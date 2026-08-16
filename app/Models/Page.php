<?php

namespace App\Models;

use GamingHub\Core\Models\Game;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Web Tree: a self-referencing hierarchy of folders and pages.
 * type='folder' rows exist purely to organize — they're never rendered on
 * their own, only walked to resolve a path (see routes/web.php). Soft
 * deletes so "Delete" in the admin is recoverable, not destructive.
 */
class Page extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'parent_id',
        'title',
        'slug',
        'type',
        'game_id',
        'status',
        'order',
        'content',
    ];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Page::class, 'parent_id')->orderBy('order');
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function isFolder(): bool
    {
        return $this->type === 'folder';
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /**
     * Every ancestor's slug, root first, including this page's own slug —
     * "/games/ark/ragnarok" for a page 3 levels deep. Walks parent_id live
     * rather than reading a stored path column, so a rename or move can
     * never leave a stale path behind (see the hierarchy migration's
     * docblock for why no path column exists).
     *
     * @return list<string>
     */
    public function pathSegments(): array
    {
        $segments = [$this->slug];
        $node = $this;

        while ($node->parent) {
            $node = $node->parent;
            array_unshift($segments, $node->slug);
        }

        return $segments;
    }
}
