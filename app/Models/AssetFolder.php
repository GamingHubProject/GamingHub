<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetFolder extends Model
{
    /** @use HasFactory<\Database\Factories\AssetFolderFactory> */
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'visibility',
        'owner_id',
        'path',
        'created_by',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(AssetFolder::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(AssetFolder::class, 'parent_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'folder_id');
    }

    /**
     * "/parent-path/slug", or just "/slug" for a root folder — recomputed
     * from the parent rather than trusted from client input, since a rename
     * or move has to cascade to every descendant's stored path too (see
     * AssetFolderController::move/rename).
     */
    public static function buildPath(?self $parent, string $slug): string
    {
        return $parent ? rtrim($parent->path, '/').'/'.$slug : '/'.$slug;
    }

    /**
     * Rows a given user is allowed to see: public folders to everyone,
     * admin_only to Admins, user_private to its owner (or an Admin — see
     * scopeAssetVisibleTo's docblock for why moderation access is granted
     * here too).
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        $isAdmin = $user?->hasRole('Admin') ?? false;

        if ($isAdmin) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->where('visibility', 'public');

            if ($user) {
                $q->orWhere(function (Builder $q2) use ($user) {
                    $q2->where('visibility', 'user_private')->where('owner_id', $user->id);
                });
            }
        });
    }
}
