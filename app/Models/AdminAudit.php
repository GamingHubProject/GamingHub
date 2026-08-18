<?php

namespace App\Models;

use App\Services\GeoIpLookup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per admin action — immutable once written (no updated_at; rows
 * are never edited after creation). user_id is nullOnDelete so deleting
 * the acting user's account can never delete their own audit trail;
 * user_name/user_email are denormalized directly onto the row for the
 * same reason, so the human-readable record survives independent of
 * whether the users table still has that row.
 *
 * record() is the single write path — both AdminAuditObserver (generic
 * created/updated/deleted events) and the role-change hooks in
 * UserResource/RoleResource's Edit/Create pages (which can't go through a
 * generic Observer at all, see those classes' own docblocks) call this
 * rather than each duplicating the "capture user/ip/country" logic.
 */
class AdminAudit extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'user_name', 'user_email',
        'action', 'resource_type', 'resource_id',
        'changes', 'ip', 'country',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * No-ops (returns without writing) when there's no authenticated
     * user — this is what keeps the scheduler, seeders, and tests silent
     * without either needing to check auth() at every call site or
     * exclude those contexts some other way.
     */
    public static function record(string $action, string $resourceType, ?int $resourceId, array $changes): void
    {
        $user = auth()->user();

        if (! $user) {
            return;
        }

        $ip = request()?->ip();

        // auth()->user() can be a stale in-memory reference to a row
        // that's already gone by the time this writes — an admin
        // deleting their own account fires this from the User model's
        // own 'deleted' hook, after the SQL DELETE has already run, so
        // $user->id no longer exists to satisfy the FK. The denormalized
        // name/email still capture who did it either way.
        $userId = User::whereKey($user->id)->exists() ? $user->id : null;

        static::create([
            'user_id' => $userId,
            'user_name' => $user->name,
            'user_email' => $user->email,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'changes' => $changes,
            'ip' => $ip,
            'country' => $ip ? app(GeoIpLookup::class)->countryCode($ip) : null,
        ]);
    }
}
