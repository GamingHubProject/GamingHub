<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PageResource\Pages;
use App\Models\Page;
use App\Permissions\ScopedPermissionChecker;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Web Tree admin. The list is intentionally flat (not a nested drag-drop
 * tree widget — no fancy UI yet, per the brief) with a computed "Path"
 * column showing each row's full breadcrumb, and a "Parent folder" filter
 * so an admin can narrow to one folder's contents to drag-reorder them
 * with Filament's ordinary reorderable() — dragging across two different
 * parents at once wouldn't mean anything anyway.
 */
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
                        Forms\Components\Select::make('type')
                            ->options([
                                'folder' => 'Folder',
                                'page' => 'Page',
                            ])
                            ->required()
                            ->default('page')
                            ->live(),
                        Forms\Components\Select::make('parent_id')
                            ->label('Parent folder')
                            ->options(fn () => Page::query()->where('type', 'folder')->pluck('title', 'id'))
                            ->helperText('Leave empty for the root level.'),
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
                            ->helperText('Must be unique among siblings in the same parent folder.'),
                        Forms\Components\Select::make('game_id')
                            ->label('Scope to a game')
                            ->relationship('game', 'name')
                            ->helperText('Optional — leave empty for a global/platform page. Also what game-scoped role permissions check against.'),
                        Forms\Components\Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'published' => 'Published',
                            ])
                            ->required()
                            ->default('draft'),
                        Forms\Components\TextInput::make('order')
                            ->numeric()
                            ->default(0)
                            ->required()
                            ->helperText('Lower sorts first among siblings.'),
                    ])
                    ->columns(2),
                Forms\Components\Textarea::make('content')
                    ->visible(fn (Get $get) => $get('type') === 'page')
                    ->rows(10)
                    ->columnSpanFull()
                    ->helperText('Plain text/HTML for now — placeholder frontend only renders this as-is.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => static::applyVisibilityScope($query))
            ->reorderable('order')
            ->columns([
                Tables\Columns\TextColumn::make('path')
                    ->label('Path')
                    ->state(fn (Page $record) => '/'.implode('/', $record->pathSegments())),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state) => $state === 'folder' ? 'gray' : 'info'),
                Tables\Columns\TextColumn::make('game.name')
                    ->label('Game')
                    ->placeholder('— global —'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'published' ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('order')
                    ->label('Order'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('parent_id')
                    ->label('Parent folder')
                    ->options(fn () => Page::query()->where('type', 'folder')->pluck('title', 'id')),
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

    /**
     * `page.scope` is now one grant covering both game-scoped visibility
     * and draft visibility together (see_drafts no longer exists as a
     * separate permission) — anyone with page.scope for a game sees that
     * game's pages, draft or published; anyone without it sees none of
     * that game's pages, published or not.
     *
     * A page with game_id null (platform-wide, no game) has no entity to
     * grant page.scope against, so it's Admin-only now rather than visible
     * to anyone with an unrestricted grant the way it worked before this
     * redesign.
     */
    protected static function applyVisibilityScope(Builder $query): Builder
    {
        $user = auth()->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasRole('Admin')) {
            return $query;
        }

        $visibleGameIds = app(ScopedPermissionChecker::class)->visibleIds($user, 'page', 'game');

        return $query->whereIn('game_id', $visibleGameIds);
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
