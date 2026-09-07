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

/**
 * Uploading is the one write in this app a non-admin can now perform, so
 * every clause of the exception gets its own test: the folder, the
 * ownership, the format, and what happens to the files afterwards.
 */
class AvatarUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('assets.disk'));
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
    }

    private function avatarsFolder(User $as): AssetFolder
    {
        $this->actingAs($as)->getJson('/api/v1/asset-folders/avatars')->assertOk();

        return AssetFolder::where('slug', 'avatars')->firstOrFail();
    }

    private function upload(User $as, array $overrides = []): \Illuminate\Testing\TestResponse
    {
        $folder = $this->avatarsFolder($as);

        return $this->actingAs($as)->postJson('/api/v1/assets', array_merge([
            'file' => UploadedFile::fake()->image('me.png', 256, 256),
            'folder_id' => $folder->id,
            'owner_type' => 'User',
            'owner_id' => $as->id,
        ], $overrides));
    }

    // --- The folder ---

    public function test_the_avatars_folder_is_created_on_demand_and_is_public(): void
    {
        $folder = $this->avatarsFolder(User::factory()->create());

        $this->assertSame('public', $folder->visibility);
        $this->assertNull($folder->parent_id);
    }

    public function test_an_anonymous_visitor_cannot_ask_for_the_avatars_folder(): void
    {
        $this->getJson('/api/v1/asset-folders/avatars')->assertUnauthorized();
    }

    public function test_the_folder_is_the_same_one_every_time(): void
    {
        $first = $this->avatarsFolder(User::factory()->create());
        $second = $this->avatarsFolder(User::factory()->create());

        $this->assertSame($first->id, $second->id);
        // Counting avatars folders specifically, not folders overall — the
        // built-in themes each seed their own.
        $this->assertSame(1, AssetFolder::where('slug', 'avatars')->count());
    }

    // --- The upload ---

    public function test_a_person_can_upload_their_own_avatar(): void
    {
        $user = User::factory()->create();

        $response = $this->upload($user);

        $response->assertCreated();
        $this->assertDatabaseHas('assets', ['owner_type' => 'User', 'owner_id' => $user->id, 'uploaded_by' => $user->id]);
    }

    public function test_a_person_cannot_upload_an_asset_owned_by_somebody_else(): void
    {
        $user = User::factory()->create();
        $victim = User::factory()->create();

        $this->upload($user, ['owner_id' => $victim->id])->assertForbidden();
        $this->assertDatabaseCount('assets', 0);
    }

    public function test_a_person_cannot_upload_outside_the_avatars_folder(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $this->actingAs($admin)->getJson('/api/v1/asset-folders/fonts')->assertOk();
        $fonts = AssetFolder::where('slug', 'fonts')->firstOrFail();

        $this->upload($user, ['folder_id' => $fonts->id])->assertForbidden();
    }

    public function test_a_person_cannot_upload_to_the_library_root(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/v1/assets', [
            'file' => UploadedFile::fake()->image('me.png'),
            'owner_type' => 'User',
            'owner_id' => $user->id,
        ])->assertForbidden();
    }

    public function test_a_person_cannot_attach_curation_tags_while_uploading(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $tag = $this->actingAs($admin)->postJson('/api/v1/asset-tags', ['name' => 'Featured'])->json('data.id');

        $this->upload($user, ['tag_ids' => [$tag]])->assertForbidden();
    }

    /**
     * An SVG is an image the browser will execute script from, and these
     * files are served from the app's own origin — so it stays admin-only
     * even though the library accepts it in general.
     */
    public function test_a_person_cannot_upload_an_svg_avatar(): void
    {
        $user = User::factory()->create();

        $this->upload($user, [
            'file' => UploadedFile::fake()->createWithContent('me.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
        ])->assertStatus(422);
    }

    public function test_an_admin_can_still_upload_an_svg(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin)->postJson('/api/v1/assets', [
            'file' => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'),
        ])->assertCreated();
    }

    /**
     * Config lowered rather than a megabyte-sized fake generated — the
     * same trick AssetApiTest's oversized-file test uses, because these
     * fakes are held in memory and the suite runs in one process.
     */
    public function test_an_avatar_is_held_to_its_own_smaller_size_cap(): void
    {
        $user = User::factory()->create();
        config(['assets.avatar_max_size_kb' => 100, 'assets.max_size_kb' => 5120]);

        $this->upload($user, ['file' => UploadedFile::fake()->image('huge.png')->size(200)])->assertStatus(422);
    }

    // --- Afterwards ---

    /**
     * A non-admin cannot delete assets, so a cap with an error message
     * would strand them. Pruning keeps it bounded instead — and never
     * removes the picture their profile is currently showing.
     */
    public function test_uploading_again_clears_out_what_it_replaced(): void
    {
        $user = User::factory()->create();

        $first = $this->upload($user)->json('data.id');
        $user->update(['avatar_asset_id' => $first]);

        $second = $this->upload($user)->json('data.id');
        $this->assertDatabaseHas('assets', ['id' => $first]);
        $this->assertDatabaseHas('assets', ['id' => $second]);

        // Third upload: the first is no longer in use (the profile moved
        // to the second) and no longer the newest, so it goes.
        $user->update(['avatar_asset_id' => $second]);
        $third = $this->upload($user)->json('data.id');

        $this->assertDatabaseMissing('assets', ['id' => $first]);
        $this->assertDatabaseHas('assets', ['id' => $second]);
        $this->assertDatabaseHas('assets', ['id' => $third]);
    }

    public function test_pruning_never_touches_somebody_elses_avatar(): void
    {
        $one = User::factory()->create();
        $two = User::factory()->create();

        $theirs = $this->upload($two)->json('data.id');
        $this->upload($one);
        $this->upload($one);

        $this->assertDatabaseHas('assets', ['id' => $theirs]);
    }

    public function test_pruning_deletes_the_file_as_well_as_the_row(): void
    {
        $user = User::factory()->create();

        $first = Asset::find($this->upload($user)->json('data.id'));
        $path = $first->disk_path;
        $this->upload($user);

        Storage::disk(config('assets.disk'))->assertMissing($path);
    }
}
