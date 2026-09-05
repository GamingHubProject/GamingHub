<?php

namespace App\Filament\Resources\ThemeResource\Pages;

use App\Experience\ThemeBundle;
use App\Experience\ThemeResolver;
use App\Experience\ThemeStorage;
use App\Models\NavigationLink;
use App\Filament\Resources\ThemeResource;
use App\Models\Theme;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

/**
 * The form edits the theme's folder, not its DB columns. theme.json is the
 * source of truth (see ThemeStorage), so this fills from the bundle and
 * saves back to it; the index row is refreshed as a side effect of the
 * write rather than being written to directly.
 */
class EditTheme extends EditRecord
{
    protected static string $resource = ThemeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // A folder edited outside the app (an import, a registry
            // install, a hand-edited theme.json) leaves the cached row
            // describing an older version until something re-reads it.
            Actions\Action::make('sync')
                ->label('Re-read from folder')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->visible(fn (Theme $record) => $record->isStale())
                ->action(function (Theme $record) {
                    app(ThemeStorage::class)->sync($record);
                    Notification::make()->title('Re-read from folder')->success()->send();

                    return redirect(static::getResource()::getUrl('edit', ['record' => $record]));
                }),
            Actions\DeleteAction::make()
                ->using(fn (Theme $record) => app(ThemeStorage::class)->deleteTheme($record)),
        ];
    }

    protected function fillForm(): void
    {
        $this->form->fill(static::bundleToFormState($this->getRecord()->bundle()));
    }

    /** @return array<string, mixed> */
    public static function bundleToFormState(ThemeBundle $bundle): array
    {
        return [
            'name' => $bundle->name,
            'tokens' => $bundle->tokens,
            'extra_tokens' => $bundle->extraTokens,
            'font_file' => $bundle->fontFile,
            'font_family' => $bundle->fontFamily,
            'favicon_file' => $bundle->faviconFile,
            'widgetStyle' => $bundle->widgetStyle,
            // Defaulted rather than left empty so the type dropdown has a
            // selection on a theme that has never set a background.
            'site_background' => $bundle->siteBackground ?: ['type' => 'color'],
            'nav_enabled' => $bundle->navEnabled,
            'nav_position' => $bundle->navPosition,
            'nav_mirror' => $bundle->navMirror,
            'header' => static::regionToFormState($bundle->header),
            'sidebar' => static::regionToFormState($bundle->sidebar),
        ];
    }

    public static function formStateToBundle(array $state, ThemeBundle $existing): ThemeBundle
    {
        return new ThemeBundle(
            id: $existing->id,
            name: $state['name'] ?? $existing->name,
            version: $existing->version,
            // Blank colour pickers are dropped rather than stored as "" —
            // an empty token would override the theme beneath it with
            // nothing, which is not what clearing a field means.
            tokens: array_filter($state['tokens'] ?? [], fn ($v) => $v !== null && $v !== ''),
            extraTokens: $state['extra_tokens'] ?? [],
            fontFile: $state['font_file'] ?? null,
            fontFamily: $state['font_family'] ?: null,
            faviconFile: $state['favicon_file'] ?? null,
            widgetStyle: array_filter($state['widgetStyle'] ?? [], fn ($v) => $v !== null && $v !== ''),
            siteBackground: static::cleanBackground($state['site_background'] ?? []),
            header: static::regionFromFormState($state['header'] ?? [], ThemeBundle::HEADER_DEFAULTS),
            sidebar: static::regionFromFormState($state['sidebar'] ?? [], ThemeBundle::SIDEBAR_DEFAULTS),
            navEnabled: (bool) ($state['nav_enabled'] ?? true),
            navPosition: $state['nav_position'] ?? 'top',
            navMirror: $state['nav_mirror'] ?? 'sidebar_follows_header',
        );
    }

    /** Gives the background sub-form a type to show controls for. */
    private static function regionToFormState(array $region): array
    {
        $region['background'] = ($region['background'] ?? []) ?: ['type' => 'color'];

        return $region;
    }

    /**
     * A region back from the form, over its defaults so an unrendered
     * control (one hidden behind `transparent`, say) keeps its stored
     * value rather than being blanked by its own absence.
     */
    private static function regionFromFormState(array $state, array $defaults): array
    {
        $region = array_merge($defaults, $state);
        $region['border'] = array_merge($defaults['border'], is_array($state['border'] ?? null) ? $state['border'] : []);
        $region['background'] = static::cleanBackground($state['background'] ?? []);

        return $region;
    }

    /**
     * Drops the fields the chosen type doesn't use, so a theme that ended
     * up as a gradient isn't still carrying the pattern someone tried
     * first — theme.json is a published contract, and stale keys in it
     * mislead whoever reads an export.
     */
    private static function cleanBackground(array $background): array
    {
        $type = $background['type'] ?? 'color';
        $keep = match ($type) {
            'pattern' => ['type', 'color', 'opacity', 'pattern', 'pattern_color'],
            'gradient' => ['type', 'opacity', 'gradient'],
            'image' => ['type', 'color', 'image', 'image_fit'],
            default => ['type', 'color', 'opacity'],
        };

        $cleaned = array_filter(
            array_intersect_key($background, array_flip($keep)),
            fn ($value) => $value !== null && $value !== '' && $value !== []
        );

        // Nothing but the default type set is the same as no background at
        // all; storing {"type":"color"} would imply an intent that isn't
        // there.
        return $cleaned === ['type' => 'color'] ? [] : $cleaned;
    }

    protected function handleRecordUpdate($record, array $data): Theme
    {
        $before = $record->bundle()->navMirror;
        $saved = app(ThemeStorage::class)->writeBundle($record, static::formStateToBundle($data, $record->bundle()));

        $this->materialiseMirroredLinks($before, $saved->bundle()->navMirror);

        return $saved;
    }

    /**
     * Mirroring is a pointer while it's on: the follower owns no rows and
     * simply renders the leader's. Turning it off is therefore the one
     * moment anything is copied — without this the follower would go from
     * showing the leader's links to showing nothing at all, which reads as
     * having lost them.
     *
     * Only meaningful for the theme actually in effect; a mirroring change
     * on some other theme takes effect when that one is applied, and
     * copying now would clobber the live navigation.
     */
    private function materialiseMirroredLinks(string $before, string $after): void
    {
        if ($before === $after || $after !== 'none') {
            return;
        }

        if (app(ThemeResolver::class)->effectiveTheme()?->id !== $this->getRecord()->id) {
            return;
        }

        [$from, $to] = match ($before) {
            'sidebar_follows_header' => [NavigationLink::SURFACE_HEADER, NavigationLink::SURFACE_SIDEBAR],
            'header_follows_sidebar' => [NavigationLink::SURFACE_SIDEBAR, NavigationLink::SURFACE_HEADER],
            default => [null, null],
        };

        if ($from !== null) {
            NavigationLink::copySurface($from, $to);
        }
    }
}
