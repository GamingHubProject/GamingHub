<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\RoleResource\Pages\CreateRole;
use App\Filament\Resources\RoleResource\Pages\EditRole;
use App\Filament\Resources\RoleResource\Pages\ListRoles;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\User;
use App\Permissions\ScopedPermissionName;
use GamingHub\Core\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserAndRoleResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $this->actingAs($admin);
    }

    public function test_can_list_users(): void
    {
        $response = $this->get('/admin/system/users');

        $response->assertStatus(200);
    }

    public function test_users_index_component_renders(): void
    {
        User::factory()->count(3)->create();

        Livewire::test(ListUsers::class)
            ->assertSuccessful();
    }

    public function test_can_assign_role_to_user(): void
    {
        $user = User::factory()->create();

        $user->assignRole('Admin');

        $this->assertTrue($user->fresh()->hasRole('Admin'));
    }

    // --- What an admin editing somebody else's row can put in it ---

    public function test_the_user_form_stores_only_allowlisted_preferences(): void
    {
        $user = User::factory()->create([
            // Left behind by the raw key/value editor this form used to
            // expose: a key nothing validates and nothing reads.
            'preferences' => ['account_placement' => 'sidebar', 'is_admin' => true],
        ]);

        Livewire::test(EditUser::class, ['record' => $user->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['account_placement' => 'sidebar'], $user->fresh()->preferences);
    }

    public function test_the_user_form_can_still_change_an_allowlisted_preference(): void
    {
        $user = User::factory()->create(['preferences' => ['account_placement' => 'sidebar']]);

        Livewire::test(EditUser::class, ['record' => $user->id])
            ->fillForm(['preferences' => ['account_placement' => 'header']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['account_placement' => 'header'], $user->fresh()->preferences);
    }

    public function test_clearing_a_preference_removes_the_key_rather_than_storing_a_null(): void
    {
        $user = User::factory()->create(['preferences' => ['account_placement' => 'sidebar']]);

        Livewire::test(EditUser::class, ['record' => $user->id])
            ->fillForm(['preferences' => ['account_placement' => null]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([], $user->fresh()->preferences);
    }

    public function test_the_bio_field_stores_no_markup(): void
    {
        $user = User::factory()->create();

        Livewire::test(EditUser::class, ['record' => $user->id])
            ->fillForm(['bio' => '<script>alert(1)</script>Hello <b>there</b>'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('alert(1)Hello there', $user->fresh()->bio);
    }

    public function test_a_password_set_in_the_form_is_hashed_exactly_once(): void
    {
        $user = User::factory()->create();

        Livewire::test(EditUser::class, ['record' => $user->id])
            ->fillForm(['password' => 'correct-horse-battery'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(Hash::check('correct-horse-battery', $user->fresh()->password));
    }

    public function test_creating_a_user_without_touching_preferences_stores_an_empty_set(): void
    {
        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Rae',
                'email' => 'rae@example.test',
                'password' => 'correct-horse-battery',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $created = User::where('email', 'rae@example.test')->firstOrFail();
        $this->assertSame([], $created->preferences);
        $this->assertTrue(Hash::check('correct-horse-battery', $created->password));
    }

    public function test_can_list_roles(): void
    {
        Role::create(['name' => 'WebEditor', 'guard_name' => 'web']);

        Livewire::test(ListRoles::class)
            ->assertSuccessful();
    }

    public function test_can_create_a_role_with_a_game_level_grant(): void
    {
        $palworld = Game::factory()->create();

        Livewire::test(CreateRole::class)
            ->fillForm([
                'name' => 'Palworld Editor',
                'guard_name' => 'web',
                "scoped_game_{$palworld->id}" => [
                    ScopedPermissionName::for('game', $palworld->id, 'settings'),
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $role = Role::where('name', 'Palworld Editor')->firstOrFail();
        $this->assertTrue($role->hasPermissionTo(ScopedPermissionName::for('game', $palworld->id, 'settings')));
    }

    public function test_editing_a_role_replaces_its_scoped_permissions(): void
    {
        $palworld = Game::factory()->create();
        $ark = Game::factory()->create();
        $role = Role::create(['name' => 'Scoped Editor', 'guard_name' => 'web']);
        $role->givePermissionTo(ScopedPermissionName::for('game', $palworld->id, 'settings'));

        Livewire::test(EditRole::class, ['record' => $role->id])
            ->fillForm([
                "scoped_game_{$palworld->id}" => [],
                "scoped_game_{$ark->id}" => [
                    ScopedPermissionName::for('game', $ark->id, 'settings'),
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $role = $role->fresh();
        $this->assertFalse($role->hasPermissionTo(ScopedPermissionName::for('game', $palworld->id, 'settings')));
        $this->assertTrue($role->hasPermissionTo(ScopedPermissionName::for('game', $ark->id, 'settings')));
    }

    public function test_a_server_group_level_grant_shows_as_checked_when_editing(): void
    {
        $palworld = Game::factory()->create();
        $role = Role::create(['name' => 'Scoped Editor', 'guard_name' => 'web']);
        $role->givePermissionTo(ScopedPermissionName::for('game', $palworld->id, 'page'));

        Livewire::test(EditRole::class, ['record' => $role->id])
            ->assertFormSet([
                "scoped_game_{$palworld->id}" => [
                    ScopedPermissionName::for('game', $palworld->id, 'page'),
                ],
            ]);
    }
}
