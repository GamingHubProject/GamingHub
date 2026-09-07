<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One number a feature records about a person. Never written directly —
 * every write goes through App\Profiles\UserStats, which owns the upsert
 * and the uniqueness semantics; see that class for why.
 */
class UserStat extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'source',
        'subject_type',
        'subject_id',
        'key',
        'value',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            // Deliberately not 'decimal:4' — that cast returns a string,
            // and every consumer of a stat wants to do arithmetic or
            // compare it. Float is the right shape for a value the
            // database already holds at fixed precision.
            'value' => 'float',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
