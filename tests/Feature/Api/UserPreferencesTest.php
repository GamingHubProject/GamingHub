<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_choose_where_their_account_controls_sit(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson('/api/v1/user/preferences', ['account_placement' => 'sidebar'])
            ->assertOk()
            ->assertJsonPath('data.preferences.account_placement', 'sidebar');

        $this->assertSame('sidebar', $user->refresh()->preferences['account_placement']);
    }

    public function test_null_clears_the_override_so_the_theme_decides_again(): void
    {
        $user = User::factory()->create(['preferences' => ['account_placement' => 'sidebar']]);

        $this->actingAs($user)
            ->patchJson('/api/v1/user/preferences', ['account_placement' => null])
            ->assertOk();

        $this->assertArrayNotHasKey('account_placement', $user->refresh()->preferences ?? []);
    }

    public function test_it_merges_rather_than_replacing_what_is_already_there(): void
    {
        // A client that knows about one preference must not wipe the ones
        // it has never heard of — a request that mentions no key at all
        // leaves every stored key standing.
        $user = User::factory()->create(['preferences' => ['account_placement' => 'sidebar']]);

        $this->actingAs($user)
            ->patchJson('/api/v1/user/preferences', [])
            ->assertOk();

        $this->assertSame('sidebar', $user->refresh()->preferences['account_placement']);
    }

    public function test_a_key_left_behind_from_outside_the_allowlist_is_dropped_on_the_next_write(): void
    {
        // Only reachable on a row written before the allowlist existed, or
        // through Filament's raw key/value editor, which this release
        // removed. The merge deliberately does not carry it forward: the
        // allowlist is a property of the column, not of one endpoint.
        $user = User::factory()->create(['preferences' => ['something_else' => 'kept']]);

        $this->actingAs($user)
            ->patchJson('/api/v1/user/preferences', ['account_placement' => 'header'])
            ->assertOk();

        $preferences = $user->refresh()->preferences;
        $this->assertArrayNotHasKey('something_else', $preferences);
        $this->assertSame('header', $preferences['account_placement']);
    }

    public function test_an_unknown_preference_key_is_ignored_not_stored(): void
    {
        // preferences is a free-form JSON column; without an allowlist this
        // endpoint would let any signed-in visitor store arbitrary data on
        // their own row.
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson('/api/v1/user/preferences', ['arbitrary_junk' => str_repeat('x', 100)])
            ->assertOk();

        $this->assertArrayNotHasKey('arbitrary_junk', $user->refresh()->preferences ?? []);
    }

    public function test_a_value_outside_the_allowed_set_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson('/api/v1/user/preferences', ['account_placement' => 'floating'])
            ->assertStatus(422);
    }

    public function test_a_signed_out_visitor_cannot_set_preferences(): void
    {
        $this->patchJson('/api/v1/user/preferences', ['account_placement' => 'sidebar'])
            ->assertStatus(401);
    }
}
