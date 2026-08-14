<?php

namespace App\Manager;

use Composer\Semver\Semver;

/**
 * Wraps composer/semver to answer two questions: does a version satisfy a
 * constraint, and does a set of installed package versions satisfy an
 * extension's "requires"?
 */
final class VersionResolver
{
    public function satisfies(string $version, string $constraint): bool
    {
        return Semver::satisfies($version, $constraint);
    }

    /**
     * @param  array<string, string>  $installedVersions  package id => installed version
     */
    public function checkRequirements(ExtensionDefinition $extension, array $installedVersions): DependencyCheckResult
    {
        $missing = [];
        $mismatched = [];

        foreach ($extension->requires as $packageId => $constraint) {
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
