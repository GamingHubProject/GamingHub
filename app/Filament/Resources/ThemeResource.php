<?php

namespace App\Filament\Resources;

use App\Experience\ThemeBundle;
use App\Experience\ThemeStorage;
use App\Filament\Resources\ThemeResource\Pages;
use App\Models\Theme;
use App\Models\ThemeAssignment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use GamingHub\Core\Models\Game;
use GamingHub\Core\Models\Server;

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

            Forms\Components\Section::make('Shape & spacing')
                ->description('Applies site-wide. Each spacing step has one job, so different parts of the site stay consistent with each other — leave any of them blank to use the built-in value.')
                ->schema(static::scaleFields())
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

            Forms\Components\Section::make('Page background')
                ->description('Sits behind everything. A pattern or gradient here reads at page scale, where the page-background colour alone would be flat.')
                ->schema([
                    Forms\Components\Select::make('site_background.type')
                        ->label('Type')
                        ->native(false)
                        ->options([
                            'color' => 'Just the page background colour',
                            'pattern' => 'Pattern',
                            'gradient' => 'Gradient',
                            'image' => 'Image',
                        ])
                        ->default('color')
                        ->live(),

                    ...static::backgroundFields('site_background'),

                    Forms\Components\FileUpload::make('background_upload')
                        ->label('Upload a background image')
                        ->image()
                        ->disk(config('assets.disk'))
                        ->directory(fn (?Theme $record) => $record ? app(ThemeStorage::class)->themePath($record->slug, 'backgrounds') : null)
                        ->visible(fn (?Theme $record, Get $get) => $record !== null && $get('site_background.type') === 'image')
                        ->dehydrated(false)
                        ->helperText('Save after uploading, then pick the file below.'),
                    Forms\Components\Select::make('site_background.image')
                        ->label('Background image')
                        ->native(false)
                        ->options(fn (?Theme $record) => $record ? app(ThemeStorage::class)->filesIn($record, 'backgrounds') : [])
                        ->visible(fn (Get $get) => $get('site_background.type') === 'image')
                        ->nullable(),
                ])
                ->columns(2)
                ->collapsed(),

            Forms\Components\Section::make('Navigation')
                ->schema([
                    Forms\Components\Toggle::make('nav_enabled')
                        ->label('Show site navigation')
                        ->default(true)
                        ->live(),
                    Forms\Components\Select::make('nav_position')
                        ->label('Where it appears')
                        ->native(false)
                        ->options(['top' => 'Top bar only', 'sidebar' => 'Left sidebar only', 'both' => 'Both'])
                        ->default('top')
                        ->live()
                        ->visible(fn (Get $get) => (bool) $get('nav_enabled'))
                        // The links themselves are site data, not part of
                        // the theme — a theme handed to another install
                        // must not carry someone else's games.
                        ->helperText('Edit the links themselves under Admin → Navigation.'),
                    Forms\Components\Select::make('nav_mirror')
                        ->label('Do both surfaces show the same links?')
                        ->native(false)
                        ->options([
                            'sidebar_follows_header' => 'Sidebar shows the header\'s links',
                            'header_follows_sidebar' => 'Header shows the sidebar\'s links',
                            'none' => 'Each has its own links',
                        ])
                        ->default('sidebar_follows_header')
                        ->visible(fn (Get $get) => (bool) $get('nav_enabled') && $get('nav_position') === 'both')
                        ->helperText('While one follows the other they are the same links — editing either changes both.'),
                ])
                ->columns(2),

            Forms\Components\Section::make('Header')
                ->description('Styled independently of the sidebar — one can be transparent while the other is solid.')
                ->schema([
                    ...static::regionFields('header'),
                    Forms\Components\Toggle::make('header.spans_full_width')
                        ->label('Header spans the full window')
                        ->helperText('Off: the sidebar runs full height and the header sits beside it. On: the header runs edge to edge above the sidebar.'),
                    Forms\Components\Toggle::make('header.show_tagline')
                        ->label('Show the tagline in the header')
                        ->helperText('Off by default — the header is the tighter surface and a tagline competes with the links.')
                        ->visible(fn (Get $get) => (bool) $get('header.show_branding')),
                ])
                ->columns(2)
                ->collapsed(),

            Forms\Components\Section::make('Sidebar')
                ->description('Styled independently of the header. The sidebar always shows the tagline when its branding block is on — space is not the constraint there.')
                ->schema([
                    Forms\Components\Select::make('sidebar.width')
                        ->label('Width')
                        ->native(false)
                        ->options(['compact' => 'Compact (200px)', 'standard' => 'Standard (240px)', 'wide' => 'Wide (300px)'])
                        ->default('standard'),
                    Forms\Components\Select::make('sidebar.behavior')
                        ->label('Behaviour')
                        ->native(false)
                        ->options([
                            'always' => 'Always visible',
                            'toggle' => 'Hidden until the menu icon is clicked',
                            'auto-hide' => 'Collapsed to icons, expands on hover',
                        ])
                        ->default('always')
                        ->helperText('Narrow screens always use the menu-icon behaviour, whatever this says.'),

                    Forms\Components\TextInput::make('sidebar.margin')
                        ->label('Gap from the window edges (px)')
                        ->helperText('0 keeps the sidebar flush against the edges. Anything above that floats it clear as a rounded panel, with a full outline instead of just a right edge.')
                        ->numeric()->minValue(0)->maxValue(64)
                        ->default(0)
                        ->live(),
                    Forms\Components\TextInput::make('sidebar.radius')
                        ->label('Corner radius (px)')
                        ->helperText('Leave blank to use the theme\'s corner radius.')
                        ->numeric()->minValue(0)->maxValue(64)
                        ->visible(fn (Get $get) => (int) $get('sidebar.margin') > 0),

                    Forms\Components\Select::make('sidebar.height')
                        ->label('Height')
                        ->native(false)
                        ->options([
                            'auto' => 'Follows its contents',
                            'full' => 'Fills the window',
                            'fixed' => 'A fixed height',
                        ])
                        ->default('auto')
                        ->live(),
                    Forms\Components\TextInput::make('sidebar.height_px')
                        ->label('Height (px)')
                        ->numeric()->minValue(80)
                        ->visible(fn (Get $get) => $get('sidebar.height') === 'fixed'),

                    Forms\Components\Select::make('sidebar.nav_align')
                        ->label('Where the links sit')
                        ->native(false)
                        ->options(['top' => 'Top', 'center' => 'Centred', 'bottom' => 'Bottom'])
                        ->default('top')
                        // Deliberately still shown when height is `auto`,
                        // with the reason stated — silently hiding it would
                        // leave an admin wondering where the setting went,
                        // and quietly forcing a height instead would be a
                        // worse surprise than a control that waits.
                        ->helperText(fn (Get $get) => $get('sidebar.height') === 'auto'
                            ? 'The sidebar is currently only as tall as its contents, so there is no spare room to move the links into. Give it a fixed or full height to use this.'
                            : 'The logo and site name stay at the top; only the links move.'),

                    ...static::regionFields('sidebar'),
                ])
                ->columns(2)
                ->collapsed(),

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
        return collect(ThemeBundle::COLOR_TOKENS)
            ->map(fn (string $label, string $key) => Forms\Components\ColorPicker::make("tokens.{$key}")
                ->label($label)
                ->hex())
            ->values()
            ->all();
    }

    /**
     * The scale tokens carry a unit, so they're numbers with a suffix
     * rather than swatches — but they're the same `tokens` map underneath
     * and reach :root the same way.
     *
     * @return list<Forms\Components\Component>
     */
    private static function scaleFields(): array
    {
        return collect(ThemeBundle::SCALE_TOKENS)
            ->map(fn (array $spec, string $key) => Forms\Components\TextInput::make("tokens.{$key}")
                ->label($spec['label'])
                ->numeric()
                ->minValue(0)
                ->suffix($spec['unit'])
                ->placeholder((string) $spec['default']))
            ->values()
            ->all();
    }

    /**
     * The type-dependent half of a background: pattern, gradient and
     * opacity controls that appear only for the type that uses them.
     *
     * Written against a path prefix so the same fields serve the page
     * background and (later) any other surface that grows one — the CSS is
     * already shared by one builder, and the form should be too rather
     * than drifting into two near-identical copies.
     *
     * @return list<Forms\Components\Component>
     */
    private static function backgroundFields(string $prefix, ?\Closure $unless = null): array
    {
        // Conditions compose rather than replace. Calling ->visible()
        // twice on a field overwrites the first closure, so a caller that
        // wants an extra condition has to pass it in here to be ANDed —
        // the alternative silently dropped each field's own type check,
        // which left a hidden gradient repeater validating its required
        // fields on a solid-colour background.
        $also = fn (\Closure $own) => fn (Get $get) => ($unless === null || $unless($get)) && $own($get);
        $isType = fn (string $type) => $also(fn (Get $get) => $get("{$prefix}.type") === $type);

        return [
            Forms\Components\ColorPicker::make("{$prefix}.color")
                ->label('Base colour')
                ->helperText('Drawn under a pattern or a non-covering image. Leave blank to use the page background token.')
                ->hex()
                ->visible($also(fn (Get $get) => in_array($get("{$prefix}.type"), ['color', 'pattern', 'image'], true))),

            Forms\Components\Select::make("{$prefix}.pattern")
                ->label('Pattern')
                ->native(false)
                ->options([
                    'dots' => 'Dots', 'grid' => 'Grid', 'diagonal-stripes' => 'Diagonal stripes',
                    'crosshatch' => 'Crosshatch', 'checkerboard' => 'Checkerboard',
                ])
                ->visible($isType('pattern')),
            Forms\Components\ColorPicker::make("{$prefix}.pattern_color")
                ->label('Pattern colour')
                ->hex()
                ->visible($isType('pattern')),

            Forms\Components\Select::make("{$prefix}.gradient.kind")
                ->label('Gradient style')
                ->native(false)
                ->options(['linear' => 'Linear', 'radial' => 'Radial'])
                ->default('linear')
                ->live()
                ->visible($isType('gradient')),
            Forms\Components\TextInput::make("{$prefix}.gradient.angle")
                ->label('Angle')
                ->numeric()->minValue(0)->maxValue(360)->suffix('°')
                ->default(135)
                // Radial gradients have no direction, so an angle field
                // would be a control that does nothing.
                ->visible($also(fn (Get $get) => $get("{$prefix}.type") === 'gradient' && $get("{$prefix}.gradient.kind") !== 'radial')),
            Forms\Components\Repeater::make("{$prefix}.gradient.stops")
                ->label('Colours')
                ->schema([
                    Forms\Components\ColorPicker::make('color')->label('Colour')->hex()->required(),
                    Forms\Components\TextInput::make('position')
                        ->label('Position')
                        ->numeric()->minValue(0)->maxValue(100)->suffix('%')
                        ->required(),
                ])
                ->columns(2)
                ->minItems(2)
                ->maxItems(3)
                ->defaultItems(2)
                ->helperText('Two or three colours. Position is how far along the gradient each one sits.')
                ->columnSpanFull()
                ->visible($isType('gradient')),

            Forms\Components\Select::make("{$prefix}.image_fit")
                ->label('Image fit')
                ->native(false)
                ->options(['cover' => 'Cover (crop to fill)', 'contain' => 'Contain (fit whole image)', 'tile' => 'Tile (repeat)'])
                ->default('cover')
                ->visible($isType('image')),

            Forms\Components\TextInput::make("{$prefix}.opacity")
                ->label('Opacity')
                ->helperText('Applies to the base colour, a pattern\'s ink and a gradient. Not to an image.')
                ->numeric()->minValue(0)->maxValue(1)->step(0.05)
                ->visible($also(fn (Get $get) => $get("{$prefix}.type") !== 'image')),
        ];
    }

    /**
     * One region's styling. Written once and used for both surfaces —
     * they're symmetrical by design, and two near-identical field lists
     * would drift the first time one gained a control.
     *
     * No "which side" border control: the sidebar's border is its right
     * edge and the header's is its bottom. See ThemeBundle::REGION_DEFAULTS.
     *
     * @return list<Forms\Components\Component>
     */
    private static function regionFields(string $prefix): array
    {
        return [
            Forms\Components\Toggle::make("{$prefix}.show_branding")
                ->label('Show the site logo and name')
                ->default(true)
                ->live()
                ->helperText('The logo, name and tagline themselves are set in Site Options — they belong to the site, not the theme.'),
            Forms\Components\Toggle::make("{$prefix}.transparent")
                ->label('Transparent')
                ->helperText('Drops this region\'s own background and edge so the page background shows through.')
                ->live(),

            Forms\Components\Select::make("{$prefix}.background.type")
                ->label('Background')
                ->native(false)
                ->options(['color' => 'Solid colour', 'pattern' => 'Pattern', 'gradient' => 'Gradient', 'image' => 'Image'])
                ->default('color')
                ->live()
                ->visible(fn (Get $get) => ! $get("{$prefix}.transparent")),
            ...static::backgroundFields("{$prefix}.background", fn (Get $get) => ! $get("{$prefix}.transparent")),

            Forms\Components\ColorPicker::make("{$prefix}.text_color")->label('Text colour')->hex(),
            Forms\Components\ColorPicker::make("{$prefix}.accent_color")
                ->label('Current item colour')
                ->helperText('Falls back to the theme\'s accent when blank.')
                ->hex(),
            Forms\Components\ColorPicker::make("{$prefix}.border.color")->label('Edge colour')->hex(),
            Forms\Components\TextInput::make("{$prefix}.border.thickness")
                ->label('Edge thickness (px)')
                ->numeric()->minValue(0)->maxValue(8),
            Forms\Components\Select::make("{$prefix}.shadow")
                ->label('Shadow')
                ->native(false)
                ->options(['none' => 'None', 'soft' => 'Soft', 'strong' => 'Strong'])
                ->default('none'),
        ];
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
                static::applyAction(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\ActionGroup::make([
                    static::duplicateAction(),
                    static::renameAction(),
                    Tables\Actions\DeleteAction::make()
                        // Deleting the row without its folder would leave
                        // an orphaned /themes/{slug}/ that the next sync
                        // would resurrect as an untracked directory.
                        ->using(fn (Theme $record) => app(ThemeStorage::class)->deleteTheme($record))
                        // A theme still in use would take the site's
                        // styling with it; make them switch first rather
                        // than silently leaving pages unstyled.
                        ->disabled(fn (Theme $record) => $record->assignments()->exists())
                        ->tooltip(fn (Theme $record) => $record->assignments()->exists()
                            ? 'In use — apply another theme first'
                            : null),
                ]),
            ])
            ->emptyStateHeading('No themes yet')
            ->emptyStateDescription('A theme is a folder in the Asset Library holding its colours, font, favicon and widget styling.');
    }

    /**
     * Apply = point a scope at this theme. Scoped rather than a bare
     * "make this the site theme" because the cascade has always had three
     * levels, and a game or server theme has to be assignable from the
     * same list as a platform one — otherwise there's no UI for it at all.
     * Platform is preselected since it's overwhelmingly the common case.
     */
    private static function applyAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('apply')
            ->label('Apply')
            ->icon('heroicon-o-check-circle')
            ->color('primary')
            ->form([
                Forms\Components\Select::make('level')
                    ->label('Apply to')
                    ->native(false)
                    ->options([
                        ThemeAssignment::LEVEL_PLATFORM => 'The whole site',
                        ThemeAssignment::LEVEL_GAME => 'One game',
                        ThemeAssignment::LEVEL_SERVER => 'One server',
                    ])
                    ->default(ThemeAssignment::LEVEL_PLATFORM)
                    ->required()
                    ->live(),
                Forms\Components\Select::make('game_id')
                    ->label('Game')
                    ->native(false)
                    ->options(fn () => Game::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->required()
                    ->visible(fn (Get $get) => $get('level') === ThemeAssignment::LEVEL_GAME),
                Forms\Components\Select::make('server_id')
                    ->label('Server')
                    ->native(false)
                    ->options(fn () => Server::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->required()
                    ->visible(fn (Get $get) => $get('level') === ThemeAssignment::LEVEL_SERVER),
            ])
            ->action(function (Theme $record, array $data) {
                ThemeAssignment::assign(
                    $data['level'],
                    $record->id,
                    $data['game_id'] ?? null,
                    $data['server_id'] ?? null,
                );

                Notification::make()
                    ->title("{$record->name} applied")
                    ->body('Reload the site to see it.')
                    ->success()
                    ->send();
            });
    }

    /**
     * Copies the folder, not just the row — a duplicate that shared its
     * original's files would mean editing one silently changed the other,
     * and neither could be exported independently.
     */
    private static function duplicateAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('duplicate')
            ->label('Duplicate')
            ->icon('heroicon-o-document-duplicate')
            ->form([
                Forms\Components\TextInput::make('name')
                    ->label('Name for the copy')
                    ->required()
                    ->default(fn (Theme $record) => "{$record->name} copy"),
            ])
            ->action(function (Theme $record, array $data) {
                $copy = app(ThemeStorage::class)->duplicateTheme($record, $data['name']);

                Notification::make()->title("Created {$copy->name}")->success()->send();
            });
    }

    private static function renameAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('rename')
            ->label('Rename')
            ->icon('heroicon-o-pencil-square')
            ->form([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->default(fn (Theme $record) => $record->name),
            ])
            ->action(function (Theme $record, array $data) {
                // Through the bundle, so theme.json stays the source of
                // truth. The folder slug deliberately doesn't follow a
                // rename — moving it would break every relative path an
                // exported copy of this theme is holding.
                $bundle = $record->bundle();
                $bundle->name = $data['name'];
                app(ThemeStorage::class)->writeBundle($record, $bundle);

                Notification::make()->title('Renamed')->success()->send();
            });
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
