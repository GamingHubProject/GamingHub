<?php

namespace Tests\Unit\Manager;

use App\Manager\PackageRegistry;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PackageRegistryTest extends TestCase
{
    public function test_parses_extensions_from_fixture(): void
    {
        $registry = PackageRegistry::fromFile(__DIR__.'/fixtures/registry.json');

        $this->assertTrue($registry->has('gaming-hub-core'));
        $this->assertTrue($registry->has('pelican'));
        $this->assertFalse($registry->has('does-not-exist'));

        $core = $registry->find('gaming-hub-core');
        $this->assertSame('Gaming Hub Core', $core->name);
        $this->assertSame('gaming-hub-core-v*.zip', $core->releaseAsset);
        $this->assertTrue($core->official);
    }

    public function test_parses_requires_constraints(): void
    {
        $registry = PackageRegistry::fromFile(__DIR__.'/fixtures/registry.json');

        $pelican = $registry->find('pelican');

        $this->assertSame(['gaming-hub-core' => '>=0.1.010', 'gaming-hub-panel' => '*'], $pelican->requires);
    }

    public function test_filters_by_category(): void
    {
        $registry = PackageRegistry::fromFile(__DIR__.'/fixtures/registry.json');

        $connectors = $registry->byCategory('Connectors');

        $this->assertCount(1, $connectors);
        $this->assertArrayHasKey('pelican', $connectors);
    }

    public function test_rejects_unsupported_schema(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PackageRegistry::fromJson(json_encode(['schema' => 2, 'extensions' => []]));
    }

    public function test_rejects_extension_missing_required_field(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PackageRegistry::fromJson(json_encode([
            'schema' => 1,
            'extensions' => [['id' => 'broken']],
        ]));
    }
}
