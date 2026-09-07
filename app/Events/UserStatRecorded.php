<?php

namespace App\Events;

use App\Models\UserStat;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after a stat has actually been written, with the row as it now
 * stands and the delta that produced it ($change is 0.0 for a set() that
 * didn't move the number).
 *
 * This is what makes "play 100 hours, earn a badge" composable: the
 * achievement side listens for a stat crossing a threshold, and neither
 * system has to know the other exists. Listeners are deliberately not the
 * write path — see App\Profiles\UserStats.
 */
class UserStatRecorded
{
    use Dispatchable;

    public function __construct(
        public UserStat $stat,
        public float $change,
    ) {}
}
