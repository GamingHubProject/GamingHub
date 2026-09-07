<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One row per (subject_type, subject_id) — 'server'/&lt;server id&gt;,
 * 'game'/&lt;game id&gt;, 'user_profile'/&lt;user id&gt;, or a singleton page
 * keyed by SINGLETON_SUBJECT_ID (0, a sentinel — never a real model id,
 * see the migration that introduced subject_type/subject_id for why NULL
 * doesn't work here): 'home' for the main Portal page, 'games-list' for
 * /games. Read access is public (matches the subject page itself); write
 * access goes through canBeEditedBy() below.
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

    /**
     * The one subject type whose layout its subject may edit. Every other
     * subject type is a site page an admin owns.
     */
    public const SUBJECT_USER_PROFILE = 'user_profile';

    protected $fillable = [
        'subject_type',
        'subject_id',
        'font_asset_id',
    ];

    /**
     * The single authority on who may write to a layout — replacing the
     * bare hasRole('Admin') that every write endpoint used to carry
     * inline, which was correct only while every layout was a site page.
     *
     * Deliberately shaped as "deny, then name the exceptions": a subject
     * type introduced later is admin-only until somebody opens it here on
     * purpose, which is the direction a mistake in this method should
     * fall. The alternative — an ownership column on the row — was
     * rejected because ownership is already fully determined by
     * (subject_type, subject_id): a column would be a second,
     * back-fillable copy of a fact the row already states, and the two
     * could disagree.
     *
     * subject_id is compared as an int because it arrives as a string
     * from some drivers; 'user_profile'/'7' and user 7 are the same
     * profile.
     */
    public function canBeEditedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->hasRole('Admin')) {
            return true;
        }

        return $this->subject_type === self::SUBJECT_USER_PROFILE
            && (int) $this->subject_id === (int) $user->id;
    }

    public function widgets(): HasMany
    {
        return $this->hasMany(PageLayoutWidget::class);
    }

    public function font(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'font_asset_id');
    }
}
