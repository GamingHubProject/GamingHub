<?php

namespace Tests\Feature;

use App\Experience\ThemeBundle;
use App\Experience\ThemePackage;
use App\Experience\ThemePackageException;
use App\Experience\ThemeStorage;
use App\Models\Asset;
use App\Models\Theme;
use App\Models\ThemeAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\InteractsWithThemes;
use Tests\TestCase;
use ZipArchive;

class ThemePackageTest extends TestCase
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

    private function packages(): ThemePackage
    {
        return app(ThemePackage::class);
    }

    private function tempPath(string $name = 'pkg'): string
    {
        $path = sys_get_temp_dir().'/'.$name.'_'.uniqid().'.zip';
        $this->tempFiles[] = $path;

        return $path;
    }

    /** Build an archive by hand, so a test can put things in it that an export never would. */
    private function makeArchive(array $entries): string
    {
        $path = $this->tempPath();
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($entries as $name => $contents) {
            $zip->addFromString($name, $contents);
        }

        $zip->close();

        return $path;
    }

    private function manifest(array $overrides = []): string
    {
        return json_encode(array_merge([
            'schema' => ThemeBundle::SCHEMA,
            'id' => 'imported',
            'name' => 'Imported',
            'version' => '2.0.0',
            'tokens' => ['accent' => '#ff0000'],
        ], $overrides));
    }

    private function entriesIn(string $zipPath): array
    {
        $zip = new ZipArchive;
        $zip->open($zipPath);
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        $zip->close();

        return $names;
    }

    // --- Export ------------------------------------------------------

    public function test_export_puts_theme_json_at_the_root_alongside_the_theme_files(): void
    {
        $theme = $this->makeTheme('Testbed', ['tokens' => ['accent' => '#7c5cff']]);
        $this->putThemeFile($theme, 'font/Inter.woff2', 'FONTBYTES');
        $this->putThemeFile($theme, 'backgrounds/stars.png', 'PNGBYTES');

        $path = $this->packages()->export($theme, $this->tempPath());

        // No wrapping folder: the manifest is findable without first
        // guessing what the theme's folder is called.
        $this->assertEqualsCanonicalizing(
            ['theme.json', 'font/Inter.woff2', 'backgrounds/stars.png'],
            $this->entriesIn($path)
        );
    }

    public function test_export_filename_carries_the_slug_and_version(): void
    {
        $theme = $this->makeTheme('Testbed');
        $bundle = $theme->bundle();
        $bundle->version = '3.1.4';
        app(ThemeStorage::class)->writeBundle($theme, $bundle);

        $this->assertSame('testbed-3.1.4.zip', $this->packages()->filename($theme->refresh()));
    }

    public function test_an_exported_theme_carries_nothing_about_the_site_it_came_from(): void
    {
        // The portability guarantee, asserted rather than assumed: the
        // logo, site name and the scopes a theme is applied to live
        // outside theme.json, so an export is safe to hand to a stranger.
        $theme = $this->makeTheme('Testbed', [], 'platform');

        $path = $this->packages()->export($theme, $this->tempPath());
        $zip = new ZipArchive;
        $zip->open($path);
        $manifest = json_decode($zip->getFromName('theme.json'), true);
        $zip->close();

        $this->assertArrayNotHasKey('assignments', $manifest);
        $this->assertArrayNotHasKey('branding', $manifest);
        $this->assertArrayNotHasKey('navigation', $manifest);
    }

    // --- Round trip --------------------------------------------------

    public function test_a_theme_survives_a_round_trip_through_a_package(): void
    {
        $theme = $this->makeTheme('Testbed', ['tokens' => ['accent' => '#7c5cff', 'text' => '#eeeeee']]);
        $this->putThemeFile($theme, 'font/Inter.woff2', 'FONTBYTES');

        $path = $this->packages()->export($theme, $this->tempPath());
        $imported = $this->packages()->import($path, 'Testbed copy');

        $this->assertSame('#7c5cff', $imported->bundle()->tokens['accent']);
        $this->assertSame('#eeeeee', $imported->bundle()->tokens['text']);
        $this->assertSame(
            'FONTBYTES',
            Storage::disk(config('assets.disk'))->get(app(ThemeStorage::class)->themePath($imported->slug, 'font/Inter.woff2'))
        );
    }

    public function test_an_imported_theme_takes_the_destination_slug_as_its_identity(): void
    {
        // theme.json's id describes the theme on the install it came from;
        // here the folder it landed in is what it is.
        $path = $this->makeArchive(['theme.json' => $this->manifest(['id' => 'somewhere-else'])]);

        $imported = $this->packages()->import($path);

        $this->assertSame($imported->slug, $imported->bundle()->id);
        $this->assertNotSame('somewhere-else', $imported->bundle()->id);
    }

    public function test_importing_a_name_that_is_taken_makes_a_separate_copy_by_default(): void
    {
        $original = $this->makeTheme('Testbed');
        $path = $this->makeArchive(['theme.json' => $this->manifest(['name' => 'Testbed'])]);

        $imported = $this->packages()->import($path, 'Testbed');

        $this->assertNotSame($original->slug, $imported->slug);
        $this->assertTrue(Theme::whereKey($original->id)->exists());
    }

    public function test_replacing_keeps_the_slug_and_stays_applied(): void
    {
        // The point of replace: "update my theme to v2" must not un-theme
        // the site. Scope lives in ThemeAssignment, not in the theme.
        $original = $this->makeTheme('Testbed', ['tokens' => ['accent' => '#000000']], 'platform');
        $path = $this->makeArchive(['theme.json' => $this->manifest(['name' => 'Testbed', 'tokens' => ['accent' => '#ff0000']])]);

        $imported = $this->packages()->import($path, 'Testbed', 'replace');

        $this->assertSame($original->id, $imported->id);
        $this->assertSame($original->slug, $imported->slug);
        $this->assertSame('#ff0000', $imported->bundle()->tokens['accent']);
        $this->assertTrue(ThemeAssignment::where('theme_id', $original->id)->exists());
    }

    public function test_replacing_does_not_leave_the_previous_versions_files_behind(): void
    {
        $original = $this->makeTheme('Testbed');
        $this->putThemeFile($original, 'backgrounds/old.png', 'OLD');
        $path = $this->makeArchive([
            'theme.json' => $this->manifest(['name' => 'Testbed']),
            'backgrounds/new.png' => 'NEW',
        ]);

        $imported = $this->packages()->import($path, 'Testbed', 'replace');

        $disk = Storage::disk(config('assets.disk'));
        $storage = app(ThemeStorage::class);
        $this->assertFalse($disk->exists($storage->themePath($imported->slug, 'backgrounds/old.png')));
        $this->assertTrue($disk->exists($storage->themePath($imported->slug, 'backgrounds/new.png')));
    }

    public function test_imported_files_become_visible_in_the_asset_library(): void
    {
        $path = $this->makeArchive([
            'theme.json' => $this->manifest(),
            'font/Inter.woff2' => 'FONTBYTES',
        ]);

        $imported = $this->packages()->import($path);

        $asset = Asset::where('disk_path', app(ThemeStorage::class)->themePath($imported->slug, 'font/Inter.woff2'))->first();
        $this->assertNotNull($asset, 'An imported file with no Asset row is invisible in the library.');
        $this->assertSame('font/woff2', $asset->mime_type);
    }

    // --- What an archive is not allowed to do ------------------------

    public function test_an_archive_with_no_theme_json_is_refused(): void
    {
        $path = $this->makeArchive(['backgrounds/stars.png' => 'PNG']);

        $this->expectException(ThemePackageException::class);
        $this->expectExceptionMessage('no theme.json');

        $this->packages()->import($path);
    }

    public function test_an_unreadable_file_is_refused(): void
    {
        $path = $this->tempPath();
        file_put_contents($path, 'this is not a zip');

        $this->expectException(ThemePackageException::class);

        $this->packages()->import($path);
    }

    public function test_invalid_json_is_refused(): void
    {
        $path = $this->makeArchive(['theme.json' => '{not json']);

        $this->expectException(ThemePackageException::class);
        $this->expectExceptionMessage('not valid JSON');

        $this->packages()->import($path);
    }

    public function test_a_theme_declaring_a_schema_this_version_cannot_read_is_refused(): void
    {
        $path = $this->makeArchive(['theme.json' => $this->manifest(['schema' => 'gaming-hub/theme@99'])]);

        $this->expectException(ThemePackageException::class);
        $this->expectExceptionMessage('gaming-hub/theme@99');

        $this->packages()->import($path);
    }

    public function test_a_theme_json_with_no_schema_line_is_taken_at_its_word(): void
    {
        // A hand-written theme is a supported way to make one; only a
        // schema that is present and unrecognised is a positive claim
        // this version can't honour.
        $raw = json_encode(['name' => 'Handwritten', 'tokens' => ['accent' => '#123456']]);
        $path = $this->makeArchive(['theme.json' => $raw]);

        $imported = $this->packages()->import($path);

        $this->assertSame('#123456', $imported->bundle()->tokens['accent']);
    }

    public function test_a_file_escaping_the_theme_folder_is_refused(): void
    {
        $path = $this->makeArchive([
            'theme.json' => $this->manifest(),
            '../../evil.png' => 'PWNED',
        ]);

        $this->expectException(ThemePackageException::class);

        $this->packages()->import($path);
    }

    public function test_an_absolute_path_entry_is_refused(): void
    {
        // The other shape of the same attack: not climbing out of the
        // folder but never being in it. Verified that ZipArchive stores
        // both of these names verbatim rather than normalising them, so
        // these two tests are testing something real.
        $path = $this->makeArchive([
            'theme.json' => $this->manifest(),
            '/etc/cron.d/evil' => 'PWNED',
        ]);

        $this->expectException(ThemePackageException::class);

        $this->packages()->import($path);
    }

    public function test_a_file_in_a_folder_the_theme_does_not_have_is_refused(): void
    {
        $path = $this->makeArchive([
            'theme.json' => $this->manifest(),
            'scripts/run.sh' => 'rm -rf /',
        ]);

        $this->expectException(ThemePackageException::class);
        $this->expectExceptionMessage('not one of a theme');

        $this->packages()->import($path);
    }

    public function test_a_file_with_an_extension_that_folder_does_not_take_is_refused(): void
    {
        // These land on the same public disk an upload does, so they get
        // the same policy an upload gets.
        $path = $this->makeArchive([
            'theme.json' => $this->manifest(),
            'backgrounds/shell.php' => '<?php system($_GET["c"]);',
        ]);

        $this->expectException(ThemePackageException::class);
        $this->expectExceptionMessage('must be one of');

        $this->packages()->import($path);
    }

    public function test_a_font_folder_will_not_take_a_format_the_asset_library_rejects(): void
    {
        $path = $this->makeArchive([
            'theme.json' => $this->manifest(),
            'font/Inter.ttf' => 'TTF',
        ]);

        $this->expectException(ThemePackageException::class);

        $this->packages()->import($path);
    }

    public function test_a_loose_file_at_the_root_is_refused(): void
    {
        $path = $this->makeArchive([
            'theme.json' => $this->manifest(),
            'README.md' => 'hello',
        ]);

        $this->expectException(ThemePackageException::class);
        $this->expectExceptionMessage('one level deep');

        $this->packages()->import($path);
    }

    public function test_nothing_is_written_when_an_archive_is_refused(): void
    {
        $before = Theme::count();
        $path = $this->makeArchive([
            'theme.json' => $this->manifest(),
            'scripts/run.sh' => 'x',
        ]);

        try {
            $this->packages()->import($path);
        } catch (ThemePackageException) {
            // expected
        }

        // Validation happens before the first write, so a rejected archive
        // leaves no half-made theme behind.
        $this->assertSame($before, Theme::count());
    }

    public function test_a_single_wrapping_folder_is_tolerated(): void
    {
        // What GitHub's "Download source" and `git archive` produce —
        // refusing them would make the obvious way to share a theme the
        // wrong one.
        $path = $this->makeArchive([
            'DefaultThemes-1.0.0/theme.json' => $this->manifest(),
            'DefaultThemes-1.0.0/font/Inter.woff2' => 'FONTBYTES',
        ]);

        $imported = $this->packages()->import($path);

        $this->assertSame('#ff0000', $imported->bundle()->tokens['accent']);
        $this->assertTrue(
            Storage::disk(config('assets.disk'))->exists(app(ThemeStorage::class)->themePath($imported->slug, 'font/Inter.woff2'))
        );
    }

    public function test_a_manifest_buried_deeper_than_one_folder_is_refused(): void
    {
        $path = $this->makeArchive(['a/b/theme.json' => $this->manifest()]);

        $this->expectException(ThemePackageException::class);
        $this->expectExceptionMessage('nested');

        $this->packages()->import($path);
    }

    public function test_an_archive_with_absurdly_many_entries_is_refused(): void
    {
        $entries = ['theme.json' => $this->manifest()];
        for ($i = 0; $i < ThemePackage::MAX_ENTRIES + 1; $i++) {
            $entries["backgrounds/{$i}.png"] = 'x';
        }

        $this->expectException(ThemePackageException::class);
        $this->expectExceptionMessage('at most');

        $this->packages()->import($this->makeArchive($entries));
    }

    // --- Inspect -----------------------------------------------------

    public function test_inspect_describes_an_archive_without_writing_anything(): void
    {
        $before = Theme::count();
        $path = $this->makeArchive([
            'theme.json' => $this->manifest(['name' => 'Testbed', 'version' => '2.0.0']),
            'font/Inter.woff2' => 'FONTBYTES',
        ]);

        $info = $this->packages()->inspect($path);

        $this->assertSame('Testbed', $info['name']);
        $this->assertSame('2.0.0', $info['version']);
        $this->assertSame(1, $info['files']);
        $this->assertSame($before, Theme::count());
    }

    public function test_replace_finds_the_theme_by_the_name_the_admin_sees_not_by_its_folder(): void
    {
        // Set up the case the two lookups disagree on: the folder called
        // "testbed" belongs to a theme that has since been renamed, and
        // the theme actually called "Testbed" lives at a suffixed slug.
        // The admin picks a name, and the name is what the themes list
        // shows them, so replacing "Testbed" has to land on the second.
        $renamed = $this->makeTheme('Testbed');
        $this->assertSame('testbed', $renamed->slug, 'Precondition: it took the obvious slug.');
        $renamed->forceFill(['name' => 'Something else'])->save();

        $real = $this->makeTheme('Testbed');
        $this->assertNotSame('testbed', $real->slug, 'Precondition: the slug was already taken.');

        $path = $this->makeArchive(['theme.json' => $this->manifest(['name' => 'Testbed'])]);
        $imported = $this->packages()->import($path, 'Testbed', 'replace');

        $this->assertSame($real->id, $imported->id);
        $this->assertSame('Something else', $renamed->refresh()->name, 'The renamed theme was left alone.');
    }

    public function test_conflict_for_finds_the_theme_an_import_would_collide_with(): void
    {
        $existing = $this->makeTheme('Testbed');

        $this->assertSame($existing->id, $this->packages()->conflictFor('Testbed')?->id);
        $this->assertNull($this->packages()->conflictFor('Something else entirely'));
    }
}
