<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ConfigurationPresetResource\Pages;
use App\Filament\Resources\ConfigurationPresetResource\RelationManagers;
use App\Models\ConfigurationPreset;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ConfigurationPresetResource extends Resource
{
    protected static ?string $model = ConfigurationPreset::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('game_id')
                    ->relationship('game', 'name')
                    ->required(),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->helperText('e.g. hardcore, casual, event'),
                Forms\Components\KeyValue::make('values')
                    ->keyLabel('Setting')
                    ->valueLabel('Value')
                    ->helperText('Keys should match this game\'s configuration schema setting names.')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('game.name')
                    ->label('Game')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('game_id')
                    ->relationship('game', 'name')
                    ->label('Game'),
            ])
            ->actions([
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
            'index' => Pages\ListConfigurationPresets::route('/'),
            'create' => Pages\CreateConfigurationPreset::route('/create'),
            'edit' => Pages\EditConfigurationPreset::route('/{record}/edit'),
        ];
    }
}
