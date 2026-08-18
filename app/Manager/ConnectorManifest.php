<?php

namespace App\Manager;

use App\Models\InstalledPackage;

/**
 * Reads installed connector packages' connector.json manifests — used both
 * to find which package owns a given normalizer id (ConnectorBackedProvider,
 * deciding whether a package-owned normalizer is currently enabled) and to
 * look up a connector's recommended polling cadence for a normalizer
 * (ProvidersRelationManager, pre-filling the cadence field). One scan of
 * installed packages, not two independently maintained copies of the same
 * "find the manifest that declares this normalizer id" loop.
 */
class ConnectorManifest
{
    public function findOwningPackageSlug(string $normalizerId): ?string
    {
        foreach ($this->manifests() as $slug => $manifest) {
            if (isset($manifest['normalizers'][$normalizerId])) {
                return $slug;
            }
        }

        return null;
    }

    /**
     * A connector.json may optionally declare {"recommended_cadence_seconds":
     * {"<normalizer-id>": 30}} — absent for any package that doesn't, in
     * which case the caller's own generic default applies instead.
     */
    public function recommendedCadenceFor(string $normalizerId): ?int
    {
        foreach ($this->manifests() as $manifest) {
            if (isset($manifest['recommended_cadence_seconds'][$normalizerId])) {
                return (int) $manifest['recommended_cadence_seconds'][$normalizerId];
            }
        }

        return null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function manifests(): array
    {
        $manifests = [];

        foreach (InstalledPackage::all() as $package) {
            $manifestPath = storage_path("app/packages/{$package->slug}/connector.json");

            if (! is_file($manifestPath)) {
                continue;
            }

            $manifest = json_decode((string) file_get_contents($manifestPath), true);

            if (is_array($manifest)) {
                $manifests[$package->slug] = $manifest;
            }
        }

        return $manifests;
    }
}
