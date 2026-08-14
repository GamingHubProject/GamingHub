<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GameExtensionResource\Pages;
use App\Filament\Resources\GameExtensionResource\RelationManagers;
use App\Models\GameExtension;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class GameExtensionResource extends Resource
{
    protected static ?string $model = GameExtension::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Extensions';

    protected static ?string $navigationLabel = 'Game Extensions';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Extension registry entry')
                    ->description(
                        'This tracks which Game Extension packages are known and enabled — it is not a '
                        .'download/install tool (that belongs to the separate Manager package, not yet built). '
                        .'The "slug" is the extension\'s stable technical ID, the same idea as a plugin\'s '
                        .'machine name — it stays constant even if the display name changes. Once an '
                        .'extension is bound to a Game below, manage that game\'s actual settings under '
                        .'Games → Games, and its presets under Servers → Presets.'
                    )
                    ->schema([
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Stable machine identifier, e.g. "palworld-integration" — not a display name.'),
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
                            ->helperText('Optional — leave empty until this extension is bound to a specific game.'),
                        Forms\Components\Textarea::make('description')
                            ->columnSpanFull(),
                        Forms\Components\KeyValue::make('manifest')
                            ->helperText('Free-form package metadata (capabilities, provided widgets, etc.) for future use.')
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
                    ->label(fn (GameExtension $record) => $record->status === 'enabled' ? 'Disable' : 'Enable')
                    ->icon('heroicon-o-power')
                    ->action(fn (GameExtension $record) => $record->update([
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
            'index' => Pages\ListGameExtensions::route('/'),
            'create' => Pages\CreateGameExtension::route('/create'),
            'edit' => Pages\EditGameExtension::route('/{record}/edit'),
        ];
    }
}
