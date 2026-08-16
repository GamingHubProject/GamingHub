<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages;
use App\Models\ServerGroup;
use App\Permissions\ScopedPermissionName;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use GamingHub\Core\Models\Game;
use GamingHub\Core\Models\Server;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

/**
 * Every permission in the system is now one of the auto-generated
 * per-entity ones (see ScopedPermissionName/ScopedPermissionGenerator) —
 * there's no flat global permission list left to show, so the form is
 * just this tree: one collapsible Section per Game, nested ServerGroups,
 * nested Servers, a 4-option checkbox list (settings/page/news/player) at
 * each level. Persistence lives in CreateRole/EditRole since these
 * checkbox fields aren't real model attributes or relationships — Filament
 * can't `relationship()`-bind several separate CheckboxLists onto the same
 * permissions() pivot.
 */
class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('guard_name')
                    ->required()
                    ->maxLength(255)
                    ->default('web'),
                Forms\Components\Section::make('Scoped permissions')
                    ->description(
                        'Grant Settings/Pages/News/Player access per game, server group, or '
                        .'server. A grant at Game level covers everything under it — only check '
                        .'a lower level to narrow access to just that one group or server.'
                    )
                    ->schema(static::gameTreeSchema())
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('permissions_count')
                    ->counts('permissions')
                    ->label('Permissions'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
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
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<int, Forms\Components\Section>
     */
    protected static function gameTreeSchema(): array
    {
        return Game::query()->orderBy('name')->get()
            ->map(fn (Game $game) => Forms\Components\Section::make($game->name)
                ->collapsible()
                ->collapsed()
                ->schema([
                    static::entityCheckboxList('game', $game),
                    ...static::serverGroupTreeSchema($game),
                ]))
            ->all();
    }

    /**
     * @return array<int, Forms\Components\Section>
     */
    protected static function serverGroupTreeSchema(Game $game): array
    {
        return ServerGroup::query()->where('game_id', $game->id)->orderBy('name')->get()
            ->map(fn (ServerGroup $group) => Forms\Components\Section::make($group->name)
                ->collapsible()
                ->collapsed()
                ->schema([
                    static::entityCheckboxList('servergroup', $group),
                    ...static::serverTreeSchema($group),
                ]))
            ->all();
    }

    /**
     * @return array<int, Forms\Components\Section>
     */
    protected static function serverTreeSchema(ServerGroup $group): array
    {
        return Server::query()->where('server_group_id', $group->id)->orderBy('name')->get()
            ->map(fn (Server $server) => Forms\Components\Section::make($server->name)
                ->collapsible()
                ->collapsed()
                ->schema([
                    static::entityCheckboxList('server', $server),
                ]))
            ->all();
    }

    protected static function entityCheckboxList(string $entityType, Model $entity): Forms\Components\CheckboxList
    {
        $options = collect(ScopedPermissionName::TYPES)->mapWithKeys(
            fn (string $type) => [ScopedPermissionName::for($entityType, $entity->getKey(), $type) => static::typeLabel($type)]
        )->all();

        return Forms\Components\CheckboxList::make("scoped_{$entityType}_{$entity->getKey()}")
            ->label(false)
            ->options($options)
            ->default(fn (?Role $record) => $record
                ? collect($options)->keys()->filter(fn (string $name) => $record->hasPermissionTo($name))->values()->all()
                : [])
            ->columns(4);
    }

    protected static function typeLabel(string $type): string
    {
        return match ($type) {
            'settings' => 'Settings',
            'page' => 'Pages',
            'news' => 'News',
            'player' => 'Player management',
            default => ucfirst($type),
        };
    }

    /**
     * Every "scoped_{entityType}_{id}" field's selected values flattened
     * into one list of permission names — used by CreateRole/EditRole to
     * persist the tree's state.
     *
     * @return list<string>
     */
    public static function extractCheckedPermissionNames(array $data): array
    {
        $names = [];

        foreach ($data as $key => $value) {
            if (str_starts_with($key, 'scoped_') && is_array($value)) {
                $names = array_merge($names, $value);
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * Seeds every "scoped_{entityType}_{id}" field from $role's current
     * grants — for EditRole's mutateFormDataBeforeFill(). Each CheckboxList's
     * own default() closure only fires on a truly blank fill (see
     * HasState::fill()/hydrateDefaultState() — an edit form's fill($data)
     * bypasses it entirely), so seeding an edit form's virtual fields has to
     * happen here instead, the same way the old scoped_permissions repeater
     * needed its own mutateFormDataBeforeFill for the same reason.
     *
     * @return array<string, list<string>>
     */
    public static function scopedFieldDefaults(Role $role): array
    {
        $defaults = [];

        foreach (Game::query()->get() as $game) {
            $defaults["scoped_game_{$game->id}"] = static::grantedNames($role, 'game', $game->id);
        }

        foreach (ServerGroup::query()->get() as $group) {
            $defaults["scoped_servergroup_{$group->id}"] = static::grantedNames($role, 'servergroup', $group->id);
        }

        foreach (Server::query()->get() as $server) {
            $defaults["scoped_server_{$server->id}"] = static::grantedNames($role, 'server', $server->id);
        }

        return $defaults;
    }

    /**
     * @return list<string>
     */
    protected static function grantedNames(Role $role, string $entityType, int $id): array
    {
        return collect(ScopedPermissionName::allFor($entityType, $id))
            ->filter(fn (string $name) => $role->hasPermissionTo($name))
            ->values()
            ->all();
    }
}
