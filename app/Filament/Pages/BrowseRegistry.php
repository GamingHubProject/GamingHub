<?php

namespace App\Filament\Pages;

use App\Manager\HttpClientContract;
use App\Manager\PackageInstaller;
use App\Manager\PackageRegistry;
use App\Models\InstalledPackage;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Throwable;

/**
 * The actual "can I see what's installable and click Install" page — real
 * package discovery instead of typing an exact package ID blind into a
 * form. Fetches a registry live, shows what's there, and what's already
 * installed.
 */
class BrowseRegistry extends Page implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $navigationGroup = 'Extensions';

    protected static ?string $navigationLabel = 'Browse Registry';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.browse-registry';

    public string $registryUrl = 'https://raw.githubusercontent.com/GamingHubProject/Registry/main/extension_registry.json';

    public array $packages = [];

    public ?string $error = null;

    public function mount(): void
    {
        $this->refreshRegistry();
    }

    public function refreshRegistry(): void
    {
        try {
            $registry = PackageRegistry::fromJson(app(HttpClientContract::class)->get($this->registryUrl));
            $this->error = null;
        } catch (Throwable $e) {
            $this->packages = [];
            $this->error = $e->getMessage();

            return;
        }

        $installed = InstalledPackage::query()->pluck('version', 'slug');

        $this->packages = collect($registry->all())
            ->map(fn ($extension) => [
                'id' => $extension->id,
                'name' => $extension->name,
                'description' => $extension->description,
                'category' => $extension->category,
                'official' => $extension->official,
                'installedVersion' => $installed->get($extension->id),
            ])
            ->values()
            ->all();
    }

    public function installAction(): Action
    {
        return Action::make('install')
            ->label('Install')
            ->form([
                TextInput::make('version')
                    ->required()
                    ->helperText('The exact release tag to install, e.g. "0.1.000" — no "latest" guessing.'),
            ])
            ->action(function (array $data, array $arguments, PackageInstaller $installer): void {
                $result = $installer->install($this->registryUrl, $arguments['packageId'], $data['version']);

                Notification::make()
                    ->title($result['status'] === 'ok' ? 'Package installed' : 'Install failed')
                    ->body($result['message'])
                    ->{$result['status'] === 'ok' ? 'success' : 'danger'}()
                    ->send();

                $this->refreshRegistry();
            });
    }
}
