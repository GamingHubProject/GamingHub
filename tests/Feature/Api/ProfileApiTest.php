<?php

namespace Tests\Feature\Api;

use App\Models\Asset;
use App\Models\PageLayout;
use App\Models\User;
use App\Profiles\UserStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProfileApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        return $admin;
    }

    // --- Reading ---

    public function test_a_public_profile_is_readable_by_anyone_signed_in_or_not(): void
    {
        $user = User::factory()->create(['display_name' => 'Rose', 'bio' => '<p>Hi</p>']);

        $response = $this->getJson("/api/v1/users/{$user->id}/profile");

        $response->assertOk();
        $response->assertJsonPath('data.display_name', 'Rose');
        $response->assertJsonPath('data.bio', '<p>Hi</p>');
        $response->assertJsonPath('data.can_edit', false);
    }

    public function test_a_profile_without_a_display_name_falls_back_to_the_account_name(): void
    {
        $user = User::factory()->create(['name' => 'Alex Doe', 'display_name' => null]);

        $this->getJson("/api/v1/users/{$user->id}/profile")
            ->assertOk()
            ->assertJsonPath('data.display_name', 'Alex Doe');
    }

    /**
     * The payload is served to anonymous visitors, so it must never carry
     * what only the account holder should see.
     */
    public function test_a_profile_never_exposes_the_email_or_roles(): void
    {
        $admin = $this->admin();

        $response = $this->getJson("/api/v1/users/{$admin->id}/profile");

        $response->assertOk();
        $response->assertJsonMissingPath('data.email');
        $response->assertJsonMissingPath('data.is_admin');
        $this->assertStringNotContainsString($admin->email, $response->getContent());
    }

    public function test_the_at_name_door_resolves_to_the_same_profile(): void
    {
        $user = User::factory()->create(['display_name' => 'Rose']);

        $byId = $this->getJson("/api/v1/users/{$user->id}/profile")->json('data');
        $byName = $this->getJson('/api/v1/profiles/by-name/Rose')->json('data');

        $this->assertSame($byId, $byName);
    }

    public function test_the_at_name_door_ignores_case_the_way_the_uniqueness_index_does(): void
    {
        User::factory()->create(['display_name' => 'Rose']);

        $this->getJson('/api/v1/profiles/by-name/rose')->assertOk();
        $this->getJson('/api/v1/profiles/by-name/ROSE')->assertOk();
        $this->getJson('/api/v1/profiles/by-name/nobody')->assertNotFound();
    }

    public function test_a_private_profile_answers_403_to_a_stranger(): void
    {
        $user = User::factory()->create(['profile_public' => false]);

        $this->getJson("/api/v1/users/{$user->id}/profile")->assertForbidden();
        $this->actingAs(User::factory()->create())
            ->getJson("/api/v1/users/{$user->id}/profile")
            ->assertForbidden();
    }

    public function test_a_private_profile_is_still_readable_by_its_owner_and_by_admins(): void
    {
        $user = User::factory()->create(['profile_public' => false]);

        $this->actingAs($user)->getJson("/api/v1/users/{$user->id}/profile")->assertOk();
        $this->actingAs($this->admin())->getJson("/api/v1/users/{$user->id}/profile")->assertOk();
    }

    public function test_a_profile_carries_the_stats_recorded_against_it(): void
    {
        $user = User::factory()->create();
        UserStats::set($user, 'pelican', 'hours', 47, subjectType: 'server', subjectId: 5);

        $response = $this->getJson("/api/v1/users/{$user->id}/profile");

        $response->assertOk();
        $response->assertJsonPath('data.stats.0.key', 'hours');
        $response->assertJsonPath('data.stats.0.value', 47);
        $response->assertJsonPath('data.stats.0.subject_id', 5);
    }

    public function test_a_profile_carries_the_admins_widget_allowlist(): void
    {
        $user = User::factory()->create();

        $this->getJson("/api/v1/users/{$user->id}/profile")
            ->assertOk()
            ->assertJsonPath('data.allowed_widget_types', ['text', 'profile-avatar', 'profile-bio', 'profile-stats']);
    }

    public function test_can_edit_is_true_for_the_owner_and_for_an_admin(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson("/api/v1/users/{$user->id}/profile")->assertJsonPath('data.can_edit', true);
        $this->actingAs($this->admin())->getJson("/api/v1/users/{$user->id}/profile")->assertJsonPath('data.can_edit', true);
        $this->actingAs(User::factory()->create())->getJson("/api/v1/users/{$user->id}/profile")->assertJsonPath('data.can_edit', false);
    }

    // --- The profile's layout ---

    public function test_the_profile_layout_is_created_lazily_and_seeded_with_an_avatar_and_a_bio(): void
    {
        $user = User::factory()->create();

        $response = $this->getJson("/api/v1/users/{$user->id}/layout");

        $response->assertOk();
        $response->assertJsonPath('data.subject_type', 'user_profile');
        $response->assertJsonPath('data.subject_id', $user->id);
        $response->assertJsonPath('data.widgets.0.widget_type', 'profile-avatar');
        $response->assertJsonPath('data.widgets.1.widget_type', 'profile-bio');
        // Seeded side by side rather than stacked at the same spot.
        $response->assertJsonPath('data.widgets.0.position_x', 0);
        $response->assertJsonPath('data.widgets.1.position_x', 3);
    }

    public function test_a_private_profiles_layout_is_not_served_to_a_stranger(): void
    {
        $user = User::factory()->create(['profile_public' => false]);

        $this->getJson("/api/v1/users/{$user->id}/layout")->assertForbidden();
        // And nothing was created on the way to refusing — a closed
        // profile leaves no row for anyone to count.
        $this->assertDatabaseCount('page_layouts', 0);
    }

    public function test_the_owner_can_edit_their_own_profile_layout(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->getJson("/api/v1/users/{$user->id}/layout")->assertOk();
        $layout = PageLayout::where('subject_type', 'user_profile')->where('subject_id', $user->id)->firstOrFail();

        $this->actingAs($user)
            ->postJson("/api/v1/page-layouts/{$layout->id}/widgets", ['widget_type' => 'text'])
            ->assertCreated();
    }

    // --- Writing ---

    public function test_a_person_can_set_their_own_display_name_bio_and_visibility(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patchJson('/api/v1/user/profile', [
            'display_name' => 'Rose',
            'bio' => '<p>Hello <strong>there</strong></p>',
            'profile_public' => false,
        ]);

        $response->assertOk();
        $user->refresh();
        $this->assertSame('Rose', $user->display_name);
        $this->assertSame('<p>Hello <strong>there</strong></p>', $user->bio);
        $this->assertFalse($user->profile_public);
    }

    public function test_a_bio_is_sanitised_on_the_way_in(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson('/api/v1/user/profile', ['bio' => '<p>Hi</p><script>alert(1)</script><img src=x onerror=y>'])
            ->assertOk();

        $this->assertSame('<p>Hi</p>', $user->refresh()->bio);
    }

    public function test_a_display_name_cannot_be_taken_twice_even_in_a_different_case(): void
    {
        User::factory()->create(['display_name' => 'Rose']);
        $other = User::factory()->create();

        $this->actingAs($other)->patchJson('/api/v1/user/profile', ['display_name' => 'Rose'])->assertStatus(422);
        $this->actingAs($other)->patchJson('/api/v1/user/profile', ['display_name' => 'rose'])->assertStatus(422);
        $this->assertNull($other->refresh()->display_name);
    }

    public function test_keeping_your_own_display_name_is_not_a_collision_with_yourself(): void
    {
        $user = User::factory()->create(['display_name' => 'Rose']);

        $this->actingAs($user)
            ->patchJson('/api/v1/user/profile', ['display_name' => 'Rose', 'profile_public' => true])
            ->assertOk();
    }

    /**
     * Uniqueness stops exact duplicates; this is the other half — a public
     * name sits next to other people's names, so it may not carry markup,
     * punctuation tricks or a single ambiguous character.
     */
    public function test_a_display_name_is_held_to_a_character_allowlist(): void
    {
        $user = User::factory()->create();

        foreach (['<b>Rose</b>', 'Ro$e', 'R', 'Rose!', 'Rose|Bud'] as $bad) {
            $this->actingAs($user)
                ->patchJson('/api/v1/user/profile', ['display_name' => $bad])
                ->assertStatus(422);
        }

        $this->assertNull($user->refresh()->display_name);
    }

    /**
     * Surrounding whitespace is trimmed rather than refused — Laravel's
     * TrimStrings middleware gets there first, and " Rose" is a typo, not
     * an attack. Worth pinning because that middleware also strips the
     * invisible characters an impersonator would reach for (a zero-width
     * space is how you get a second "Rose"): the framework removes them
     * before the allowlist ever sees them, so the stored name has no edges
     * to hide behind either way.
     */
    public function test_surrounding_whitespace_and_invisible_characters_are_trimmed_away(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patchJson('/api/v1/user/profile', ['display_name' => '  Rose  '])->assertOk();
        $this->assertSame('Rose', $user->refresh()->display_name);

        $this->actingAs($user)->patchJson('/api/v1/user/profile', ['display_name' => "Rose\u{200B}"])->assertOk();
        $this->assertSame('Rose', $user->refresh()->display_name);
    }

    public function test_clearing_a_display_name_frees_it_for_somebody_else(): void
    {
        $user = User::factory()->create(['display_name' => 'Rose']);
        $other = User::factory()->create();

        $this->actingAs($user)->patchJson('/api/v1/user/profile', ['display_name' => null])->assertOk();
        $this->actingAs($other)->patchJson('/api/v1/user/profile', ['display_name' => 'Rose'])->assertOk();
    }

    public function test_an_avatar_has_to_be_an_image(): void
    {
        $user = User::factory()->create();
        $font = Asset::factory()->create(['mime_type' => 'font/woff2']);

        $this->actingAs($user)
            ->patchJson('/api/v1/user/profile', ['avatar_asset_id' => $font->id])
            ->assertStatus(422);
    }

    public function test_updating_a_profile_requires_being_signed_in(): void
    {
        $this->patchJson('/api/v1/user/profile', ['display_name' => 'Rose'])->assertUnauthorized();
    }

    /**
     * There is no "edit anyone" endpoint — the only row this route can
     * touch is the requester's own, which is what keeps it safe to leave
     * ungated beyond authentication.
     */
    public function test_the_endpoint_can_only_ever_write_the_requesters_own_row(): void
    {
        $victim = User::factory()->create(['display_name' => 'Rose']);
        $attacker = User::factory()->create();

        $this->actingAs($attacker)
            ->patchJson('/api/v1/user/profile', ['id' => $victim->id, 'user_id' => $victim->id, 'display_name' => 'Taken'])
            ->assertOk();

        $this->assertSame('Rose', $victim->refresh()->display_name);
        $this->assertSame('Taken', $attacker->refresh()->display_name);
    }
}
