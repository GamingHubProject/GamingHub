<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InstalledPackageResource\Pages;
use App\Models\InstalledPackage;
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
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('toggle')
                    ->label(fn (InstalledPackage $record) => $record->status === 'enabled' ? 'Disable' : 'Enable')
                    ->icon('heroicon-o-power')
                    ->action(fn (InstalledPackage $record) => $record->update([
                        'status' => $record->status === 'enabled' ? 'disabled' : 'enabled',
                    ])),
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
}
