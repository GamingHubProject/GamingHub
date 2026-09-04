<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\SiteOptions;
use App\Models\SiteOption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SiteOptionsTest extends TestCase
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

    public function test_the_form_loads_prefilled_with_current_values(): void
    {
        SiteOption::current()->update(['values' => ['site_name' => 'My Hub', 'timezone' => 'UTC']]);

        Livewire::test(SiteOptions::class)
            ->assertFormSet(['site_name' => 'My Hub']);
    }

    public function test_saving_the_form_persists_every_field(): void
    {
        Livewire::test(SiteOptions::class)
            ->fillForm([
                'site_name' => 'Gaming Hub',
                'site_description' => 'A place for gamers',
                'site_url' => 'https://gaminghub.example',
                'timezone' => 'Europe/Berlin',
                'admin_email' => 'admin@example.com',
                'discord_webhook' => 'https://discord.com/api/webhooks/x',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $values = SiteOption::current()->values;
        $this->assertSame('Gaming Hub', $values['site_name']);
        $this->assertSame('https://gaminghub.example', $values['site_url']);
        $this->assertSame('Europe/Berlin', $values['timezone']);
        $this->assertSame('admin@example.com', $values['admin_email']);
    }

    public function test_site_url_must_be_a_valid_url(): void
    {
        Livewire::test(SiteOptions::class)
            ->fillForm(['site_name' => 'Hub', 'timezone' => 'UTC', 'site_url' => 'not-a-url'])
            ->call('save')
            ->assertHasFormErrors(['site_url']);
    }

    public function test_admin_email_must_be_a_valid_email(): void
    {
        Livewire::test(SiteOptions::class)
            ->fillForm(['site_name' => 'Hub', 'timezone' => 'UTC', 'admin_email' => 'not-an-email'])
            ->call('save')
            ->assertHasFormErrors(['admin_email']);
    }

    public function test_appearance_settings_are_no_longer_part_of_site_options(): void
    {
        // Colours, font, favicon, header style and widget defaults moved
        // onto the Theme record — Site Options is branding only now, and
        // saving it must not resurrect the old keys.
        Livewire::test(SiteOptions::class)
            ->fillForm(['site_name' => 'Hub', 'timezone' => 'UTC'])
            ->call('save')
            ->assertHasNoFormErrors();

        $values = SiteOption::current()->values;
        foreach (['widget_style_defaults', 'font_asset_id', 'favicon_asset_id', 'header_transparent'] as $moved) {
            $this->assertArrayNotHasKey($moved, $values);
        }
    }
}
