<?php

namespace App\Manager;

use InvalidArgumentException;
use JsonException;

/**
 * Parses a registry file (extension_registry.json / games_registry.json —
 * same "packages" array shape) into typed ExtensionDefinitions. This is
 * read-only metadata about what's available to install — it does not know
 * what's actually installed.
 */
final class PackageRegistry
{
    /** @var array<string, ExtensionDefinition> */
    private array $extensions = [];

    public function __construct(
        public readonly string $id,
        public readonly string $name,
    ) {}

    public static function fromJson(string $json): self
    {
        try {
            $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Registry file is not valid JSON: '.$e->getMessage(), previous: $e);
        }

        if (($data['schema'] ?? null) !== 1) {
            throw new InvalidArgumentException('Unsupported (or missing) registry schema version — expected schema 1.');
        }

        $registry = new self($data['id'] ?? '', $data['name'] ?? '');

        foreach ($data['packages'] ?? [] as $entry) {
            $registry->add(ExtensionDefinition::fromArray($entry));
        }

        return $registry;
    }

    public static function fromFile(string $path): self
    {
        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw new InvalidArgumentException("Could not read registry file at [{$path}].");
        }

        return self::fromJson($contents);
    }

    private function add(ExtensionDefinition $extension): void
    {
        $this->extensions[$extension->id] = $extension;
    }

    public function find(string $id): ?ExtensionDefinition
    {
        return $this->extensions[$id] ?? null;
    }

    public function has(string $id): bool
    {
        return isset($this->extensions[$id]);
    }

    /** @return array<string, ExtensionDefinition> */
    public function all(): array
    {
        return $this->extensions;
    }

    /** @return array<string, ExtensionDefinition> */
    public function byCategory(string $category): array
    {
        return array_filter($this->extensions, fn (ExtensionDefinition $e) => $e->category === $category);
    }
}
