<?php

namespace Tests\Feature\Api;

use App\Models\Asset;
use App\Models\AssetFolder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssetApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The app always writes to config('assets.disk'), never a hardcoded
        // disk name — faking anything else silently tests nothing.
        Storage::fake(config('assets.disk'));
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        return $admin;
    }

    public function test_index_is_public_no_auth_required(): void
    {
        $this->getJson('/api/v1/assets')->assertOk();
    }

    public function test_index_returns_items_and_meta_nested_under_data(): void
    {
        Asset::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/assets');

        $response->assertOk();
        $response->assertJsonCount(3, 'data.items');
        $response->assertJsonPath('data.meta.total', 3);
        $response->assertJsonPath('data.meta.current_page', 1);
    }

    public function test_index_filters_by_owner(): void
    {
        Asset::factory()->create(['owner_type' => 'game', 'owner_id' => 5]);
        Asset::factory()->create(['owner_type' => 'game', 'owner_id' => 9]);
        Asset::factory()->create(['owner_type' => null, 'owner_id' => null]);

        $response = $this->getJson('/api/v1/assets?owner_type=game&owner_id=5');

        $response->assertOk();
        $response->assertJsonCount(1, 'data.items');
    }

    public function test_index_excludes_assets_in_an_admin_only_folder_for_a_guest(): void
    {
        $folder = AssetFolder::factory()->adminOnly()->create();
        Asset::factory()->create(['folder_id' => $folder->id]);
        Asset::factory()->create(['folder_id' => null]);

        $response = $this->getJson('/api/v1/assets');

        $response->assertOk();
        $response->assertJsonCount(1, 'data.items');
    }

    public function test_index_includes_assets_in_an_admin_only_folder_for_an_admin(): void
    {
        $folder = AssetFolder::factory()->adminOnly()->create();
        Asset::factory()->create(['folder_id' => $folder->id]);
        Asset::factory()->create(['folder_id' => null]);

        $response = $this->actingAs($this->admin())->getJson('/api/v1/assets');

        $response->assertOk();
        $response->assertJsonCount(2, 'data.items');
    }

    public function test_index_only_shows_a_users_own_private_folder_assets_to_them(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $folder = AssetFolder::factory()->userPrivate($owner->id)->create();
        Asset::factory()->create(['folder_id' => $folder->id]);

        $this->actingAs($other)->getJson('/api/v1/assets')->assertJsonCount(0, 'data.items');
        $this->actingAs($owner)->getJson('/api/v1/assets')->assertJsonCount(1, 'data.items');
    }

    public function test_index_filters_by_folder_id(): void
    {
        $folder = AssetFolder::factory()->create();
        Asset::factory()->create(['folder_id' => $folder->id]);
        Asset::factory()->create(['folder_id' => null]);

        $response = $this->getJson("/api/v1/assets?folder_id={$folder->id}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data.items');
    }

    public function test_update_requires_the_admin_role(): void
    {
        $asset = Asset::factory()->create();

        $this->actingAs(User::factory()->create())
            ->patchJson("/api/v1/assets/{$asset->id}", ['alt_text' => 'x'])
            ->assertForbidden();
    }

    public function test_update_moves_an_asset_to_a_different_folder(): void
    {
        $folder = AssetFolder::factory()->create();
        $asset = Asset::factory()->create(['folder_id' => null]);

        $response = $this->actingAs($this->admin())->patchJson("/api/v1/assets/{$asset->id}", [
            'folder_id' => $folder->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.folder_id', $folder->id);
    }

    public function test_update_syncs_tags(): void
    {
        $asset = Asset::factory()->create();
        $tag = \App\Models\AssetTag::factory()->create();

        $response = $this->actingAs($this->admin())->patchJson("/api/v1/assets/{$asset->id}", [
            'tag_ids' => [$tag->id],
        ]);

        $response->assertOk();
        $response->assertJsonCount(1, 'data.tags');
        $this->assertTrue($asset->fresh()->tags->contains($tag));
    }

    public function test_store_requires_authentication(): void
    {
        $file = UploadedFile::fake()->image('banner.png', 800, 400);

        $this->postJson('/api/v1/assets', ['file' => $file])->assertUnauthorized();
    }

    public function test_store_requires_the_admin_role(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('banner.png', 800, 400);

        $this->actingAs($user)->postJson('/api/v1/assets', ['file' => $file])->assertForbidden();
    }

    public function test_store_creates_an_asset_with_a_thumbnail_for_a_raster_image(): void
    {
        $file = UploadedFile::fake()->image('banner.png', 800, 400);

        $response = $this->actingAs($this->admin())->postJson('/api/v1/assets', [
            'file' => $file,
            'alt_text' => 'A banner',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.mime_type', 'image/png');
        $response->assertJsonPath('data.width', 800);
        $response->assertJsonPath('data.height', 400);
        $response->assertJsonPath('data.alt_text', 'A banner');

        $asset = Asset::first();
        $this->assertNotNull($asset);
        $this->assertNotSame($asset->url, $response->json('data.thumbnail_url'));
        Storage::disk(config('assets.disk'))->assertExists($asset->disk_path);
        Storage::disk(config('assets.disk'))->assertExists($asset->thumbnailPath());
    }

    public function test_store_creates_an_asset_with_a_thumbnail_for_a_jpeg(): void
    {
        // Regression: this PHP/GD build originally had JPEG support
        // compiled out entirely (confirmed live via gd_info() and a real
        // upload's exception — "No JPEG support in this PHP build" —
        // Dockerfile only installed libpng-dev, not libjpeg-dev/libwebp-dev,
        // before `docker-php-ext-install gd` ran). Fixed at the image
        // level; this guards against it silently regressing.
        $file = UploadedFile::fake()->image('banner.jpg', 800, 400);

        $response = $this->actingAs($this->admin())->postJson('/api/v1/assets', ['file' => $file]);

        $response->assertCreated();
        $response->assertJsonPath('data.mime_type', 'image/jpeg');
        $asset = Asset::first();
        Storage::disk(config('assets.disk'))->assertExists($asset->thumbnailPath());
    }

    public function test_store_creates_an_asset_with_a_thumbnail_for_a_webp(): void
    {
        $file = UploadedFile::fake()->image('banner.webp', 800, 400);

        $response = $this->actingAs($this->admin())->postJson('/api/v1/assets', ['file' => $file]);

        $response->assertCreated();
        $response->assertJsonPath('data.mime_type', 'image/webp');
        $asset = Asset::first();
        Storage::disk(config('assets.disk'))->assertExists($asset->thumbnailPath());
    }

    public function test_store_does_not_generate_a_thumbnail_for_svg_and_reuses_the_original_url(): void
    {
        $file = UploadedFile::fake()->createWithContent(
            'icon.svg',
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10"/></svg>'
        );

        $response = $this->actingAs($this->admin())->postJson('/api/v1/assets', ['file' => $file]);

        $response->assertCreated();
        $response->assertJsonPath('data.mime_type', 'image/svg+xml');
        $response->assertJsonPath('data.width', null);

        $asset = Asset::first();
        $this->assertSame($asset->url, $response->json('data.thumbnail_url'));
        Storage::disk(config('assets.disk'))->assertMissing($asset->thumbnailPath());
    }

    public function test_store_does_not_generate_a_thumbnail_for_a_font_and_reuses_the_original_url(): void
    {
        $file = UploadedFile::fake()->create('font.woff2', 50, 'font/woff2');

        $response = $this->actingAs($this->admin())->postJson('/api/v1/assets', ['file' => $file]);

        $response->assertCreated();
        $response->assertJsonPath('data.mime_type', 'font/woff2');
        $response->assertJsonPath('data.width', null);

        $asset = Asset::first();
        $this->assertSame($asset->url, $response->json('data.thumbnail_url'));
        Storage::disk(config('assets.disk'))->assertMissing($asset->thumbnailPath());
    }

    public function test_store_rejects_a_disallowed_file_type(): void
    {
        $file = UploadedFile::fake()->create('malware.exe', 10, 'application/x-msdownload');

        $response = $this->actingAs($this->admin())->postJson('/api/v1/assets', ['file' => $file]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('file');
    }

    public function test_store_rejects_an_oversized_file(): void
    {
        config(['assets.max_size_kb' => 100]);
        $file = UploadedFile::fake()->image('huge.png')->size(200);

        $response = $this->actingAs($this->admin())->postJson('/api/v1/assets', ['file' => $file]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('file');
    }

    public function test_store_rejects_dimensions_over_the_configured_maximum(): void
    {
        config(['assets.max_dimension_px' => 500]);
        $file = UploadedFile::fake()->image('huge.png', 1000, 1000);

        $response = $this->actingAs($this->admin())->postJson('/api/v1/assets', ['file' => $file]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('file');
        $this->assertSame(0, Asset::count());
    }

    public function test_store_writes_nothing_to_disk_or_the_database_when_thumbnailing_fails(): void
    {
        // getimagesize() only reads the IHDR chunk (width/height), so a
        // PNG with a valid header but a corrupted IDAT stream passes
        // dimension validation yet fails when GD actually tries to decode
        // it — the exact failure this test reproduces (found live: an
        // upload succeeded past validation, then threw partway through
        // processing, orphaning the original file on disk with no Asset
        // row pointing at it, since the original write happened before
        // the thumbnail step that failed).
        $file = UploadedFile::fake()->createWithContent('corrupt.png', $this->corruptPngWithValidHeader());

        $response = $this->actingAs($this->admin())->postJson('/api/v1/assets', ['file' => $file]);

        $response->assertServerError();
        $this->assertSame(0, Asset::count());
        Storage::disk(config('assets.disk'))->assertDirectoryEmpty('assets');
    }

    private function corruptPngWithValidHeader(): string
    {
        $chunk = function (string $tag, string $data) {
            return pack('N', strlen($data)).$tag.$data.pack('N', crc32($tag.$data));
        };

        $signature = "\x89PNG\r\n\x1a\n";
        $ihdr = $chunk('IHDR', pack('NNCCCCC', 10, 10, 8, 2, 0, 0, 0));
        $corruptIdat = $chunk('IDAT', 'not-a-real-deflate-stream');
        $iend = $chunk('IEND', '');

        return $signature.$ihdr.$corruptIdat.$iend;
    }

    public function test_destroy_requires_the_admin_role(): void
    {
        $asset = Asset::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)->deleteJson("/api/v1/assets/{$asset->id}")->assertForbidden();
        $this->assertDatabaseHas('assets', ['id' => $asset->id]);
    }

    public function test_destroy_removes_the_asset_and_its_files_from_disk(): void
    {
        $file = UploadedFile::fake()->image('banner.png', 800, 400);
        $admin = $this->admin();

        $created = $this->actingAs($admin)->postJson('/api/v1/assets', ['file' => $file]);
        $asset = Asset::find($created->json('data.id'));

        $response = $this->actingAs($admin)->deleteJson("/api/v1/assets/{$asset->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('assets', ['id' => $asset->id]);
        Storage::disk(config('assets.disk'))->assertMissing($asset->disk_path);
        Storage::disk(config('assets.disk'))->assertMissing($asset->thumbnailPath());
    }
}
