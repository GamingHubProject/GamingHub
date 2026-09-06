<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InstalledPackageResource\Pages;
use App\Models\InstalledPackage;
use App\Models\Theme;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class InstalledPackageResource extends Resource
{
    protected static ?string $model = InstalledPackage::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Extensions';

    protected static ?string $navigationLabel = 'Installed Packages';

    protected static ?string $modelLabel = 'Installed Package';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Package record')
                    ->description(
                        'Tracks what Manager has installed — a Hub Extension (no game) or a Game '
                        .'Integration (bound to a game). This form edits the record directly; use '
                        .'"Install from registry" on the list page to actually download a package.'
                    )
                    ->schema([
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Stable machine identifier, e.g. "palworld-integration".'),
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('version')
                            ->required()
                            ->maxLength(255)
                            ->default('0.1.000'),
                        Forms\Components\Select::make('status')
                            ->options([
                                'enabled' => 'Enabled',
                                'disabled' => 'Disabled',
                                // Themes have no on/off state — see the
                                // kind column and InstalledPackage's
                                // STATUS_INSTALLED. Listed so editing a
                                // theme's row doesn't blank its status.
                                InstalledPackage::STATUS_INSTALLED => 'Installed',
                            ])
                            ->required()
                            ->default('disabled'),
                        Forms\Components\Select::make('game_id')
                            ->label('Bound to game')
                            ->relationship('game', 'name')
                            ->helperText('Optional — only Game Integrations bind to a game.'),
                        Forms\Components\Textarea::make('description')
                            ->columnSpanFull(),
                        Forms\Components\KeyValue::make('manifest')
                            ->label('Requires (from manifest)')
                            ->helperText('Dependency constraints read from the package\'s own gaming-hub-extension.json.')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->searchable(),
                Tables\Columns\TextColumn::make('kind')
                    ->badge()
                    ->color(fn (string $state): string => $state === InstalledPackage::KIND_THEME ? 'info' : 'gray'),
                Tables\Columns\TextColumn::make('version'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'enabled' ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('game.name')
                    ->label('Bound game')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('installed_at')
                    ->dateTime()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'enabled' => 'Enabled',
                        'disabled' => 'Disabled',
                        InstalledPackage::STATUS_INSTALLED => 'Installed',
                    ]),
                Tables\Filters\SelectFilter::make('kind')
                    ->options([
                        InstalledPackage::KIND_EXTENSION => 'Extension',
                        InstalledPackage::KIND_THEME => 'Theme',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('toggle')
                    ->label(fn (InstalledPackage $record) => $record->status === 'enabled' ? 'Disable' : 'Enable')
                    ->icon('heroicon-o-power')
                    // A theme is applied through a ThemeAssignment, not
                    // switched on here. Offering a toggle that changes a
                    // string nothing reads would be a lie.
                    ->visible(fn (InstalledPackage $record) => $record->kind !== InstalledPackage::KIND_THEME)
                    ->action(fn (InstalledPackage $record) => $record->update([
                        'status' => $record->status === 'enabled' ? 'disabled' : 'enabled',
                    ])),
                Tables\Actions\Action::make('manageTheme')
                    ->label('Manage theme')
                    ->icon('heroicon-o-swatch')
                    ->color('gray')
                    // Only once the theme it installed still exists — an
                    // admin can delete a theme without touching this row.
                    ->visible(fn (InstalledPackage $record) => $record->kind === InstalledPackage::KIND_THEME
                        && static::themeFor($record) !== null)
                    ->url(fn (InstalledPackage $record) => ThemeResource::getUrl('edit', ['record' => static::themeFor($record)])),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInstalledPackages::route('/'),
            'create' => Pages\CreateInstalledPackage::route('/create'),
            'edit' => Pages\EditInstalledPackage::route('/{record}/edit'),
        ];
    }

    /**
     * The theme a package row installed, if it is still there.
     *
     * The slug is recorded at install time (see ThemeInstaller) rather
     * than matched by name, because an admin may rename either one
     * afterwards and the row still needs to point at the right theme.
     */
    protected static function themeFor(InstalledPackage $record): ?Theme
    {
        $slug = $record->manifest['theme_slug'] ?? null;

        return $slug ? Theme::where('slug', $slug)->first() : null;
    }
}
