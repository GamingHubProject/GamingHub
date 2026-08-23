<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Asset extends Model
{
    /** @use HasFactory<\Database\Factories\AssetFactory> */
    use HasFactory;

    protected $fillable = [
        'owner_type',
        'owner_id',
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
        return $this->mime_type !== 'image/svg+xml';
    }
}
