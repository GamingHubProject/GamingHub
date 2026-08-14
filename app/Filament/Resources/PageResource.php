<?php

namespace App\Filament\Resources;

use App\Experience\BlockRegistry;
use App\Filament\Resources\PageResource\Pages;
use App\Models\Page;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Experience';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Page')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (string $state, Forms\Set $set, string $operation) => $operation === 'create'
                                ? $set('slug', str($state)->slug())
                                : null),
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->helperText('URL path: /p/{slug}'),
                        Forms\Components\Select::make('game_id')
                            ->label('Scope to a game')
                            ->relationship('game', 'name')
                            ->helperText('Optional — leave empty for a global/platform page.'),
                        Forms\Components\Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'published' => 'Published',
                            ])
                            ->required()
                            ->default('draft'),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Blocks')
                    ->description('Blocks render in order, top to bottom, on the public page.')
                    ->schema([
                        Forms\Components\Repeater::make('blocks')
                            ->label('')
                            ->schema([
                                Forms\Components\Select::make('type')
                                    ->label('Block type')
                                    ->options(fn () => app(BlockRegistry::class)->options())
                                    ->required()
                                    ->live(),
                                Forms\Components\Group::make(
                                    fn (Get $get) => self::blockConfigSchema($get('type'))
                                )
                                    ->statePath('config'),
                            ])
                            ->itemLabel(fn (array $state) => $state['type'] ?? 'Block')
                            ->collapsible()
                            ->default([])
                            ->addActionLabel('Add block'),
                    ]),
            ]);
    }

    protected static function blockConfigSchema(?string $type): array
    {
        if (! $type) {
            return [];
        }

        $class = app(BlockRegistry::class)->get($type);

        return $class ? $class::configSchema() : [];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable(),
                Tables\Columns\TextColumn::make('slug')
                    ->searchable(),
                Tables\Columns\TextColumn::make('game.name')
                    ->label('Game')
                    ->placeholder('— global —'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'published' ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
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
            'index' => Pages\ListPages::route('/'),
            'create' => Pages\CreatePage::route('/create'),
            'edit' => Pages\EditPage::route('/{record}/edit'),
        ];
    }
}
