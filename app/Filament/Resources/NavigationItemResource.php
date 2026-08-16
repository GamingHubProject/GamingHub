<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NavigationItemResource\Pages;
use App\Models\NavigationItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Navcom. Rows are seeded once (see the navigation_items migration) and
 * locked — no create/delete action anywhere on this resource, only
 * reorder + edit label/favorite/icon. 'key' ties a row to a real Filament
 * navigationGroup and is never editable once created.
 */
class NavigationItemResource extends Resource
{
    protected static ?string $model = NavigationItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-bars-3';

    protected static ?string $navigationGroup = 'Basic Settings';

    protected static ?string $navigationLabel = 'Navcom';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('key')
                    ->disabled()
                    ->dehydrated(false),
                Forms\Components\TextInput::make('label')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('order')
                    ->numeric()
                    ->required(),
                Forms\Components\Toggle::make('is_favorite')
                    ->label('Favorite')
                    ->helperText('Favorited groups always sort above non-favorites, regardless of order.'),
                Forms\Components\TextInput::make('icon')
                    ->label('Icon')
                    ->helperText('Optional Filament icon class, e.g. "heroicon-o-star".')
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->orderByDesc('is_favorite')->orderBy('order'))
            ->reorderable('order')
            ->columns([
                Tables\Columns\TextColumn::make('label'),
                Tables\Columns\TextColumn::make('order'),
                Tables\Columns\IconColumn::make('is_favorite')
                    ->label('Favorite')
                    ->boolean(),
                Tables\Columns\TextColumn::make('icon')
                    ->placeholder('—'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNavigationItems::route('/'),
            'edit' => Pages\EditNavigationItem::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
