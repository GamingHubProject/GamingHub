<?php

namespace App\Manager;

/**
 * One entry from a registry file — enough metadata to find and download a
 * package. Deliberately does NOT carry dependency constraints: those live
 * in the package's own PackageManifest (shipped inside its release zip),
 * the same way a Composer package declares its own require block instead
 * of a central index declaring it on the package's behalf. A registry
 * entry can go stale relative to what a package actually needs; the
 * manifest inside the release you just downloaded cannot.
 */
final class ExtensionDefinition
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $description,
        public readonly string $author,
        public readonly string $category,
        public readonly string $repository,
        public readonly string $releaseAsset,
        public readonly string $checksumAsset,
        public readonly bool $verified,
        public readonly bool $official,
    ) {}

    public static function fromArray(array $data): self
    {
        foreach (['id', 'name', 'repository', 'release_asset'] as $required) {
            if (empty($data[$required])) {
                throw new \InvalidArgumentException("Registry extension entry is missing required field [{$required}].");
            }
        }

        return new self(
            id: $data['id'],
            name: $data['name'],
            description: $data['description'] ?? '',
            author: $data['author'] ?? '',
            category: $data['category'] ?? '',
            repository: $data['repository'],
            releaseAsset: $data['release_asset'],
            checksumAsset: $data['checksum_asset'] ?? 'SHA256SUMS',
            verified: $data['verified'] ?? false,
            official: $data['official'] ?? false,
        );
    }
}
