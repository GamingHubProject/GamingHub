<?php

namespace App\Permissions;

use App\Models\ServerGroup;
use App\Models\User;
use GamingHub\Core\Models\Game;
use GamingHub\Core\Models\Server;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Cascading access check for the auto-generated per-entity permissions
 * (see ScopedPermissionName/ScopedPermissionGenerator). A grant at Game
 * level covers everything under it — ServerGroups and Servers belonging
 * to that game — so checking a Server walks server -> server group ->
 * game and stops at the first match; an explicit lower-level grant only
 * matters for narrowing to just that one group/server.
 *
 * Admin is short-circuited here directly because Spatie's
 * hasPermissionTo() does not consult Laravel's Gate — AppServiceProvider's
 * Gate::before() only covers Gate/can()/@can/policy checks, not this.
 *
 * There is no more "zero rows = unrestricted" case (that was
 * permission_scopes' rule, now gone). A role either holds one of these
 * permissions or it doesn't — visibleIds() always returns a concrete,
 * possibly-empty list, never null.
 */
class ScopedPermissionChecker
{
    public function can(User $user, string $type, Model $subject): bool
    {
        if ($user->hasRole('Admin')) {
            return true;
        }

        foreach ($this->chainNames($subject, $type) as $name) {
            if ($user->hasPermissionTo($name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Which ids at $level (game|servergroup|server) $user may act on for
     * $type — for filtering a list query without calling can() per row
     * against a hydrated model. Always a concrete list; a global/platform
     * subject (e.g. a Page with game_id null) has no entity to grant
     * against and is never included by this.
     *
     * @return list<int>
     */
    public function visibleIds(User $user, string $type, string $level): array
    {
        $model = match ($level) {
            'game' => Game::class,
            'servergroup' => ServerGroup::class,
            'server' => Server::class,
            default => throw new InvalidArgumentException("Unknown scope level: {$level}"),
        };

        return $model::query()
            ->get()
            ->filter(fn (Model $entity) => $this->can($user, $type, $entity))
            ->map(fn (Model $entity) => (int) $entity->getKey())
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    protected function chainNames(Model $subject, string $type): array
    {
        return match (true) {
            $subject instanceof Server => [
                ScopedPermissionName::for('server', $subject->id, $type),
                ScopedPermissionName::for('servergroup', $subject->server_group_id, $type),
                ScopedPermissionName::for('game', $subject->game_id, $type),
            ],
            $subject instanceof ServerGroup => [
                ScopedPermissionName::for('servergroup', $subject->id, $type),
                ScopedPermissionName::for('game', $subject->game_id, $type),
            ],
            $subject instanceof Game => [
                ScopedPermissionName::for('game', $subject->id, $type),
            ],
            default => throw new InvalidArgumentException('Unsupported scoped-permission subject: '.get_class($subject)),
        };
    }
}
