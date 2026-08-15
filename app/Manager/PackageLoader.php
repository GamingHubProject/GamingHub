<?php

namespace App\Manager;

use App\Connectors\ConnectorRegistry;
use App\Models\InstalledPackage;
use RuntimeException;

/**
 * Loads and registers every enabled Connector package's PHP code into
 * ConnectorRegistry — the piece that was missing before this: Manager could
 * download and record a package (PackageInstaller), but nothing ever
 * required its code, so "enable" never actually made an extension's
 * connector usable. A package is a Connector if the directory Manager
 * installed it into ships a connector.json — presence on disk decides,
 * not a database flag, the same convention PackageManifest already uses
 * for gaming-hub-extension.json (missing file = "not applicable", not an
 * error).
 *
 * connector.json:
 * {
 *   "name": "Pelican Connector",
 *   "class": "Vendor\\Namespace\\PelicanConnector",
 *   "autoload": {"prefix": "Vendor\\Namespace\\", "path": "src"},
 *   "auth_schema": {...}, "endpoints": [...], "capabilities": [...]
 * }
 *
 * "autoload" is optional — a connector whose class already lives somewhere
 * Composer's own autoloader reaches (true today for the Pelican/REST
 * connectors still shipped inside Platform's app/Connectors/ while they're
 * mid-migration to real packages) doesn't need it; class_exists() below
 * just succeeds immediately. A genuinely external package supplies it so
 * its src/ files get pulled in via a scoped spl_autoload_register.
 */
class PackageLoader
{
    public function __construct(protected ConnectorRegistry $connectors) {}

    public function loadConnectorPackages(): void
    {
        foreach (InstalledPackage::where('status', 'enabled')->get() as $package) {
            $directory = storage_path('app/packages/'.$package->slug);
            $manifestPath = $directory.'/connector.json';

            if (! is_file($manifestPath)) {
                continue;
            }

            $this->loadConnectorPackage($package->slug, $directory, $manifestPath);
        }
    }

    protected function loadConnectorPackage(string $slug, string $directory, string $manifestPath): void
    {
        $manifest = json_decode((string) file_get_contents($manifestPath), true);

        if (! is_array($manifest) || empty($manifest['class'])) {
            throw new RuntimeException("Connector package [{$slug}]'s connector.json is missing or invalid — needs at least a \"class\".");
        }

        $class = $manifest['class'];

        if (! class_exists($class)) {
            $this->registerAutoloader($slug, $directory, $manifest['autoload'] ?? null);
        }

        if (! class_exists($class)) {
            throw new RuntimeException("Connector package [{$slug}]'s entry class [{$class}] could not be loaded.");
        }

        $this->connectors->register($class);
    }

    /**
     * @param  array{prefix?: string, path?: string}|null  $autoload
     */
    protected function registerAutoloader(string $slug, string $directory, ?array $autoload): void
    {
        if (empty($autoload['prefix'])) {
            throw new RuntimeException(
                "Connector package [{$slug}]'s entry class isn't autoloadable and connector.json has no \"autoload\" block to load it from."
            );
        }

        $prefix = rtrim($autoload['prefix'], '\\').'\\';
        $base = rtrim($directory.'/'.($autoload['path'] ?? 'src'), '/');

        spl_autoload_register(function (string $class) use ($prefix, $base) {
            if (! str_starts_with($class, $prefix)) {
                return;
            }

            $relative = substr($class, strlen($prefix));
            $file = $base.'/'.str_replace('\\', '/', $relative).'.php';

            if (is_file($file)) {
                require_once $file;
            }
        });
    }
}
