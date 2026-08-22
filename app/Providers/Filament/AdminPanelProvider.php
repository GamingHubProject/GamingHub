<?php

namespace App\Providers\Filament;

use App\Filament\Pages\BrowseRegistry;
use App\Filament\Pages\SiteOptions;
use App\Filament\Resources\AdminAuditResource;
use App\Filament\Resources\ConnectorInstanceResource;
use App\Filament\Resources\GameResource;
use App\Filament\Resources\InstalledPackageResource;
use App\Filament\Resources\NavigationItemResource;
use App\Filament\Resources\PageResource;
use App\Filament\Resources\RoleResource;
use App\Filament\Resources\ServerGroupResource;
use App\Filament\Resources\ServerResource;
use App\Filament\Resources\ThemeResource;
use App\Filament\Resources\UserResource;
use App\Models\NavigationItem;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationGroup;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Throwable;

class AdminPanelProvider extends PanelProvider
{
    /**
     * Which Resource/Page classes belong to each Navcom key — the mapping
     * lives here, in code, deliberately NOT keyed by the group's display
     * label (see buildNavigation()'s docblock for why).
     *
     * @var array<string, array<class-string>>
     */
    protected const GROUP_MEMBERS = [
        'capabilities' => [ConnectorInstanceResource::class],
        'extensions' => [InstalledPackageResource::class, BrowseRegistry::class],
        'games' => [GameResource::class],
        'experience' => [PageResource::class, ThemeResource::class],
        'servers' => [ServerResource::class, ServerGroupResource::class],
        'administration' => [UserResource::class, RoleResource::class, AdminAuditResource::class],
        'basic-settings' => [NavigationItemResource::class, SiteOptions::class],
    ];

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin/system')
            ->login()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
            ])
            ->navigation(fn (NavigationBuilder $builder) => $this->buildNavigation($builder))
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    /**
     * Navcom: group order (and a ★ prefix for favorites) comes from the
     * navigation_items table. Deliberately keyed by NavigationItem.key,
     * not .label, when deciding which Resources/Pages sit in a group —
     * .label is meant to be freely admin-editable (Priority 4's own
     * spec), but Filament's stock navigationGroups() API matches items to
     * groups by exact label string, so renaming a label through that API
     * would silently orphan every Resource still declaring the old
     * string. Building navigation explicitly here instead means renaming
     * "Games" to "Game Titles" only changes what's displayed — GameResource
     * stays correctly grouped regardless.
     */
    protected function buildNavigation(NavigationBuilder $builder): NavigationBuilder
    {
        $items = $this->navigationItemsInOrder();

        $groups = [];

        foreach ($items as $item) {
            $classes = self::GROUP_MEMBERS[$item->key] ?? [];

            $navigationItems = collect($classes)
                ->flatMap(fn (string $class) => $class::getNavigationItems())
                ->all();

            if ($navigationItems === []) {
                continue;
            }

            $label = ($item->is_favorite ? '★ ' : '').$item->label;

            $groups[] = NavigationGroup::make($label)->items($navigationItems);
        }

        return $builder
            ->items(Pages\Dashboard::getNavigationItems())
            ->groups($groups);
    }

    /**
     * Falls back to the fixed, seeded order if navigation_items doesn't
     * exist yet (a brand-new database before its migration has run) —
     * boot() runs on every request/command, including the very first
     * `migrate`, so this can't assume the table is there.
     *
     * @return \Illuminate\Support\Collection<int, NavigationItem>
     */
    protected function navigationItemsInOrder(): \Illuminate\Support\Collection
    {
        try {
            return NavigationItem::inSidebarOrder();
        } catch (Throwable) {
            return collect(array_map(
                fn (string $key, string $label) => new NavigationItem(['key' => $key, 'label' => $label, 'is_favorite' => false]),
                array_keys(self::GROUP_MEMBERS),
                ['Capabilities', 'Extensions', 'Games', 'Experience', 'Servers', 'Administration', 'Basic Settings'],
            ));
        }
    }
}
