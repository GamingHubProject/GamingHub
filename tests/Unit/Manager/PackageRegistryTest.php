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

        $this->assertTrue($registry->has('maps'));
        $this->assertTrue($registry->has('trading'));
        $this->assertFalse($registry->has('does-not-exist'));

        $maps = $registry->find('maps');
        $this->assertSame('Maps', $maps->name);
        $this->assertSame('maps-v*.zip', $maps->releaseAsset);
        $this->assertTrue($maps->official);
    }

    public function test_filters_by_category(): void
    {
        $registry = PackageRegistry::fromFile(__DIR__.'/fixtures/registry.json');

        $hubExtensions = $registry->byCategory('Hub Extensions');

        $this->assertCount(2, $hubExtensions);
        $this->assertArrayHasKey('maps', $hubExtensions);
        $this->assertArrayHasKey('trading', $hubExtensions);
    }

    public function test_rejects_unsupported_schema(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PackageRegistry::fromJson(json_encode(['schema' => 2, 'packages' => []]));
    }

    public function test_rejects_extension_missing_required_field(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PackageRegistry::fromJson(json_encode([
            'schema' => 1,
            'packages' => [['id' => 'broken']],
        ]));
    }
}
