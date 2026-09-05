<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * One entry in the public site's navigation. See the migration for why
 * this is separate from NavigationItem (which is the Filament admin
 * panel's own sidebar, not this).
 */
class NavigationLink extends Model
{
    public const TYPE_PAGE = 'page';
    public const TYPE_LINK = 'link';
    public const TYPE_FOLDER = 'folder';

    /**
     * Folders hold links; folders do not hold folders. Both surfaces
     * render one level naturally (a dropdown, a sidebar section) and
     * deeper navigation is a usability problem rather than a feature.
     * Lifting this later needs no migration — an adjacency list doesn't
     * care — only the editor's drop rules and the renderers change.
     */
    public const MAX_DEPTH = 2;

    protected $fillable = [
        'parent_id', 'position', 'type', 'label',
        'target_type', 'target_id', 'url', 'icon_asset_id', 'is_visible',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_visible' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }

    public function icon(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'icon_asset_id');
    }

    /**
     * Every link, ordered, in one query — the tree is assembled in memory
     * by the caller. At tens of rows that is cheaper than any recursive
     * query, and it means a page load costs exactly one hit regardless of
     * how deep the nesting goes.
     *
     * @return Collection<int, self>
     */
    public static function ordered(): Collection
    {
        return static::query()->with('icon')->orderBy('position')->orderBy('id')->get();
    }

    /**
     * Rewrite the whole tree in one transaction.
     *
     * Whole-tree rather than per-item because that's what a drag produces:
     * moving one link renumbers its old siblings and its new ones, so
     * "save what changed" would mean the client computing a diff it has no
     * reason to be trusted with. Anything the payload omits is deleted,
     * which is what makes a removal in the editor stick.
     *
     * @param  list<array{id?: int|null, ...}>  $tree  Nested; children under 'children'.
     * @return list<int> the ids that survived, so a caller can report on them
     */
    public static function replaceTree(array $tree): array
    {
        return DB::transaction(function () use ($tree) {
            $kept = [];
            self::writeLevel($tree, null, 1, $kept);

            // Deleting last means a link that merely *moved* is never
            // briefly absent, and cascade never fires on a parent that is
            // about to be re-parented rather than removed.
            static::query()->whereNotIn('id', $kept ?: [0])->delete();

            return $kept;
        });
    }

    /** @param  list<int>  $kept */
    private static function writeLevel(array $nodes, ?int $parentId, int $depth, array &$kept): void
    {
        foreach (array_values($nodes) as $position => $node) {
            $attributes = [
                'parent_id' => $parentId,
                'position' => $position,
                'type' => $node['type'],
                'label' => $node['label'],
                'target_type' => $node['target_type'] ?? null,
                'target_id' => $node['target_id'] ?? null,
                'url' => $node['url'] ?? null,
                'icon_asset_id' => $node['icon_asset_id'] ?? null,
                'is_visible' => $node['is_visible'] ?? true,
            ];

            $link = isset($node['id']) && ($existing = static::find($node['id']))
                ? tap($existing)->update($attributes)
                : static::create($attributes);

            $kept[] = $link->id;

            // Silently flattened rather than rejected: a client that sends
            // something too deep gets a valid tree back, not a 422 in the
            // middle of a drag. The editor's own drop rules are the real
            // guard; this is the backstop.
            if ($depth < self::MAX_DEPTH && ! empty($node['children'])) {
                self::writeLevel($node['children'], $link->id, $depth + 1, $kept);
            }
        }
    }
}
