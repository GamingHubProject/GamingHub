<?php

namespace App\Permissions;

/**
 * The single source of truth for how a scoped permission's Spatie `name`
 * is built. Used by the generator (creates them), the checker (looks them
 * up), and RoleResource's tree UI (renders/persists them) — all three must
 * agree on the exact string or grants silently stop matching.
 *
 * The id is baked into the name rather than a slug/display name because
 * Spatie's `name` is the permanent identifier role grants point at:
 * renaming a Game must never orphan every role scoped to it.
 *
 * 'player' deliberately has no '.scope' suffix — it's the literal type
 * list requested, kept as-is rather than normalized to '.scope' for all
 * four, since it's just a name string with no behavioral meaning either
 * way.
 */
class ScopedPermissionName
{
    public const TYPES = ['settings', 'page', 'news', 'player'];

    public static function for(string $entityType, int $id, string $type): string
    {
        $suffix = $type === 'player' ? 'player' : "{$type}.scope";

        return "{$entityType}:{$id}:{$suffix}";
    }

    /**
     * @return list<string>
     */
    public static function allFor(string $entityType, int $id): array
    {
        return array_map(
            fn (string $type) => self::for($entityType, $id, $type),
            self::TYPES,
        );
    }
}
