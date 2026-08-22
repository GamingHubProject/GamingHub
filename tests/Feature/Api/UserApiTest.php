<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_the_authenticated_user(): void
    {
        $user = User::factory()->create(['name' => 'Rose']);

        $response = $this->actingAs($user)->getJson('/api/v1/user');

        $response->assertOk();
        $response->assertJsonPath('data.id', $user->id);
        $response->assertJsonPath('data.name', 'Rose');
        $response->assertJsonPath('data.is_admin', false);
    }

    public function test_is_admin_reflects_the_admin_role(): void
    {
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('Admin');

        $response = $this->actingAs($user)->getJson('/api/v1/user');

        $response->assertJsonPath('data.is_admin', true);
    }

    public function test_401s_for_a_guest(): void
    {
        $this->getJson('/api/v1/user')->assertUnauthorized();
    }
}
