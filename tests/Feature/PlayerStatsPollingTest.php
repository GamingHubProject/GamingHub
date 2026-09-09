<?php

namespace Tests\Feature;

use App\Contracts\PlayerStatsContract;
use App\Contracts\PlayerStatWrite;
use App\Models\PlayerGameIdentity;
use App\Models\User;
use App\Profiles\PlayerIdentityResolver;
use App\Profiles\UserStats;
use GamingHub\Core\Models\Game;
use GamingHub\Core\Models\Provider;
use GamingHub\Core\Models\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class PlayerStatsPollingTest extends TestCase
{
    use RefreshDatabase;

    public function test_poll_tick_writes_stats_for_matched_users_and_skips_unmatched(): void
    {
        [$server, $provider] = $this->createServerWithProvider();

        $linked = User::factory()->create();
        PlayerGameIdentity::create([
            'user_id' => $linked->id,
            'game_slug' => 'palworld',
            'hashed_player_id' => PlayerIdentityResolver::hash('player_a'),
        ]);

        $provider->update([
            'last_raw_response' => [
                'players' => [
                    ['id' => 'player_a', 'hours' => 47.5],
                    ['id' => 'player_b', 'hours' => 12.0],
                ],
            ],
        ]);

        $this->registerFakeExtension([
            new PlayerStatWrite('player_a', 'palworld', 'hours', 47.5),
            new PlayerStatWrite('player_b', 'palworld', 'hours', 12.0),
        ]);

        $this->artisan('gaming-hub:poll-providers', ['--once' => true])->assertExitCode(0);

        $this->assertSame(47.5, UserStats::get($linked, 'palworld', 'hours')?->value);
        $this->assertDatabaseMissing('user_stats', ['source' => 'palworld', 'key' => 'hours', 'value' => 12.0]);
    }

    public function test_increment_stat_writes_use_increment_not_set(): void
    {
        [$server, $provider] = $this->createServerWithProvider();

        $user = User::factory()->create();
        PlayerGameIdentity::create([
            'user_id' => $user->id,
            'game_slug' => 'palworld',
            'hashed_player_id' => PlayerIdentityResolver::hash('player_a'),
        ]);

        UserStats::set($user, 'palworld', 'kills', 10);

        $provider->update([
            'last_raw_response' => ['players' => [['id' => 'player_a', 'kills_delta' => 5]]],
        ]);

        $this->registerFakeExtension([
            new PlayerStatWrite('player_a', 'palworld', 'kills', 5, cumulative: false),
        ]);

        $this->artisan('gaming-hub:poll-providers', ['--once' => true])->assertExitCode(0);

        $this->assertSame(15.0, UserStats::get($user, 'palworld', 'kills')->value);
    }

    public function test_failing_extension_logs_and_does_not_affect_server_refresh(): void
    {
        [$server, $provider] = $this->createServerWithProvider();

        $provider->update([
            'last_raw_response' => ['players' => []],
            'config' => array_merge($provider->config, [
                'normalizer' => 'field-mapping',
                'capability' => 'server-status',
                'field_map' => [],
            ]),
        ]);

        $this->registerThrowingExtension();

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn ($msg) => str_contains($msg, 'Player stats extraction failed'));

        // Allow other log calls through.
        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('debug')->zeroOrMoreTimes();
        Log::shouldReceive('warning')->zeroOrMoreTimes();

        $this->artisan('gaming-hub:poll-providers', ['--once' => true])->assertExitCode(0);
    }

    public function test_server_without_extension_skips_player_stats_silently(): void
    {
        $game = Game::factory()->create(['slug' => 'ark']);
        $server = Server::factory()->create(['game_id' => $game->id, 'status' => 'online']);

        Provider::factory()->create([
            'server_id' => $server->id,
            'type' => 'manual',
            'config' => ['capability' => 'server-status', 'value' => ['online' => 'true']],
        ]);

        $this->artisan('gaming-hub:poll-providers', ['--once' => true])->assertExitCode(0);

        $this->assertDatabaseCount('user_stats', 0);
    }

    private function createServerWithProvider(): array
    {
        $game = Game::factory()->create(['slug' => 'palworld']);
        $server = Server::factory()->create(['game_id' => $game->id, 'status' => 'online']);

        $provider = Provider::factory()->create([
            'server_id' => $server->id,
            'type' => 'manual',
            'config' => ['capability' => 'server-status', 'value' => ['online' => 'true']],
        ]);

        return [$server, $provider];
    }

    private function registerFakeExtension(array $writes): void
    {
        $extension = new class($writes) implements PlayerStatsContract {
            public function __construct(private array $writes) {}

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
                return null;
            }

            public function extractPlayerStats(Server $server, array $rawApiResponse): array
            {
                return $this->writes;
            }
        };

        $this->app->tag([$extension::class], 'player-stats-extensions');
        $this->app->instance($extension::class, $extension);
    }

    private function registerThrowingExtension(): void
    {
        $extension = new class implements PlayerStatsContract {
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
                return null;
            }

            public function extractPlayerStats(Server $server, array $rawApiResponse): array
            {
                throw new \RuntimeException('Extension crashed');
            }
        };

        $this->app->tag([$extension::class], 'player-stats-extensions');
        $this->app->instance($extension::class, $extension);
    }
}
