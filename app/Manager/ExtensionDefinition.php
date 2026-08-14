<?php

namespace App\Manager;

/**
 * One entry from a registry.json — metadata about a downloadable package,
 * not its installed state (that's InstalledPackage, tracked by whatever
 * consumes this library, e.g. Platform's own GameExtension model).
 */
final class ExtensionDefinition
{
    /**
     * @param  array<string, string>  $requires  package id => version constraint
     */
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
        public readonly array $requires = [],
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
            requires: $data['requires'] ?? [],
        );
    }
}
