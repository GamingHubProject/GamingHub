<?php

namespace App\Manager;

use App\Models\InstalledPackage;

/**
 * Ties Manager's pieces together for an actual install: fetch the registry,
 * find the package, download+verify+extract it, read its own manifest, check
 * that manifest's requires against what's already installed, and record the
 * result. Does not load any package code — see InstalledPackage's docblock.
 */
class PackageInstaller
{
    public function __construct(
        protected HttpClientContract $http,
        protected PackageDownloader $downloader,
        protected VersionResolver $resolver,
    ) {}

    /**
     * @return array{status: string, message: string, package: ?InstalledPackage}
     */
    public function install(string $registryUrl, string $packageId, string $version): array
    {
        $registry = PackageRegistry::fromJson($this->http->get($registryUrl));

        $extension = $registry->find($packageId);

        if (! $extension) {
            return $this->failure("Package [{$packageId}] was not found in this registry.");
        }

        $destination = storage_path('app/packages/'.$extension->id);

        try {
            $this->downloader->install($extension, $version, $destination);
        } catch (\Throwable $e) {
            return $this->failure("Download failed: {$e->getMessage()}");
        }

        $manifest = PackageManifest::fromPackageDirectory($destination);

        $dependencyCheck = $this->resolver->checkRequirements(
            $manifest?->requires ?? [],
            $this->installedVersions(),
        );

        if (! $dependencyCheck->satisfied()) {
            $this->removeDirectory($destination);

            $problems = array_merge(
                array_map(fn ($id) => "missing dependency [{$id}]", $dependencyCheck->missing),
                array_map(
                    fn ($id, $info) => "[{$id}] requires {$info['constraint']}, have {$info['installed']}",
                    array_keys($dependencyCheck->mismatched),
                    $dependencyCheck->mismatched,
                ),
            );

            return $this->failure('Dependency check failed: '.implode('; ', $problems));
        }

        $package = InstalledPackage::updateOrCreate(
            ['slug' => $extension->id],
            [
                'name' => $extension->name,
                'version' => $manifest?->version ?? $version,
                'status' => 'disabled',
                'description' => $extension->description,
                'manifest' => $manifest?->requires ?? [],
                'installed_at' => now(),
            ],
        );

        return [
            'status' => 'ok',
            'message' => "Installed {$extension->name} v{$package->version}. Enable it from the list to activate.",
            'package' => $package,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function installedVersions(): array
    {
        return array_merge(
            [
                'php' => PHP_VERSION,
                'gaming-hub-platform' => config('app.version'),
            ],
            InstalledPackage::query()->pluck('version', 'slug')->all(),
        );
    }

    protected function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (array_diff(scandir($dir), ['.', '..']) as $entry) {
            $path = $dir.'/'.$entry;
            is_dir($path) ? $this->removeDirectory($path) : @unlink($path);
        }

        @rmdir($dir);
    }

    /**
     * @return array{status: string, message: string, package: null}
     */
    protected function failure(string $message): array
    {
        return ['status' => 'error', 'message' => $message, 'package' => null];
    }
}
