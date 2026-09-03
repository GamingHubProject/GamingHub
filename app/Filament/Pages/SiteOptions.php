<?php

namespace App\Filament\Pages;

use App\Models\Asset;
use App\Models\SiteOption;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

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
        $this->form->fill(SiteOption::current()->values);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('site_name')
                    ->label('Site name')
                    ->required()
                    ->maxLength(255),
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
                Forms\Components\Select::make('font_asset_id')
                    ->label('Platform default font')
                    ->helperText('Upload a .woff/.woff2 file into the Fonts folder from the Asset Library first (Admin > Assets), then pick it here. A page can override this — see that page\'s "Edit layout" controls.')
                    ->options(fn () => Asset::query()
                        ->whereHas('folder', fn ($q) => $q->where('slug', 'fonts'))
                        ->get()
                        ->mapWithKeys(fn (Asset $asset) => [$asset->id => $asset->alt_text ?: basename($asset->disk_path)]))
                    ->searchable()
                    ->nullable(),
                Forms\Components\Select::make('favicon_asset_id')
                    ->label('Favicon')
                    ->helperText('Upload a .png/.ico/.svg into the Icons folder from the Asset Library first (Admin > Assets), then pick it here.')
                    ->options(fn () => Asset::query()
                        ->whereHas('folder', fn ($q) => $q->where('slug', 'icons'))
                        ->get()
                        ->mapWithKeys(fn (Asset $asset) => [$asset->id => $asset->alt_text ?: basename($asset->disk_path)]))
                    ->searchable()
                    ->nullable(),
                Forms\Components\Toggle::make('header_transparent')
                    ->label('Transparent site header')
                    ->helperText('Drops the header\'s own background and bottom border so a page-wide background image shows through behind the nav.'),
                // Purely per-widget-instance from here down — unlike font,
                // there's no page-level tier (confirmed: border/text/
                // background are naturally per-widget, not a whole-page
                // aesthetic choice the way a typeface is). Every field
                // left blank here just means "no app-wide default", not
                // "0"/"off" — a widget without its own override falls
                // back further to the hardcoded chrome baseline (see the
                // frontend's resolveWidgetStyle).
                Forms\Components\Section::make('Default widget style')
                    ->description('Applies to every widget on every page unless a specific widget instance overrides it in its own settings.')
                    ->schema([
                        Forms\Components\Toggle::make('widget_style_defaults.border_enabled')
                            ->label('Show a border by default'),
                        Forms\Components\TextInput::make('widget_style_defaults.border_thickness')
                            ->label('Border thickness (px)')
                            ->numeric()
                            ->minValue(1),
                        Forms\Components\ColorPicker::make('widget_style_defaults.border_color')
                            ->label('Default border color'),
                        Forms\Components\TextInput::make('widget_style_defaults.border_radius')
                            ->label('Default border roundness (px)')
                            ->numeric()
                            ->minValue(0),
                        Forms\Components\ColorPicker::make('widget_style_defaults.text_color')
                            ->label('Default text color'),
                        Forms\Components\TextInput::make('widget_style_defaults.text_size')
                            ->label('Default text size (px)')
                            ->helperText('Self-scaling card widgets (Game/Server/Server Group Card) use the relative adjustment below instead.')
                            ->numeric()
                            ->minValue(1),
                        Forms\Components\TextInput::make('widget_style_defaults.text_scale')
                            ->label('Default text size adjustment for self-scaling cards')
                            ->helperText('0.5–2, where 1 means unchanged (e.g. 1.1 = 10% larger).')
                            ->numeric()
                            ->minValue(0.5)
                            ->maxValue(2)
                            ->step(0.05),
                        Forms\Components\Select::make('widget_style_defaults.background_type')
                            ->label('Default background type')
                            ->helperText('Leave blank for a solid color, which is what every widget used before patterns/images existed.')
                            ->options(['color' => 'Solid color', 'pattern' => 'Pattern', 'image' => 'Image'])
                            ->nullable(),
                        Forms\Components\ColorPicker::make('widget_style_defaults.background_color')
                            ->label('Default background color')
                            ->helperText('The base fill in every background type — a pattern\'s ink and an image both draw on top of it.'),
                        Forms\Components\TextInput::make('widget_style_defaults.background_opacity')
                            ->label('Default background opacity (0–1)')
                            ->helperText('Applies to the base color and a pattern\'s ink. Not to an image.')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(1)
                            ->step(0.05),
                        Forms\Components\Select::make('widget_style_defaults.background_pattern')
                            ->label('Default pattern')
                            ->options([
                                'dots' => 'Dots',
                                'grid' => 'Grid',
                                'diagonal-stripes' => 'Diagonal stripes',
                                'crosshatch' => 'Crosshatch',
                                'checkerboard' => 'Checkerboard',
                            ])
                            ->nullable(),
                        Forms\Components\ColorPicker::make('widget_style_defaults.background_pattern_color')
                            ->label('Default pattern color'),
                        // No global default background *image* picker here
                        // on purpose: one image repeated behind every
                        // widget on the site is not a thing an admin
                        // realistically wants, and the Asset Library's
                        // picker lives in the SPA, not Filament. The
                        // resolver still honors background_image_url if
                        // it's present in these defaults, so a Theme
                        // bundle can carry one — this is just not a knob
                        // worth hand-setting.
                        Forms\Components\Select::make('widget_style_defaults.background_image_fit')
                            ->label('Default background image fit')
                            ->options(['cover' => 'Cover (crop to fill)', 'contain' => 'Contain (fit whole image)', 'tile' => 'Tile (repeat)'])
                            ->nullable(),
                    ])
                    ->columns(2),
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
