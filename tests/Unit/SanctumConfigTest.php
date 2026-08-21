<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * env('KEY', $default) only substitutes $default when the env var is
 * completely absent, not when it's present-but-blank — the state every
 * non-HTTPS install leaves SANCTUM_STATEFUL_DOMAINS in unless the "access
 * host" prompt was answered (and the installer's Update path never asks
 * it at all). A blank value here silently breaks the SPA's cookie auth
 * while Filament (a different guard) keeps working, so this must fall
 * back the same way an absent value does.
 */
class SanctumConfigTest extends TestCase
{
    protected ?string $original = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->original = $_ENV['SANCTUM_STATEFUL_DOMAINS'] ?? null;
    }

    protected function tearDown(): void
    {
        $this->setEnv($this->original);
        parent::tearDown();
    }

    /**
     * Laravel's env() reads $_ENV/$_SERVER (populated once by dotenv at
     * boot) ahead of plain getenv(), so a bare putenv() in a test isn't
     * enough to override it — all three need to agree.
     */
    protected function setEnv(?string $value): void
    {
        if ($value === null) {
            putenv('SANCTUM_STATEFUL_DOMAINS');
            unset($_ENV['SANCTUM_STATEFUL_DOMAINS'], $_SERVER['SANCTUM_STATEFUL_DOMAINS']);

            return;
        }

        putenv("SANCTUM_STATEFUL_DOMAINS={$value}");
        $_ENV['SANCTUM_STATEFUL_DOMAINS'] = $value;
        $_SERVER['SANCTUM_STATEFUL_DOMAINS'] = $value;
    }

    public function test_a_present_but_blank_env_value_falls_back_to_the_default_list(): void
    {
        $this->setEnv('');

        $config = require config_path('sanctum.php');

        $this->assertNotContains('', $config['stateful']);
        $this->assertContains('localhost', $config['stateful']);
    }

    public function test_an_absent_env_value_also_falls_back_to_the_default_list(): void
    {
        $this->setEnv(null);

        $config = require config_path('sanctum.php');

        $this->assertNotContains('', $config['stateful']);
        $this->assertContains('localhost', $config['stateful']);
    }

    public function test_an_explicit_value_is_used_verbatim(): void
    {
        $this->setEnv('example.test:8087');

        $config = require config_path('sanctum.php');

        $this->assertSame(['example.test:8087'], $config['stateful']);
    }
}
