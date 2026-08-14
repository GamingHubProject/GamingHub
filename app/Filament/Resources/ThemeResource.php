<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ThemeResource\Pages;
use App\Models\Theme;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ThemeResource extends Resource
{
    protected static ?string $model = Theme::class;

    protected static ?string $navigationIcon = 'heroicon-o-swatch';

    protected static ?string $navigationGroup = 'Experience';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('level')
                    ->options([
                        Theme::LEVEL_PLATFORM => 'Platform (global default)',
                        Theme::LEVEL_GAME => 'Game override',
                        Theme::LEVEL_SERVER => 'Server override',
                    ])
                    ->required()
                    ->live()
                    ->helperText('Each level inherits from its parent unless a token is overridden here.'),
                Forms\Components\Select::make('game_id')
                    ->label('Game')
                    ->relationship('game', 'name')
                    ->required(fn (Get $get) => in_array($get('level'), [Theme::LEVEL_GAME, Theme::LEVEL_SERVER]))
                    ->visible(fn (Get $get) => in_array($get('level'), [Theme::LEVEL_GAME, Theme::LEVEL_SERVER])),
                Forms\Components\Select::make('server_id')
                    ->label('Server')
                    ->relationship('server', 'name')
                    ->required(fn (Get $get) => $get('level') === Theme::LEVEL_SERVER)
                    ->visible(fn (Get $get) => $get('level') === Theme::LEVEL_SERVER),
                Forms\Components\KeyValue::make('tokens')
                    ->label('Design tokens')
                    ->keyLabel('Token')
                    ->valueLabel('Value')
                    ->helperText('e.g. color-primary, font-body — only set the tokens you want to override at this level.')
                    ->columnSpanFull(),
                Forms\Components\Toggle::make('is_default')
                    ->label('Default theme at this level')
                    ->helperText('When multiple themes exist at the same level/scope, the default is used.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('level')
                    ->badge(),
                Tables\Columns\TextColumn::make('game.name')
                    ->label('Game')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('server.name')
                    ->label('Server')
                    ->placeholder('—'),
                Tables\Columns\IconColumn::make('is_default')
                    ->boolean(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('level')
                    ->options([
                        Theme::LEVEL_PLATFORM => 'Platform',
                        Theme::LEVEL_GAME => 'Game',
                        Theme::LEVEL_SERVER => 'Server',
                    ]),
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
            'index' => Pages\ListThemes::route('/'),
            'create' => Pages\CreateTheme::route('/create'),
            'edit' => Pages\EditTheme::route('/{record}/edit'),
        ];
    }
}
