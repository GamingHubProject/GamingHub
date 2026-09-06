<?php

namespace App\Manager;

use App\Experience\ThemePackage;
use App\Experience\ThemePackageException;
use App\Models\InstalledPackage;
use App\Models\Theme;

/**
 * Installs a theme package from a registry.
 *
 * A theme takes the same road as any other package right up to the point
 * where they genuinely differ: same registry, same release, same
 * non-negotiable checksum verification. Then it forks, because a theme
 * isn't code. It doesn't belong in storage/app/packages waiting to be
 * loaded; it belongs on the assets disk as a theme folder, which is what
 * ThemePackage already knows how to build from an archive.
 *
 * Handing ThemePackage the verified ZIP rather than an extracted directory
 * is the point of the split. Its import validates every entry and writes
 * only to paths it builds itself, so a hostile archive from a registry is
 * refused before a byte lands — where extracting first would mean trusting
 * the archive's own paths to decide where files go.
 *
 * An InstalledPackage row is still written, because that record is what
 * makes the Browse Registry page show "installed v1.0.0" and offer an
 * update. It carries kind='theme' so the installed list doesn't offer it
 * an enable/disable toggle it has no use for, and it remembers the slug of
 * the theme it created — see updateTarget().
 */
class ThemeInstaller
{
    public function __construct(
        private readonly PackageDownloader $downloader,
        private readonly ThemePackage $packages,
    ) {}

    /**
     * @return array{status: string, message: string, package: ?InstalledPackage}
     */
    public function install(ExtensionDefinition $extension, string $version): array
    {
        try {
            $zip = $this->downloader->fetchVerified($extension, $version);
        } catch (\Throwable $e) {
            return $this->failure("Download failed: {$e->getMessage()}");
        }

        try {
            $record = InstalledPackage::where('slug', $extension->id)->first();
            $target = $this->updateTarget($record);

            $theme = $this->packages->import(
                $zip,
                /*
                 * A fresh install takes the name from the package's own
                 * theme.json (passing null). A registry entry's `name` is a
                 * listing title chosen by whoever wrote the registry, and
                 * the two are allowed to disagree — a theme should be called
                 * what its author called it, not what a catalogue calls it.
                 *
                 * An update keeps the name the theme already has. Renaming
                 * is a deliberate local customisation the admin made through
                 * the UI, and an update that silently undid it would be
                 * taking something away in exchange for a version bump. The
                 * package owns the theme's contents; the admin owns its
                 * label — the same division that already keeps the folder
                 * slug stable across a rename.
                 */
                $target?->name,
                'copy',
                $target,
            );
        } catch (ThemePackageException $e) {
            return $this->failure($e->getMessage());
        } finally {
            @unlink($zip);
        }

        $bundle = $theme->bundle();

        $package = InstalledPackage::updateOrCreate(
            ['slug' => $extension->id],
            [
                'kind' => InstalledPackage::KIND_THEME,
                'name' => $extension->name,
                // The version that matters is the one the theme declares,
                // not the release tag it happened to be published under.
                'version' => $bundle->version ?: $version,
                'status' => InstalledPackage::STATUS_INSTALLED,
                'description' => $extension->description,
                'manifest' => ['theme_slug' => $theme->slug],
                'installed_at' => now(),
            ],
        );

        return [
            'status' => 'ok',
            'message' => $target
                ? "Updated {$theme->name} to v{$package->version}. It stays applied wherever it already was."
                : "Installed {$theme->name} v{$package->version}. Apply it from Appearance → Themes when you want it live.",
            'package' => $package,
        ];
    }

    /**
     * The theme a re-install should overwrite, or null for a fresh one.
     *
     * Recorded by slug rather than looked up by name: an admin is free to
     * rename an installed theme, and an update that matched on name would
     * then quietly install a second copy and leave the renamed original
     * applied — the exact opposite of what "update" means. If the theme has
     * been deleted outright, this returns null and the install proceeds as
     * a fresh one.
     */
    private function updateTarget(?InstalledPackage $record): ?Theme
    {
        $slug = $record?->manifest['theme_slug'] ?? null;

        return $slug ? Theme::where('slug', $slug)->first() : null;
    }

    /**
     * @return array{status: string, message: string, package: null}
     */
    private function failure(string $message): array
    {
        return ['status' => 'error', 'message' => $message, 'package' => null];
    }
}
