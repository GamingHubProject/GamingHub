<?php

namespace App\Manager;

use App\Connectors\ConnectorRegistry;
use App\Models\InstalledPackage;
use GamingHub\Core\Normalizers\NormalizerRegistry;
use RuntimeException;

/**
 * Loads and registers every enabled Connector package's PHP code — into
 * ConnectorRegistry for its connector class, and into NormalizerRegistry
 * for any normalizer(s) it ships alongside it. Manager could always
 * download and record a package (PackageInstaller), but nothing ever
 * required its code until this: "enable" now actually makes an extension's
 * connector (and its normalizers) usable. A package is a Connector if the
 * directory Manager installed it into ships a connector.json — presence on
 * disk decides, not a database flag, the same convention PackageManifest
 * already uses for gaming-hub-extension.json (missing file = "not
 * applicable", not an error).
 *
 * connector.json:
 * {
 *   "name": "Pelican Connector",
 *   "class": "Vendor\\Namespace\\PelicanConnector",
 *   "autoload": {"prefix": "Vendor\\Namespace\\", "path": "src"},
 *   "normalizers": {"pelican-server-status": "Vendor\\Namespace\\PelicanServerStatusNormalizer"},
 *   "auth_schema": {...}, "endpoints": [...], "capabilities": [...]
 * }
 *
 * "autoload" is optional — a connector class already reachable via
 * Composer's own autoloader (true for RestConnector, which stays a
 * Platform-built-in rather than a package) doesn't need it; class_exists()
 * below just succeeds immediately. A genuinely external package supplies it
 * so its src/ files — connector class and any normalizer classes alike —
 * get pulled in via one scoped spl_autoload_register. "normalizers" is
 * optional too — a connector whose data needs no package-specific shaping,
 * or that relies entirely on Core's generic normalizers (field-mapping),
 * simply omits it.
 */
class PackageLoader
{
    public function __construct(
        protected ConnectorRegistry $connectors,
        protected NormalizerRegistry $normalizers,
    ) {}

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

        foreach ($manifest['normalizers'] ?? [] as $normalizerId => $normalizerClass) {
            if (! class_exists($normalizerClass)) {
                throw new RuntimeException("Connector package [{$slug}]'s normalizer class [{$normalizerClass}] for [{$normalizerId}] could not be loaded.");
            }

            $this->normalizers->register($normalizerId, app($normalizerClass));
        }
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
