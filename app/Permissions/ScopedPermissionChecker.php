<?php

namespace App\Permissions;

use App\Models\PermissionScope;
use App\Models\User;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

/**
 * Sits on top of Spatie's own hasPermissionTo() — that check still decides
 * whether a Role has a permission at all; this decides whether that grant
 * is global or narrowed to specific Games (see permission_scopes'
 * migration docblock for the "zero rows = unrestricted" rule).
 *
 * A Game-scoped grant never covers a subject with no game at all
 * (game_id === null, e.g. a platform-wide page) — only an unrestricted
 * grant does. This is deliberately the stricter reading: a role scoped to
 * "Palworld only" shouldn't incidentally reach platform-wide content.
 */
class ScopedPermissionChecker
{
    public function can(User $user, string $permission, ?int $gameId): bool
    {
        foreach ($user->roles as $role) {
            if (! $role->hasPermissionTo($permission)) {
                continue;
            }

            $scopes = $this->scopesFor($role, $permission);

            if ($scopes->isEmpty()) {
                return true;
            }

            if ($gameId !== null && $scopes->contains(fn (PermissionScope $scope) => $scope->scope_type === 'game' && (int) $scope->scope_id === $gameId
            )) {
                return true;
            }
        }

        return false;
    }

    /**
     * Which game ids $user may act on for $permission — for filtering a
     * list query without calling can() per row. Returns null for "no
     * restriction, every game (and global/no-game subjects) visible";
     * an array (possibly empty) means visibility is limited to exactly
     * those game ids, and global (game_id === null) subjects are excluded
     * — matching can()'s own rule above.
     *
     * @return list<int>|null
     */
    public function visibleGameIds(User $user, string $permission): ?array
    {
        $gameIds = [];

        foreach ($user->roles as $role) {
            if (! $role->hasPermissionTo($permission)) {
                continue;
            }

            $scopes = $this->scopesFor($role, $permission);

            if ($scopes->isEmpty()) {
                return null;
            }

            $gameIds = array_merge($gameIds, $scopes->pluck('scope_id')->map(fn ($id) => (int) $id)->all());
        }

        return array_values(array_unique($gameIds));
    }

    /**
     * @return Collection<int, PermissionScope>
     */
    protected function scopesFor(Role $role, string $permission): Collection
    {
        return PermissionScope::query()
            ->where('role_id', $role->id)
            ->where('permission', $permission)
            ->get();
    }
}
