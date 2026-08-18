<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
    }

    public function test_401s_for_a_guest(): void
    {
        $this->getJson('/api/v1/user')->assertUnauthorized();
    }
}
