<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Asset extends Model
{
    /** @use HasFactory<\Database\Factories\AssetFactory> */
    use HasFactory;

    protected $fillable = [
        'owner_type',
        'owner_id',
        'folder_id',
        'disk_path',
        'url',
        'mime_type',
        'size',
        'width',
        'height',
        'alt_text',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(AssetFolder::class, 'folder_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(AssetTag::class, 'asset_asset_tag');
    }

    /**
     * An asset's effective visibility is entirely its folder's — a null
     * folder_id (unfiled, the only state Phase 1 assets can be in) is
     * public, same as today. There's no visibility column on Asset itself:
     * duplicating it here would let an asset drift out of sync with the
     * folder that's supposed to be its single source of truth.
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        $isAdmin = $user?->hasRole('Admin') ?? false;

        if ($isAdmin) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->whereNull('folder_id')
                ->orWhereHas('folder', function (Builder $folderQuery) use ($user) {
                    $folderQuery->visibleTo($user);
                });
        });
    }

    /**
     * Deterministic from disk_path — no separate DB column, since a
     * thumbnail is always exactly "the same path with -thumb before the
     * extension" (see AssetThumbnailer). SVG has no separate thumbnail
     * file at all (see hasThumbnail()); its own url doubles as both.
     */
    public function thumbnailPath(): string
    {
        return static::thumbnailPathFor($this->disk_path);
    }

    public static function thumbnailPathFor(string $diskPath): string
    {
        return preg_replace('/\.([^.\/]+)$/', '-thumb.$1', $diskPath);
    }

    public function hasThumbnail(): bool
    {
        return ! static::isNonRasterMime($this->mime_type);
    }

    /**
     * True for a mime that has no fixed pixel dimensions and nothing
     * visual to thumbnail — SVG (vector) and, since the Theme font system,
     * woff/woff2 (fonts). Shared between here and AssetController::
     * dimensions()/store() so the "no getimagesize(), no thumbnail" branch
     * only has one definition of what counts as non-raster.
     *
     * Deliberately doesn't include 'application/octet-stream' — some
     * servers' fileinfo databases do report that for a .woff2 file, but
     * it's also the generic fallback for arbitrary binary data; treating
     * it as "definitely a font" here would be wrong for anything else that
     * mimetype could mean. If that turns out to be a real problem on a
     * given server, the fix belongs in AssetController (checking the
     * upload's extension too), not by widening this to a mime that isn't
     * actually specific to fonts.
     */
    public static function isNonRasterMime(string $mimeType): bool
    {
        return in_array($mimeType, [
            'image/svg+xml',
            'font/woff',
            'font/woff2',
            'application/font-woff',
            'application/font-woff2',
        ], true);
    }
}
