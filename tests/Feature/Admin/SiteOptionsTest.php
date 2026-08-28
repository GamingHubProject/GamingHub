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

    public function test_saving_persists_the_widget_style_defaults_as_a_nested_object(): void
    {
        Livewire::test(SiteOptions::class)
            ->fillForm([
                'site_name' => 'Hub',
                'timezone' => 'UTC',
                'widget_style_defaults' => [
                    'border_enabled' => true,
                    'border_thickness' => 2,
                    'border_color' => '#00ff00',
                    'border_radius' => 12,
                    'text_size' => 16,
                    'text_color' => '#ff0000',
                    'text_scale' => 1.2,
                    'background_color' => '#000000',
                    'background_opacity' => 0.5,
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $values = SiteOption::current()->values;
        $this->assertSame(true, $values['widget_style_defaults']['border_enabled']);
        $this->assertSame('#00ff00', $values['widget_style_defaults']['border_color']);
        $this->assertSame(12, $values['widget_style_defaults']['border_radius']);
        $this->assertSame(1.2, $values['widget_style_defaults']['text_scale']);
        $this->assertSame(2, $values['widget_style_defaults']['border_thickness']);
        $this->assertSame('#ff0000', $values['widget_style_defaults']['text_color']);
        $this->assertSame(0.5, $values['widget_style_defaults']['background_opacity']);
    }
}
