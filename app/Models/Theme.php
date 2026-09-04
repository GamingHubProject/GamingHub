<?php

namespace App\Models;

use App\Experience\ThemeBundle;
use App\Experience\ThemeStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An index over /themes/{slug}/ — see App\Experience\ThemeStorage, which
 * owns the folder that is the actual source of truth. This row exists so
 * that reading a theme (every page load, via /api/v1/theme) is one query
 * rather than a disk read and a JSON parse, and so themes can be listed,
 * searched and assigned with ordinary Eloquent.
 *
 * `payload` is the cached, URL-resolved bundle; `checksum` is how a folder
 * edited behind the app's back gets detected. Neither is authoritative —
 * `themes:sync` rebuilds both from disk at any time.
 *
 * Scope lives in ThemeAssignment now, not here: a theme has to be
 * portable, and a row that knows which game it was installed against
 * isn't.
 */
class Theme extends Model
{
    protected $fillable = ['name', 'slug', 'folder_id', 'payload', 'checksum', 'synced_at', 'is_builtin'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'synced_at' => 'datetime',
            'is_builtin' => 'boolean',
        ];
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(AssetFolder::class, 'folder_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ThemeAssignment::class);
    }

    /** The editable bundle, read from the folder (not from `payload`). */
    public function bundle(): ThemeBundle
    {
        return app(ThemeStorage::class)->readBundle($this->slug)
            ?? new ThemeBundle(id: $this->slug, name: $this->name);
    }

    /** True when the folder's theme.json no longer matches what was last synced. */
    public function isStale(): bool
    {
        $path = app(ThemeStorage::class)->themePath($this->slug, 'theme.json');
        $disk = \Illuminate\Support\Facades\Storage::disk(config('assets.disk'));

        return $disk->exists($path) && md5((string) $disk->get($path)) !== $this->checksum;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
