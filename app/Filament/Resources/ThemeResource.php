<?php

namespace App\Filament\Resources;

use App\Experience\ThemeBundle;
use App\Experience\ThemeStorage;
use App\Filament\Resources\ThemeResource\Pages;
use App\Models\Theme;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * The one place a site's appearance is edited. Everything Phase A put in
 * Site Options — font, favicon, header transparency, widget style defaults
 * — lives here now, alongside the colour tokens; Site Options went back to
 * being branding only.
 *
 * Two things about this form are deliberate and worth not "simplifying"
 * later:
 *
 * - Every Select is ->native(false). Filament's own (Choices.js) dropdown
 *   opens and selects on a single click; bare native <select> popups do
 *   not on every platform, which is exactly the regression Phase A shipped.
 *
 * - The form's state is the theme's bundle, not the model's columns. The
 *   folder is the source of truth (see ThemeStorage), so the edit pages
 *   fill from theme.json and save back to it; the DB row is refreshed as a
 *   consequence, never written directly.
 */
class ThemeResource extends Resource
{
    protected static ?string $model = Theme::class;

    protected static ?string $navigationIcon = 'heroicon-o-swatch';

    protected static ?string $navigationGroup = 'Experience';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->helperText('Also names the theme\'s folder in the Asset Library.'),

            Forms\Components\Section::make('Colours')
                ->description('The design tokens every page and widget reads. Anything left blank falls back to the theme beneath this one, or to the built-in look.')
                ->schema(static::tokenFields())
                ->columns(2),

            Forms\Components\Section::make('Font')
                ->description('Upload a .woff/.woff2 into this theme, or pick one already in it. The file is copied into the theme\'s own folder so the theme stays self-contained.')
                ->schema([
                    Forms\Components\FileUpload::make('font_upload')
                        ->label('Upload a font')
                        ->disk(config('assets.disk'))
                        ->directory(fn (?Theme $record) => $record ? app(ThemeStorage::class)->themePath($record->slug, 'font') : null)
                        ->acceptedFileTypes(['font/woff', 'font/woff2', 'application/octet-stream'])
                        ->visible(fn (?Theme $record) => $record !== null)
                        ->dehydrated(false)
                        ->helperText('Save after uploading, then pick the file below.'),
                    Forms\Components\Select::make('font_file')
                        ->label('Font file')
                        ->native(false)
                        ->options(fn (?Theme $record) => $record ? app(ThemeStorage::class)->filesIn($record, 'font') : [])
                        ->nullable(),
                    Forms\Components\TextInput::make('font_family')
                        ->label('Font family name')
                        ->helperText('Optional — only affects the CSS family name, not which file loads.'),
                ])
                ->columns(2),

            Forms\Components\Section::make('Favicon')
                ->schema([
                    Forms\Components\FileUpload::make('favicon_upload')
                        ->label('Upload an icon')
                        ->image()
                        ->disk(config('assets.disk'))
                        ->directory(fn (?Theme $record) => $record ? app(ThemeStorage::class)->themePath($record->slug, 'favicon') : null)
                        ->visible(fn (?Theme $record) => $record !== null)
                        ->dehydrated(false)
                        ->helperText('Save after uploading, then pick the file below.'),
                    Forms\Components\Select::make('favicon_file')
                        ->label('Icon file')
                        ->native(false)
                        ->options(fn (?Theme $record) => $record ? app(ThemeStorage::class)->filesIn($record, 'favicon') : [])
                        ->nullable(),
                ])
                ->columns(2),

            Forms\Components\Section::make('Site chrome')
                ->schema([
                    Forms\Components\Toggle::make('header_transparent')
                        ->label('Transparent site header')
                        ->helperText('Drops the header\'s own background and bottom border so a page-wide background image shows through behind the nav.'),
                ]),

            Forms\Components\Section::make('Default widget style')
                ->description('Applies to every widget on every page unless a specific widget instance overrides it in its own settings.')
                ->schema(static::widgetStyleFields())
                ->columns(2)
                ->collapsed(),

            Forms\Components\Section::make('Additional tokens')
                ->description('Escape hatch for a design that needs a CSS variable outside the standard set above. Rarely needed.')
                ->schema([
                    Forms\Components\KeyValue::make('extra_tokens')
                        ->keyLabel('Token')
                        ->valueLabel('Value')
                        ->addActionLabel('Add token'),
                ])
                ->collapsed(),
        ]);
    }

    /**
     * One labelled colour control per token in the contract, rather than
     * the free-form key/value grid themes used to carry. Free-form keys
     * can't be validated, can't be shown as a swatch, and — the real
     * problem — can't be relied on to mean anything on the install that
     * receives an exported theme.
     *
     * @return list<Forms\Components\Component>
     */
    private static function tokenFields(): array
    {
        return collect(ThemeBundle::TOKENS)
            ->map(fn (string $label, string $key) => Forms\Components\ColorPicker::make("tokens.{$key}")
                ->label($label)
                ->hex())
            ->values()
            ->all();
    }

    /** @return list<Forms\Components\Component> */
    private static function widgetStyleFields(): array
    {
        return [
            Forms\Components\Toggle::make('widgetStyle.border_enabled')->label('Show a border by default'),
            Forms\Components\TextInput::make('widgetStyle.border_thickness')->label('Border thickness (px)')->numeric()->minValue(1),
            Forms\Components\ColorPicker::make('widgetStyle.border_color')->label('Border colour')->hex(),
            Forms\Components\TextInput::make('widgetStyle.border_radius')->label('Border roundness (px)')->numeric()->minValue(0),
            Forms\Components\ColorPicker::make('widgetStyle.text_color')->label('Text colour')->hex(),
            Forms\Components\TextInput::make('widgetStyle.text_size')->label('Text size (px)')
                ->helperText('Self-scaling card widgets use the relative adjustment below instead.')
                ->numeric()->minValue(1),
            Forms\Components\TextInput::make('widgetStyle.text_scale')->label('Text size adjustment for self-scaling cards')
                ->helperText('0.5–2, where 1 means unchanged.')
                ->numeric()->minValue(0.5)->maxValue(2)->step(0.05),
            Forms\Components\Select::make('widgetStyle.background_type')->label('Background type')
                ->native(false)
                ->options(['color' => 'Solid colour', 'pattern' => 'Pattern', 'image' => 'Image'])
                ->nullable(),
            Forms\Components\ColorPicker::make('widgetStyle.background_color')->label('Background colour')
                ->helperText('The base fill in every background type.')
                ->hex(),
            Forms\Components\TextInput::make('widgetStyle.background_opacity')->label('Background opacity (0–1)')
                ->helperText('Applies to the base colour and a pattern\'s ink. Not to an image.')
                ->numeric()->minValue(0)->maxValue(1)->step(0.05),
            Forms\Components\Select::make('widgetStyle.background_pattern')->label('Pattern')
                ->native(false)
                ->options([
                    'dots' => 'Dots', 'grid' => 'Grid', 'diagonal-stripes' => 'Diagonal stripes',
                    'crosshatch' => 'Crosshatch', 'checkerboard' => 'Checkerboard',
                ])
                ->nullable(),
            Forms\Components\ColorPicker::make('widgetStyle.background_pattern_color')->label('Pattern colour')->hex(),
            Forms\Components\Select::make('widgetStyle.background_image_fit')->label('Background image fit')
                ->native(false)
                ->options(['cover' => 'Cover (crop to fill)', 'contain' => 'Contain (fit whole image)', 'tile' => 'Tile (repeat)'])
                ->nullable(),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('slug')->label('Folder')
                    ->formatStateUsing(fn (string $state) => '/themes/'.$state.'/')
                    ->color('gray'),
                Tables\Columns\TextColumn::make('assignments_summary')
                    ->label('Used for')
                    ->state(fn (Theme $record) => static::assignmentSummary($record))
                    ->badge()
                    ->color(fn (string $state) => $state === 'Not in use' ? 'gray' : 'success'),
                Tables\Columns\IconColumn::make('is_builtin')->label('Built in')->boolean()->toggleable(),
                Tables\Columns\TextColumn::make('synced_at')->dateTime()->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    // Deleting the row without its folder would leave an
                    // orphaned /themes/{slug}/ that the next sync would
                    // resurrect as an untracked directory.
                    ->using(fn (Theme $record) => app(ThemeStorage::class)->deleteTheme($record)),
            ])
            ->emptyStateHeading('No themes yet')
            ->emptyStateDescription('A theme is a folder in the Asset Library holding its colours, font, favicon and widget styling.');
    }

    private static function assignmentSummary(Theme $record): string
    {
        $levels = $record->assignments->pluck('level')->unique();

        return $levels->isEmpty() ? 'Not in use' : $levels->map(ucfirst(...))->join(', ');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListThemes::route('/'),
            'create' => Pages\CreateTheme::route('/create'),
            'edit' => Pages\EditTheme::route('/{record}/edit'),
        ];
    }
}
