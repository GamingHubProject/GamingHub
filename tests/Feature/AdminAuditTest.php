<?php

namespace Tests\Feature;

use App\Filament\Resources\AdminAuditResource;
use App\Filament\Resources\RoleResource\Pages\CreateRole;
use App\Filament\Resources\RoleResource\Pages\EditRole;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Models\AdminAudit;
use App\Models\ConnectorInstance;
use App\Models\ServerGroup;
use App\Models\SiteOption;
use App\Models\User;
use App\Permissions\ScopedPermissionName;
use GamingHub\Core\Models\Game;
use GamingHub\Core\Models\Provider;
use GamingHub\Core\Models\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminAuditTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    public function test_creating_a_server_group_while_authenticated_is_logged(): void
    {
        $this->actingAs($this->admin);
        $game = Game::factory()->create();

        $group = ServerGroup::create(['game_id' => $game->id, 'name' => 'Cluster A']);

        $audit = AdminAudit::where('resource_type', 'ServerGroup')->where('resource_id', $group->id)->first();
        $this->assertNotNull($audit);
        $this->assertSame('created', $audit->action);
        $this->assertSame($this->admin->id, $audit->user_id);
        $this->assertSame($this->admin->name, $audit->user_name);
        $this->assertSame($this->admin->email, $audit->user_email);
        $this->assertSame('Cluster A', $audit->changes['name']);
    }

    public function test_no_authenticated_user_means_nothing_is_logged(): void
    {
        // No actingAs() — simulates the scheduler's console context.
        $game = Game::factory()->create();
        ServerGroup::create(['game_id' => $game->id, 'name' => 'Cluster A']);

        $this->assertSame(0, AdminAudit::count());
    }

    public function test_updating_a_server_group_records_old_and_new_values(): void
    {
        $this->actingAs($this->admin);
        $group = ServerGroup::factory()->create(['name' => 'Original']);

        $group->update(['name' => 'Renamed']);

        $audit = AdminAudit::where('action', 'updated')
            ->where('resource_type', 'ServerGroup')
            ->where('resource_id', $group->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame(['old' => 'Original', 'new' => 'Renamed'], $audit->changes['name']);
    }

    public function test_deleting_a_connector_instance_is_logged_without_leaking_credentials(): void
    {
        $this->actingAs($this->admin);
        $instance = ConnectorInstance::create([
            'name' => 'Test Panel',
            'type' => 'pelican',
            'base_url' => 'https://panel.test',
            'credentials' => ['client_token' => 'super-secret-token'],
        ]);

        $instance->delete();

        $audit = AdminAudit::where('action', 'deleted')->where('resource_type', 'ConnectorInstance')->first();
        $this->assertNotNull($audit);
        $this->assertArrayNotHasKey('credentials', $audit->changes);
        $this->assertSame('Test Panel', $audit->changes['name']);
    }

    public function test_provider_telemetry_only_updates_are_not_logged(): void
    {
        $this->actingAs($this->admin);
        $server = Server::factory()->create();
        $provider = Provider::factory()->create(['server_id' => $server->id]);

        AdminAudit::query()->delete(); // clear the row from Provider::factory()->create() itself

        $provider->update([
            'last_check' => now(),
            'status' => 'connected',
            'error_message' => null,
            'last_raw_response' => ['ok' => true],
        ]);

        $this->assertSame(0, AdminAudit::where('resource_type', 'Provider')->count());
    }

    public function test_a_genuine_provider_config_edit_is_logged_alongside_a_telemetry_change(): void
    {
        $this->actingAs($this->admin);
        $server = Server::factory()->create();
        $provider = Provider::factory()->create(['server_id' => $server->id, 'priority' => 0]);

        $provider->update(['priority' => 5, 'status' => 'connected']);

        $audit = AdminAudit::where('resource_type', 'Provider')->where('resource_id', $provider->id)->first();
        $this->assertNotNull($audit);
        $this->assertArrayHasKey('priority', $audit->changes);
        $this->assertArrayNotHasKey('status', $audit->changes);
    }

    public function test_settings_changes_are_logged(): void
    {
        $this->actingAs($this->admin);
        $option = SiteOption::current();

        $option->update(['values' => ['site_name' => 'New Name']]);

        $audit = AdminAudit::where('resource_type', 'SiteOption')->first();
        $this->assertNotNull($audit);
        $this->assertSame('updated', $audit->action);
    }

    public function test_deleting_a_user_preserves_their_audit_trail(): void
    {
        $this->actingAs($this->admin);
        $target = User::factory()->create(['name' => 'Departing Admin', 'email' => 'gone@example.com']);
        $targetId = $target->id;

        $target->delete();

        $audit = AdminAudit::where('resource_type', 'User')->where('resource_id', $targetId)->first();
        $this->assertNotNull($audit);

        // The row survives even once the actor's own account is gone.
        $this->admin->delete();
        $audit->refresh();
        $this->assertNull($audit->user_id);
        $this->assertSame($this->admin->name, $audit->user_name);
        $this->assertSame($this->admin->email, $audit->user_email);
    }

    public function test_assigning_a_role_to_a_user_is_logged(): void
    {
        $this->actingAs($this->admin);
        $webEditor = Role::create(['name' => 'WebEditor', 'guard_name' => 'web']);
        $user = User::factory()->create();

        Livewire::test(EditUser::class, ['record' => $user->id])
            ->fillForm(['roles' => [$webEditor->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $audit = AdminAudit::where('action', 'role_changed')->where('resource_id', $user->id)->first();
        $this->assertNotNull($audit);
        $this->assertSame(['WebEditor'], $audit->changes['added']);
        $this->assertSame([], $audit->changes['removed']);
    }

    public function test_removing_a_role_from_a_user_is_logged(): void
    {
        $this->actingAs($this->admin);
        Role::create(['name' => 'WebEditor', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('WebEditor');

        Livewire::test(EditUser::class, ['record' => $user->id])
            ->fillForm(['roles' => []])
            ->call('save')
            ->assertHasNoFormErrors();

        $audit = AdminAudit::where('action', 'role_changed')->where('resource_id', $user->id)->first();
        $this->assertNotNull($audit);
        $this->assertSame([], $audit->changes['added']);
        $this->assertSame(['WebEditor'], $audit->changes['removed']);
    }

    public function test_creating_a_user_with_roles_is_logged(): void
    {
        $this->actingAs($this->admin);
        $webEditor = Role::create(['name' => 'WebEditor', 'guard_name' => 'web']);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'New Editor',
                'email' => 'editor@example.com',
                'password' => 'password123',
                'roles' => [$webEditor->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::where('email', 'editor@example.com')->firstOrFail();
        $audit = AdminAudit::where('action', 'role_changed')->where('resource_id', $user->id)->first();
        $this->assertNotNull($audit);
        $this->assertSame(['WebEditor'], $audit->changes['added']);
    }

    public function test_changing_a_roles_scoped_permissions_is_logged(): void
    {
        $this->actingAs($this->admin);
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

        $audit = AdminAudit::where('action', 'permissions_changed')->where('resource_id', $role->id)->first();
        $this->assertNotNull($audit);
        $this->assertContains(ScopedPermissionName::for('game', $ark->id, 'settings'), $audit->changes['added']);
        $this->assertContains(ScopedPermissionName::for('game', $palworld->id, 'settings'), $audit->changes['removed']);
    }

    public function test_creating_a_role_with_permissions_is_logged(): void
    {
        $this->actingAs($this->admin);
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
        $audit = AdminAudit::where('action', 'permissions_changed')->where('resource_id', $role->id)->first();
        $this->assertNotNull($audit);
        $this->assertContains(ScopedPermissionName::for('game', $palworld->id, 'settings'), $audit->changes['added']);
    }

    public function test_audit_log_page_loads_for_an_admin(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get(AdminAuditResource::getUrl('index'));

        $response->assertStatus(200);
    }
}
