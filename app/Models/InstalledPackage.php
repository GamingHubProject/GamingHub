<?php

namespace App\Models;

use GamingHub\Core\Models\Game;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bookkeeping for what Manager has installed — a Hub Extension (game_id
 * null) or a Game Integration (game_id set). This is state, not behavior:
 * it records what's on disk and enabled, but doesn't load any package code
 * at runtime — that's a separate, not-yet-built problem (package loading).
 */
class InstalledPackage extends Model
{
    /** Loadable code, unpacked into storage/app/packages. */
    public const KIND_EXTENSION = 'extension';

    /** A theme, unpacked onto the assets disk — see App\Manager\ThemeInstaller. */
    public const KIND_THEME = 'theme';

    /**
     * What a theme package's row carries instead of enabled/disabled.
     * A theme has no on/off state: it is applied through a ThemeAssignment
     * or it isn't, and that lives on the theme, not here.
     */
    public const STATUS_INSTALLED = 'installed';

    /** @use HasFactory<\Database\Factories\InstalledPackageFactory> */
    use HasFactory;

    protected $fillable = [
        'game_id',
        'slug',
        'kind',
        'name',
        'version',
        'status',
        'description',
        'manifest',
        'installed_at',
    ];

    protected function casts(): array
    {
        return [
            'manifest' => 'array',
            'installed_at' => 'datetime',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
