<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\InteractsWithThemes;
use Tests\TestCase;

class SyncThemesCommandTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithThemes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeThemeDisk();
    }

    private function editOnDisk(string $slug, array $data): void
    {
        Storage::disk(config('assets.disk'))->put(
            app(\App\Experience\ThemeStorage::class)->themePath($slug, 'theme.json'),
            json_encode($data)
        );
    }

    public function test_it_rebuilds_the_cached_payload_from_the_folder(): void
    {
        $theme = $this->makeTheme('Midnight', ['tokens' => ['accent' => '#000000']]);
        $this->editOnDisk($theme->slug, ['id' => $theme->slug, 'name' => 'Midnight', 'tokens' => ['accent' => '#ff0000']]);

        $this->artisan('themes:sync')->assertSuccessful();

        $this->assertSame('#ff0000', $theme->refresh()->payload['tokens']['accent']);
    }

    public function test_it_can_target_a_single_theme(): void
    {
        $a = $this->makeTheme('Alpha', ['tokens' => ['accent' => '#000000']]);
        $b = $this->makeTheme('Beta', ['tokens' => ['accent' => '#000000']]);
        $this->editOnDisk($a->slug, ['id' => $a->slug, 'name' => 'Alpha', 'tokens' => ['accent' => '#ff0000']]);
        $this->editOnDisk($b->slug, ['id' => $b->slug, 'name' => 'Beta', 'tokens' => ['accent' => '#00ff00']]);

        $this->artisan('themes:sync', ['slug' => $a->slug])->assertSuccessful();

        $this->assertSame('#ff0000', $a->refresh()->payload['tokens']['accent']);
        $this->assertSame('#000000', $b->refresh()->payload['tokens']['accent']);
    }

    public function test_the_stale_option_skips_themes_already_in_sync(): void
    {
        $fresh = $this->makeTheme('Fresh');
        $drifted = $this->makeTheme('Drifted', ['tokens' => ['accent' => '#000000']]);
        $this->editOnDisk($drifted->slug, ['id' => $drifted->slug, 'name' => 'Drifted', 'tokens' => ['accent' => '#ff0000']]);

        $this->artisan('themes:sync', ['--stale' => true])
            ->expectsOutputToContain($drifted->slug)
            ->doesntExpectOutputToContain($fresh->slug)
            ->assertSuccessful();
    }

    public function test_it_reports_a_theme_whose_folder_lost_its_theme_json(): void
    {
        // Surfaced rather than silently leaving the last known payload in
        // place — this is the command an admin runs to find out what's
        // wrong.
        $theme = $this->makeTheme('Broken');
        Storage::disk(config('assets.disk'))->delete(
            app(\App\Experience\ThemeStorage::class)->themePath($theme->slug, 'theme.json')
        );

        $this->artisan('themes:sync')
            ->expectsOutputToContain('missing theme.json')
            ->assertSuccessful();
    }

    public function test_it_is_safe_to_run_repeatedly(): void
    {
        $theme = $this->makeTheme('Midnight', ['tokens' => ['accent' => '#4f46e5']]);

        $this->artisan('themes:sync')->assertSuccessful();
        $checksum = $theme->refresh()->checksum;
        $this->artisan('themes:sync')->assertSuccessful();

        $this->assertSame($checksum, $theme->refresh()->checksum);
        $this->assertSame('#4f46e5', $theme->payload['tokens']['accent']);
    }
}
