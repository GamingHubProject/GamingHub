<?php

namespace App\Manager;

use Composer\Semver\Semver;

/**
 * Wraps composer/semver to answer two questions: does a version satisfy a
 * constraint, and does a set of installed package versions satisfy a
 * package's "requires"? Takes a plain requires array rather than an
 * ExtensionDefinition — the requires being checked normally come from a
 * PackageManifest (the package's own declaration), not the registry.
 */
final class VersionResolver
{
    public function satisfies(string $version, string $constraint): bool
    {
        return Semver::satisfies($version, $constraint);
    }

    /**
     * @param  array<string, string>  $requires  package id => version constraint
     * @param  array<string, string>  $installedVersions  package id => installed version
     */
    public function checkRequirements(array $requires, array $installedVersions): DependencyCheckResult
    {
        $missing = [];
        $mismatched = [];

        foreach ($requires as $packageId => $constraint) {
            if (! array_key_exists($packageId, $installedVersions)) {
                $missing[] = $packageId;

                continue;
            }

            $installed = $installedVersions[$packageId];

            if (! $this->satisfies($installed, $constraint)) {
                $mismatched[$packageId] = ['constraint' => $constraint, 'installed' => $installed];
            }
        }

        return new DependencyCheckResult($missing, $mismatched);
    }
}
