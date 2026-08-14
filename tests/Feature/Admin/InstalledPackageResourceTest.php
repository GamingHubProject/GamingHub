<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\InstalledPackageResource\Pages\CreateInstalledPackage;
use App\Filament\Resources\InstalledPackageResource\Pages\ListInstalledPackages;
use App\Models\InstalledPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InstalledPackageResourceTest extends TestCase
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

    public function test_can_list_packages(): void
    {
        InstalledPackage::factory()->count(2)->create();

        Livewire::test(ListInstalledPackages::class)->assertSuccessful();
    }

    public function test_can_register_a_package_unbound_to_a_game(): void
    {
        Livewire::test(CreateInstalledPackage::class)
            ->fillForm([
                'slug' => 'maps',
                'name' => 'Maps',
                'version' => '0.1.000',
                'status' => 'disabled',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('installed_packages', [
            'slug' => 'maps',
            'game_id' => null,
        ]);
    }

    public function test_can_toggle_status(): void
    {
        $package = InstalledPackage::factory()->create(['status' => 'disabled']);

        $package->update(['status' => 'enabled']);

        $this->assertSame('enabled', $package->fresh()->status);
    }
}
