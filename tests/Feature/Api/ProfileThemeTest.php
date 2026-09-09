<?php

namespace Tests\Feature\Api;

use App\Experience\ThemeResolver;
use App\Models\SiteOption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_set_their_profile_theme(): void
    {
        $user = User::factory()->create();

        $theme = ['accent' => '#ff0000', 'background' => '#111111'];

        $this->actingAs($user)
            ->patchJson('/api/v1/user/profile', ['profile_theme' => $theme])
            ->assertOk();

        $this->assertSame($theme, $user->refresh()->profile_theme);
    }

    public function test_unknown_tokens_are_stripped(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson('/api/v1/user/profile', [
                'profile_theme' => [
                    'accent' => '#ff0000',
                    'radius' => '#999999',
                    'evil' => '#000000',
                ],
            ])
            ->assertOk();

        $this->assertSame(['accent' => '#ff0000'], $user->refresh()->profile_theme);
    }

    public function test_invalid_colour_values_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson('/api/v1/user/profile', [
                'profile_theme' => ['accent' => 'not-a-colour'],
            ])
            ->assertUnprocessable();
    }

    public function test_clearing_profile_theme_sets_null(): void
    {
        $user = User::factory()->create(['profile_theme' => ['accent' => '#ff0000']]);

        $this->actingAs($user)
            ->patchJson('/api/v1/user/profile', ['profile_theme' => null])
            ->assertOk();

        $this->assertNull($user->refresh()->profile_theme);
    }

    public function test_empty_object_clears_to_null(): void
    {
        $user = User::factory()->create(['profile_theme' => ['accent' => '#ff0000']]);

        $this->actingAs($user)
            ->patchJson('/api/v1/user/profile', ['profile_theme' => []])
            ->assertOk();

        $this->assertNull($user->refresh()->profile_theme);
    }

    public function test_profile_theme_merges_into_resolved_tokens(): void
    {
        $user = User::factory()->create([
            'profile_theme' => ['accent' => '#ff0000', 'background' => '#111111'],
        ]);

        $resolver = new ThemeResolver();
        $tokens = $resolver->resolve(profileUser: $user);

        $this->assertSame('#ff0000', $tokens['accent'] ?? null);
        $this->assertSame('#111111', $tokens['background'] ?? null);
    }

    public function test_profile_theme_is_skipped_when_feature_is_disabled(): void
    {
        $user = User::factory()->create([
            'profile_theme' => ['accent' => '#ff0000'],
        ]);

        $options = SiteOption::current();
        $options->values = array_merge($options->values ?? [], ['profile_themes_enabled' => false]);
        $options->save();

        $resolver = new ThemeResolver();
        $tokens = $resolver->resolve(profileUser: $user);

        $this->assertArrayNotHasKey('accent', $tokens);
    }

    public function test_user_resource_includes_profile_theme_fields(): void
    {
        $user = User::factory()->create([
            'profile_theme' => ['accent' => '#ff0000'],
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/user')
            ->assertOk()
            ->assertJsonPath('data.profile_theme.accent', '#ff0000')
            ->assertJsonPath('data.profile_themes_enabled', true);
    }

    public function test_user_resource_reports_disabled_when_feature_is_off(): void
    {
        $user = User::factory()->create();

        $options = SiteOption::current();
        $options->values = array_merge($options->values ?? [], ['profile_themes_enabled' => false]);
        $options->save();

        $this->actingAs($user)
            ->getJson('/api/v1/user')
            ->assertOk()
            ->assertJsonPath('data.profile_themes_enabled', false);
    }

    public function test_theme_endpoint_applies_profile_overlay(): void
    {
        $user = User::factory()->create([
            'profile_theme' => ['accent' => '#ff0000'],
        ]);

        $response = $this->getJson("/api/v1/theme?subject_type=user_profile&subject_id={$user->id}")
            ->assertOk();

        $this->assertSame('#ff0000', $response->json('tokens.accent'));
    }

    public function test_theme_endpoint_without_profile_scope_has_no_overlay(): void
    {
        User::factory()->create([
            'profile_theme' => ['accent' => '#ff0000'],
        ]);

        $response = $this->getJson('/api/v1/theme')->assertOk();

        $this->assertNotSame('#ff0000', $response->json('tokens.accent'));
    }
}
