<?php

namespace Tests\Feature\Profiles;

use App\Contracts\PlayerStatsContract;
use App\Contracts\PlayerStatWrite;
use App\Models\PlayerGameIdentity;
use App\Models\User;
use App\Profiles\PlayerIdentityResolver;
use GamingHub\Core\Models\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_hash_is_deterministic(): void
    {
        $a = PlayerIdentityResolver::hash('76561198012345678');
        $b = PlayerIdentityResolver::hash('76561198012345678');

        $this->assertSame($a, $b);
        $this->assertSame(64, strlen($a));
    }

    public function test_different_ids_produce_different_hashes(): void
    {
        $a = PlayerIdentityResolver::hash('76561198012345678');
        $b = PlayerIdentityResolver::hash('76561198099999999');

        $this->assertNotSame($a, $b);
    }

    public function test_resolve_returns_user_id_for_linked_and_null_for_unlinked(): void
    {
        $user = User::factory()->create();

        PlayerGameIdentity::create([
            'user_id' => $user->id,
            'game_slug' => 'palworld',
            'hashed_player_id' => PlayerIdentityResolver::hash('steam_12345'),
        ]);

        $result = PlayerIdentityResolver::resolve('palworld', ['steam_12345', 'steam_99999']);

        $this->assertSame($user->id, $result['steam_12345']);
        $this->assertNull($result['steam_99999']);
    }

    public function test_resolve_scopes_by_game_slug(): void
    {
        $user = User::factory()->create();

        PlayerGameIdentity::create([
            'user_id' => $user->id,
            'game_slug' => 'palworld',
            'hashed_player_id' => PlayerIdentityResolver::hash('player1'),
        ]);

        $result = PlayerIdentityResolver::resolve('ark', ['player1']);

        $this->assertNull($result['player1']);
    }

    public function test_resolve_handles_empty_input(): void
    {
        $this->assertSame([], PlayerIdentityResolver::resolve('palworld', []));
    }

    public function test_link_stores_hash_and_never_returns_raw_id(): void
    {
        $user = User::factory()->create();
        $this->registerFakeExtension();

        $this->actingAs($user)
            ->postJson('/api/v1/user/game-identities', [
                'game_slug' => 'palworld',
                'player_id' => 'steam_12345',
            ])
            ->assertOk()
            ->assertJson(['linked' => true]);

        $this->assertDatabaseHas('player_game_identities', [
            'user_id' => $user->id,
            'game_slug' => 'palworld',
            'hashed_player_id' => PlayerIdentityResolver::hash('steam_12345'),
        ]);

        $indexResponse = $this->actingAs($user)
            ->getJson('/api/v1/user/game-identities')
            ->assertOk();

        $games = $indexResponse->json('data');
        $palworld = collect($games)->firstWhere('game_slug', 'palworld');
        $this->assertTrue($palworld['linked']);
        $this->assertArrayNotHasKey('hashed_player_id', $palworld);
        $this->assertArrayNotHasKey('player_id', $palworld);
    }

    public function test_unlink_deletes_the_identity(): void
    {
        $user = User::factory()->create();
        $this->registerFakeExtension();

        PlayerGameIdentity::create([
            'user_id' => $user->id,
            'game_slug' => 'palworld',
            'hashed_player_id' => PlayerIdentityResolver::hash('steam_12345'),
        ]);

        $this->actingAs($user)
            ->deleteJson('/api/v1/user/game-identities/palworld')
            ->assertOk()
            ->assertJson(['linked' => false]);

        $this->assertDatabaseMissing('player_game_identities', [
            'user_id' => $user->id,
            'game_slug' => 'palworld',
        ]);
    }

    public function test_relink_overwrites_with_new_hash(): void
    {
        $user = User::factory()->create();
        $this->registerFakeExtension();

        $this->actingAs($user)->postJson('/api/v1/user/game-identities', [
            'game_slug' => 'palworld',
            'player_id' => 'old_id',
        ]);

        $this->actingAs($user)->postJson('/api/v1/user/game-identities', [
            'game_slug' => 'palworld',
            'player_id' => 'new_id',
        ]);

        $this->assertDatabaseCount('player_game_identities', 1);
        $this->assertDatabaseHas('player_game_identities', [
            'user_id' => $user->id,
            'hashed_player_id' => PlayerIdentityResolver::hash('new_id'),
        ]);
    }

    public function test_validation_error_from_extension_is_returned(): void
    {
        $user = User::factory()->create();
        $this->registerFakeExtension(validateResult: 'Steam IDs must be 17 digits.');

        $this->actingAs($user)
            ->postJson('/api/v1/user/game-identities', [
                'game_slug' => 'palworld',
                'player_id' => 'bad',
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.player_id.0', 'Steam IDs must be 17 digits.');
    }

    public function test_game_without_extension_not_in_index(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/user/game-identities');

        $response->assertOk()->assertJson(['data' => []]);
    }

    public function test_game_without_extension_rejects_link(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/user/game-identities', [
                'game_slug' => 'unknown-game',
                'player_id' => 'some_id',
            ])
            ->assertStatus(422);
    }

    public function test_unauthenticated_user_cannot_access_identities(): void
    {
        $this->getJson('/api/v1/user/game-identities')->assertUnauthorized();
        $this->postJson('/api/v1/user/game-identities')->assertUnauthorized();
        $this->deleteJson('/api/v1/user/game-identities/palworld')->assertUnauthorized();
    }

    private function registerFakeExtension(?string $validateResult = null): void
    {
        $extension = new class($validateResult) implements PlayerStatsContract {
            public function __construct(private ?string $validateResult) {}

            public function supportsPlayerIdentity(): array
            {
                return ['palworld'];
            }

            public function playerIdLabel(string $gameSlug): string
            {
                return 'Steam ID';
            }

            public function validatePlayerId(string $gameSlug, string $rawPlayerId): ?string
            {
                return $this->validateResult;
            }

            public function extractPlayerStats(Server $server, array $rawApiResponse): array
            {
                return [];
            }
        };

        $this->app->tag([$extension::class], 'player-stats-extensions');
        $this->app->instance($extension::class, $extension);
    }
}
