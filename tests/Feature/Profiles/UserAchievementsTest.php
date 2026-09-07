<?php

namespace Tests\Feature\Profiles;

use App\Events\UserAchievementAwarded;
use App\Models\User;
use App\Profiles\UserAchievements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Tests\TestCase;

class UserAchievementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_awarding_records_it(): void
    {
        $user = User::factory()->create();

        $awarded = UserAchievements::award($user, 'core', 'first.login');

        $this->assertNotNull($awarded->earned_at);
        $this->assertTrue(UserAchievements::holds($user, 'core', 'first.login'));
    }

    /**
     * The natural caller is a listener reacting to a stat, which fires
     * again on every tick — so awarding twice has to be a no-op rather
     * than a second row or a second announcement.
     */
    public function test_awarding_twice_changes_nothing_and_announces_once(): void
    {
        Event::fake();
        $user = User::factory()->create();

        $first = UserAchievements::award($user, 'core', 'hundred.hours', earnedAt: now()->subDays(3));
        $second = UserAchievements::award($user, 'core', 'hundred.hours');

        $this->assertSame($first->id, $second->id);
        $this->assertTrue($first->earned_at->equalTo($second->earned_at));
        $this->assertDatabaseCount('user_achievements', 1);
        Event::assertDispatchedTimes(UserAchievementAwarded::class, 1);
    }

    public function test_the_same_key_from_two_sources_is_two_achievements(): void
    {
        $user = User::factory()->create();

        UserAchievements::award($user, 'core', 'veteran');
        UserAchievements::award($user, 'games.rust', 'veteran');

        $this->assertDatabaseCount('user_achievements', 2);
    }

    public function test_earned_at_can_be_backfilled_to_when_it_actually_happened(): void
    {
        $user = User::factory()->create();
        $when = now()->subYear()->startOfDay();

        $awarded = UserAchievements::award($user, 'pelican', 'first.join', earnedAt: $when);

        $this->assertTrue($awarded->earned_at->equalTo($when));
    }

    public function test_revoking_removes_it_and_lets_it_be_earned_again(): void
    {
        Event::fake();
        $user = User::factory()->create();

        UserAchievements::award($user, 'core', 'veteran');
        UserAchievements::revoke($user, 'core', 'veteran');

        $this->assertFalse(UserAchievements::holds($user, 'core', 'veteran'));

        UserAchievements::award($user, 'core', 'veteran');

        Event::assertDispatchedTimes(UserAchievementAwarded::class, 2);
    }

    public function test_a_malformed_key_is_refused(): void
    {
        $user = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        UserAchievements::award($user, 'core', 'Hundred Hours!');
    }

    public function test_achievements_go_when_the_person_does(): void
    {
        $user = User::factory()->create();
        UserAchievements::award($user, 'core', 'veteran');

        $user->delete();

        $this->assertDatabaseCount('user_achievements', 0);
    }
}
