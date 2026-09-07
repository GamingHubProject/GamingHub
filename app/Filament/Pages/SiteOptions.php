<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ThemeResource;
use App\Models\SiteOption;
use App\Profiles\ProfileWidgets;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\HtmlString;

/**
 * A single settings form, not a Resource — there's exactly one row to
 * edit (SiteOption::current()), so list/create/delete routes would be
 * dead weight. save() writes straight to the singleton row; applying
 * site_name/timezone to the running app's config happens in
 * AppServiceProvider::boot(), not here — this page only owns the form.
 */
class SiteOptions extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Basic Settings';

    protected static ?string $navigationLabel = 'Options';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.site-options';

    public ?array $data = [];

    public function mount(): void
    {
        $values = SiteOption::current()->values;

        // A site that has never opened this page has no stored list, and
        // an unset CheckboxList fills as "nothing checked" — which would
        // then save as "no widgets allowed" the first time somebody
        // touches an unrelated setting. Seeding the starter set here keeps
        // the form showing what the site is actually doing.
        $values[ProfileWidgets::OPTION_KEY] ??= ProfileWidgets::DEFAULT_ENABLED;

        $this->form->fill($values);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('site_name')
                    ->label('Site name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('site_tagline')
                    ->label('Tagline')
                    ->helperText('A short line shown under the site name in the sidebar. Separate from the description above, which is the SEO meta text.')
                    ->maxLength(255),
                Forms\Components\Select::make('logo_asset_id')
                    ->label('Logo')
                    ->native(false)
                    ->helperText('Shown beside the site name in the header and sidebar. Upload it in the Asset Library first (Admin > Assets).')
                    ->options(fn () => \App\Models\Asset::query()
                        ->whereIn('mime_type', ['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'])
                        ->latest()
                        ->limit(200)
                        ->get()
                        ->mapWithKeys(fn (\App\Models\Asset $asset) => [$asset->id => $asset->alt_text ?: basename($asset->disk_path)]))
                    ->searchable()
                    ->nullable(),
                Forms\Components\Textarea::make('site_description')
                    ->label('Site description')
                    ->helperText('Used as the meta description for SEO.')
                    ->maxLength(1000),
                Forms\Components\TextInput::make('site_url')
                    ->label('Site URL')
                    ->url()
                    ->helperText('Base URL used to build absolute links, e.g. https://example.com'),
                Forms\Components\Select::make('timezone')
                    ->options(array_combine(timezone_identifiers_list(), timezone_identifiers_list()))
                    ->searchable()
                    ->required(),
                Forms\Components\TextInput::make('admin_email')
                    ->label('Admin email')
                    ->email()
                    ->helperText('For alerts — not used yet.'),
                Forms\Components\TextInput::make('discord_webhook')
                    ->label('Discord webhook')
                    ->helperText('Optional — for future news/alerts. Not validated or tested here.'),
                // Policy, not capability: a widget only appears here if it
                // can actually render on a profile (see
                // App\Profiles\ProfileWidgets), and unchecking one takes it
                // out of what people may add — it can never add a widget
                // profiles cannot show.
                Forms\Components\CheckboxList::make(ProfileWidgets::OPTION_KEY)
                    ->label('Widgets people may put on their profile')
                    ->helperText('Unchecking one hides it from the profile editor. Widgets already placed stop rendering.')
                    ->options(ProfileWidgets::CAPABLE)
                    ->default(ProfileWidgets::DEFAULT_ENABLED)
                    ->columns(2),
                Forms\Components\Placeholder::make('appearance_moved')
                    ->label('Appearance')
                    ->content(new HtmlString(
                        'Colours, font, favicon, header style and widget defaults are part of a <strong>Theme</strong>, '
                        .'not a site setting — edit them under <a class="fi-link" style="text-decoration:underline" href="'
                        .ThemeResource::getUrl().'">Experience &rsaquo; Themes</a>.'
                    )),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        SiteOption::current()->update(['values' => $this->form->getState()]);

        Notification::make()
            ->title('Options saved')
            ->success()
            ->send();
    }
}
