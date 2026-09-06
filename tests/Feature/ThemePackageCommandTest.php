<?php

namespace Tests\Feature;

use App\Experience\ThemePackage;
use App\Models\Theme;
use App\Models\ThemeAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\InteractsWithThemes;
use Tests\TestCase;

/**
 * The command line is not just a convenience here: it's how a theme
 * package gets built for the registry in Phase D, without a browser in the
 * loop. So it gets the same coverage the UI path does.
 */
class ThemePackageCommandTest extends TestCase
{
    use InteractsWithThemes;
    use RefreshDatabase;

    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeThemeDisk();
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    private function tempPath(): string
    {
        $path = sys_get_temp_dir().'/cmd_'.uniqid().'.zip';
        $this->tempFiles[] = $path;

        return $path;
    }

    public function test_export_writes_a_package_to_the_given_path(): void
    {
        $theme = $this->makeTheme('Testbed');
        $out = $this->tempPath();

        $this->artisan('themes:export', ['slug' => $theme->slug, '--out' => $out])
            ->assertSuccessful();

        $this->assertFileExists($out);
    }

    public function test_export_fails_loudly_for_a_slug_that_is_not_there(): void
    {
        $this->artisan('themes:export', ['slug' => 'no-such-theme'])
            ->expectsOutputToContain('No theme with slug')
            ->assertFailed();
    }

    public function test_a_theme_can_be_exported_and_imported_back_from_the_command_line(): void
    {
        $theme = $this->makeTheme('Testbed', ['tokens' => ['accent' => '#7c5cff']]);
        $this->putThemeFile($theme, 'font/Inter.woff2', 'FONTBYTES');
        $out = $this->tempPath();

        $this->artisan('themes:export', ['slug' => $theme->slug, '--out' => $out])->assertSuccessful();
        $this->artisan('themes:import', ['file' => $out, '--name' => 'Testbed from disk'])->assertSuccessful();

        $imported = Theme::where('name', 'Testbed from disk')->firstOrFail();
        $this->assertSame('#7c5cff', $imported->bundle()->tokens['accent']);
    }

    public function test_import_warns_rather_than_overwriting_when_the_name_is_taken(): void
    {
        $original = $this->makeTheme('Testbed');
        $out = $this->tempPath();
        $this->artisan('themes:export', ['slug' => $original->slug, '--out' => $out])->assertSuccessful();

        $this->artisan('themes:import', ['file' => $out])
            ->expectsOutputToContain('already exists')
            ->assertSuccessful();

        $this->assertSame(2, Theme::where('name', 'Testbed')->count());
    }

    public function test_import_replace_keeps_the_theme_applied(): void
    {
        $original = $this->makeTheme('Testbed', [], 'platform');
        $out = $this->tempPath();
        $this->artisan('themes:export', ['slug' => $original->slug, '--out' => $out])->assertSuccessful();

        $this->artisan('themes:import', ['file' => $out, '--replace' => true])->assertSuccessful();

        $this->assertSame(1, Theme::where('name', 'Testbed')->count());
        $this->assertTrue(ThemeAssignment::where('theme_id', $original->id)->exists());
    }

    public function test_inspect_reports_without_importing(): void
    {
        $theme = $this->makeTheme('Testbed');
        $out = $this->tempPath();
        $this->artisan('themes:export', ['slug' => $theme->slug, '--out' => $out])->assertSuccessful();
        $before = Theme::count();

        $this->artisan('themes:import', ['file' => $out, '--inspect' => true])
            ->expectsOutputToContain('Testbed')
            ->assertSuccessful();

        $this->assertSame($before, Theme::count());
    }

    public function test_import_reports_a_bad_package_instead_of_throwing(): void
    {
        $out = $this->tempPath();
        file_put_contents($out, 'not a zip at all');

        $this->artisan('themes:import', ['file' => $out])->assertFailed();
    }

    public function test_import_fails_for_a_file_that_is_not_there(): void
    {
        $this->artisan('themes:import', ['file' => '/tmp/definitely-not-here.zip'])
            ->expectsOutputToContain('No file at')
            ->assertFailed();
    }
}
