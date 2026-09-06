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
    /** Code, installed into storage/app/packages and recorded for loading. */
    public const KIND_EXTENSION = 'extension';

    /** A theme package — unpacked onto the assets disk as a Theme folder. */
    public const KIND_THEME = 'theme';

    public const KINDS = [self::KIND_EXTENSION, self::KIND_THEME];

    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $description,
        public readonly string $author,
        public readonly string $category,
        /**
         * What this package IS, which decides where it gets installed —
         * not the same thing as `category`, which is a display label an
         * author picks freely. Behaviour must not hang off a label
         * somebody can rename, so it hangs off this instead.
         */
        public readonly string $kind,
        public readonly string $repository,
        public readonly string $releaseAsset,
        public readonly string $checksumAsset,
        public readonly bool $verified,
        public readonly bool $official,
    ) {}

    public function isTheme(): bool
    {
        return $this->kind === self::KIND_THEME;
    }

    public static function fromArray(array $data): self
    {
        foreach (['id', 'name', 'repository', 'release_asset'] as $required) {
            if (empty($data[$required])) {
                throw new \InvalidArgumentException("Registry extension entry is missing required field [{$required}].");
            }
        }

        if (isset($data['kind']) && ! in_array($data['kind'], self::KINDS, true)) {
            throw new \InvalidArgumentException(
                "Registry entry [{$data['id']}] declares kind [{$data['kind']}], which this version doesn't install. It knows: ".implode(', ', self::KINDS).'.'
            );
        }

        return new self(
            id: $data['id'],
            name: $data['name'],
            description: $data['description'] ?? '',
            author: $data['author'] ?? '',
            category: $data['category'] ?? '',
            // Absent means 'extension', so every registry entry written
            // before themes existed keeps meaning exactly what it did.
            kind: $data['kind'] ?? self::KIND_EXTENSION,
            repository: $data['repository'],
            releaseAsset: $data['release_asset'],
            checksumAsset: $data['checksum_asset'] ?? 'SHA256SUMS',
            verified: $data['verified'] ?? false,
            official: $data['official'] ?? false,
        );
    }
}
