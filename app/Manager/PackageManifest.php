<?php

namespace App\Manager;

use InvalidArgumentException;
use JsonException;

/**
 * A package's own manifest — gaming-hub-extension.json, shipped inside its
 * release zip at the package root. This is the authoritative source for
 * what a package actually requires; the registry only helps you find and
 * download it in the first place.
 */
final class PackageManifest
{
    public const FILENAME = 'gaming-hub-extension.json';

    /**
     * @param  array<string, string>  $requires  package id => version constraint
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $version,
        public readonly array $requires = [],
    ) {}

    public static function fromJson(string $json): self
    {
        try {
            $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException(self::FILENAME.' is not valid JSON: '.$e->getMessage(), previous: $e);
        }

        foreach (['id', 'name', 'version'] as $required) {
            if (empty($data[$required])) {
                throw new InvalidArgumentException(self::FILENAME." is missing required field [{$required}].");
            }
        }

        return new self(
            id: $data['id'],
            name: $data['name'],
            version: $data['version'],
            requires: $data['requires'] ?? [],
        );
    }

    /**
     * Reads the manifest from an already-extracted package directory.
     * Returns null if the package doesn't ship one (treated as "no
     * declared requirements" rather than an error).
     */
    public static function fromPackageDirectory(string $directory): ?self
    {
        $path = rtrim($directory, '/').'/'.self::FILENAME;

        if (! is_file($path)) {
            return null;
        }

        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw new InvalidArgumentException("Could not read manifest at [{$path}].");
        }

        return self::fromJson($contents);
    }
}
