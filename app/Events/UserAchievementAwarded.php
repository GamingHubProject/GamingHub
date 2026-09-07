<?php

namespace App\Events;

use App\Models\UserAchievement;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired only when an achievement is genuinely new to this person —
 * award() is idempotent, and re-awarding something they already hold
 * fires nothing. A listener can therefore treat this as "congratulate
 * them" without checking first.
 */
class UserAchievementAwarded
{
    use Dispatchable;

    public function __construct(
        public UserAchievement $achievement,
    ) {}
}
