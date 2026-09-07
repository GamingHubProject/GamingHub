<?php

namespace Tests\Feature\Profiles;

use App\Events\UserStatRecorded;
use App\Models\User;
use App\Profiles\UserStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Tests\TestCase;

class UserStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_set_records_a_site_wide_stat(): void
    {
        $user = User::factory()->create();

        $stat = UserStats::set($user, 'core', 'sessions', 12);

        $this->assertSame(12.0, $stat->value);
        $this->assertDatabaseHas('user_stats', ['user_id' => $user->id, 'source' => 'core', 'key' => 'sessions', 'subject_type' => null]);
    }

    /**
     * The whole reason set() exists next to increment(): a poller reporting
     * a cumulative total must be able to repeat itself without the number
     * growing.
     */
    public function test_set_is_idempotent_where_increment_is_not(): void
    {
        $user = User::factory()->create();

        UserStats::set($user, 'pelican', 'hours', 47);
        UserStats::set($user, 'pelican', 'hours', 47);

        $this->assertSame(47.0, UserStats::get($user, 'pelican', 'hours')->value);
        $this->assertDatabaseCount('user_stats', 1);

        UserStats::increment($user, 'pelican', 'hours', 2);
        UserStats::increment($user, 'pelican', 'hours', 2);

        $this->assertSame(51.0, UserStats::get($user, 'pelican', 'hours')->value);
        $this->assertDatabaseCount('user_stats', 1);
    }

    public function test_increment_starts_from_zero_when_nothing_is_there_yet(): void
    {
        $user = User::factory()->create();

        $stat = UserStats::increment($user, 'core', 'kills', 3);

        $this->assertSame(3.0, $stat->value);
    }

    /**
     * The departure from the original sketch (`hours_on_server_5: 47`):
     * the subject is columns, so the same key on two servers is two rows
     * that can be summed and ranked in the database.
     */
    public function test_the_same_key_on_different_subjects_is_different_stats(): void
    {
        $user = User::factory()->create();

        UserStats::set($user, 'pelican', 'hours', 10, subjectType: 'server', subjectId: 5);
        UserStats::set($user, 'pelican', 'hours', 4, subjectType: 'server', subjectId: 9);
        UserStats::set($user, 'pelican', 'hours', 99);

        $this->assertDatabaseCount('user_stats', 3);
        $this->assertSame(10.0, UserStats::get($user, 'pelican', 'hours', 'server', 5)->value);
        $this->assertSame(4.0, UserStats::get($user, 'pelican', 'hours', 'server', 9)->value);
        $this->assertSame(99.0, UserStats::get($user, 'pelican', 'hours')->value);
    }

    public function test_the_same_key_from_different_sources_never_overwrites(): void
    {
        $user = User::factory()->create();

        UserStats::set($user, 'pelican', 'hours', 10);
        UserStats::set($user, 'games.rust', 'hours', 200);

        $this->assertSame(10.0, UserStats::get($user, 'pelican', 'hours')->value);
        $this->assertSame(200.0, UserStats::get($user, 'games.rust', 'hours')->value);
    }

    public function test_two_people_keep_their_own_numbers(): void
    {
        $one = User::factory()->create();
        $two = User::factory()->create();

        UserStats::increment($one, 'core', 'sessions', 5);
        UserStats::increment($two, 'core', 'sessions', 1);

        $this->assertSame(5.0, UserStats::get($one, 'core', 'sessions')->value);
        $this->assertSame(1.0, UserStats::get($two, 'core', 'sessions')->value);
    }

    public function test_a_write_dispatches_an_event_carrying_the_delta(): void
    {
        Event::fake();
        $user = User::factory()->create();

        UserStats::set($user, 'pelican', 'hours', 10);
        UserStats::increment($user, 'pelican', 'hours', 2.5);

        Event::assertDispatchedTimes(UserStatRecorded::class, 2);
        Event::assertDispatched(UserStatRecorded::class, fn (UserStatRecorded $event) => $event->change === 10.0);
        Event::assertDispatched(UserStatRecorded::class, fn (UserStatRecorded $event) => $event->change === 2.5);
    }

    public function test_metadata_is_only_replaced_when_the_caller_sends_some(): void
    {
        $user = User::factory()->create();

        UserStats::set($user, 'core', 'sessions', 1, metadata: ['last_seen' => 'yesterday']);
        UserStats::increment($user, 'core', 'sessions', 1);

        $this->assertSame(['last_seen' => 'yesterday'], UserStats::get($user, 'core', 'sessions')->metadata);
    }

    public function test_a_stat_survives_fractional_values(): void
    {
        $user = User::factory()->create();

        UserStats::increment($user, 'pelican', 'hours', 0.25);
        UserStats::increment($user, 'pelican', 'hours', 0.5);

        $this->assertSame(0.75, UserStats::get($user, 'pelican', 'hours')->value);
    }

    public function test_a_malformed_key_is_refused_rather_than_stored(): void
    {
        $user = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        UserStats::set($user, 'core', 'Hours On Server 5', 1);
    }

    public function test_a_half_specified_subject_is_refused(): void
    {
        $user = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        UserStats::set($user, 'core', 'hours', 1, subjectType: 'server');
    }

    public function test_stats_go_when_the_person_does(): void
    {
        $user = User::factory()->create();
        UserStats::set($user, 'core', 'sessions', 1);

        $user->delete();

        $this->assertDatabaseCount('user_stats', 0);
    }
}
