<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One row per (subject_type, subject_id) — 'server'/&lt;server id&gt;,
 * 'game'/&lt;game id&gt;, or a singleton page keyed by SINGLETON_SUBJECT_ID
 * (0, a sentinel — never a real model id, see the migration that
 * introduced subject_type/subject_id for why NULL doesn't work here):
 * 'home' for the main Portal page, 'games-list' for /games. Not
 * user-owned like DashboardPage: read access is public (matches the
 * subject page itself); write access is Admin-role gated, not identity
 * gated. See the PageLayout* controllers.
 *
 * Deliberately has no morphTo relation to the subject — the singleton
 * pages have no backing model, so a uniform polymorphic resolver doesn't
 * fit every subject type. Each read endpoint already has its own real
 * subject via route-model binding (a Server, a Game, or nothing) and
 * resolves its PageLayout row directly; this model only ever needs
 * subject_type to decide *how* a caller looks it up, not to resolve it
 * generically.
 */
class PageLayout extends Model
{
    public const SINGLETON_SUBJECT_ID = 0;

    protected $fillable = [
        'subject_type',
        'subject_id',
        'font_asset_id',
    ];

    public function widgets(): HasMany
    {
        return $this->hasMany(PageLayoutWidget::class);
    }

    public function font(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'font_asset_id');
    }
}
