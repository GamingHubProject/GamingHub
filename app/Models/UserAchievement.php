<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Something a person has earned. Never written directly — see
 * App\Profiles\UserAchievements.
 */
class UserAchievement extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'source',
        'achievement_key',
        'earned_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'earned_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
