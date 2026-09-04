<?php

namespace App\Models;

use GamingHub\Core\Models\Game;
use GamingHub\Core\Models\Server;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Which theme this site uses where — the half of the old Theme row that
 * was about *this install* rather than about the design itself.
 *
 * Splitting it out is what lets a theme be exported: a bundle that carried
 * a game_id would arrive on another site pointing at a game that doesn't
 * exist there. It also expresses something the old shape couldn't — the
 * same theme assigned to several games at once, rather than a duplicated
 * row per game that then has to be kept in sync by hand.
 */
class ThemeAssignment extends Model
{
    public const LEVEL_PLATFORM = 'platform';
    public const LEVEL_GAME = 'game';
    public const LEVEL_SERVER = 'server';

    protected $fillable = ['theme_id', 'level', 'game_id', 'server_id'];

    public function theme(): BelongsTo
    {
        return $this->belongsTo(Theme::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * Point a scope at a theme, replacing whatever was there. The
     * uniqueness the schema deliberately doesn't enforce (a partial unique
     * index isn't portable between the SQLite used in tests and the
     * Postgres used in production) is enforced here instead, so there's
     * exactly one place that decides what "one theme per scope" means.
     */
    public static function assign(string $level, int $themeId, ?int $gameId = null, ?int $serverId = null): self
    {
        return static::updateOrCreate(
            ['level' => $level, 'game_id' => $gameId, 'server_id' => $serverId],
            ['theme_id' => $themeId]
        );
    }
}
