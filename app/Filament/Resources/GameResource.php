<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GameResource\Pages;
use App\Filament\Resources\GameResource\RelationManagers;
use App\Models\Game;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class GameResource extends Resource
{
    protected static ?string $model = Game::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('description')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('icon_url')
                    ->maxLength(255),
                Forms\Components\Select::make('status')
                    ->options([
                        'enabled' => 'Enabled',
                        'disabled' => 'Disabled',
                    ])
                    ->required()
                    ->default('enabled'),
                Forms\Components\Toggle::make('has_servers')
                    ->helperText('Does this game use servers, or is it community/publisher-hosted only?'),
                Forms\Components\KeyValue::make('metadata')
                    ->columnSpanFull(),
                Forms\Components\Repeater::make('configuration_schema')
                    ->label('Configuration schema')
                    ->helperText('Settings admins can tweak per server/instance (e.g. ExpRate). Presets and the Instance editor read this.')
                    ->default([])
                    ->schema([
                        Forms\Components\TextInput::make('key')
                            ->label('Setting key')
                            ->required(),
                        Forms\Components\Select::make('type')
                            ->options([
                                'decimal' => 'Decimal',
                                'integer' => 'Integer',
                                'boolean' => 'Boolean',
                                'string' => 'String',
                            ])
                            ->required()
                            ->default('decimal')
                            ->live(),
                        Forms\Components\TextInput::make('min')
                            ->numeric()
                            ->visible(fn (Forms\Get $get) => in_array($get('type'), ['decimal', 'integer'])),
                        Forms\Components\TextInput::make('max')
                            ->numeric()
                            ->visible(fn (Forms\Get $get) => in_array($get('type'), ['decimal', 'integer'])),
                        Forms\Components\TextInput::make('default')
                            ->required(),
                        Forms\Components\Toggle::make('requiresRestart')
                            ->label('Requires restart'),
                        Forms\Components\TextInput::make('description'),
                    ])
                    ->columns(3)
                    ->columnSpanFull()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['key'] ?? null),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'enabled' ? 'success' : 'gray')
                    ->searchable(),
                Tables\Columns\IconColumn::make('has_servers')
                    ->boolean(),
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
                    ->label(fn (Game $record) => $record->status === 'enabled' ? 'Disable' : 'Enable')
                    ->icon('heroicon-o-power')
                    ->action(fn (Game $record) => $record->update([
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
            'index' => Pages\ListGames::route('/'),
            'create' => Pages\CreateGame::route('/create'),
            'edit' => Pages\EditGame::route('/{record}/edit'),
        ];
    }
}
