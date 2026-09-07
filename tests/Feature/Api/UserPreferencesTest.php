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
        // it has never heard of.
        $user = User::factory()->create(['preferences' => ['something_else' => 'kept']]);

        $this->actingAs($user)
            ->patchJson('/api/v1/user/preferences', ['account_placement' => 'header'])
            ->assertOk();

        $preferences = $user->refresh()->preferences;
        $this->assertSame('kept', $preferences['something_else']);
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
