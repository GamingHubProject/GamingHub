<?php

namespace Tests\Unit\Manager;

use App\Manager\VersionResolver;
use PHPUnit\Framework\TestCase;

class VersionResolverTest extends TestCase
{
    public function test_satisfies_basic_constraints(): void
    {
        $resolver = new VersionResolver;

        $this->assertTrue($resolver->satisfies('0.1.010', '>=0.1.010'));
        $this->assertTrue($resolver->satisfies('0.1.020', '>=0.1.010'));
        $this->assertFalse($resolver->satisfies('0.1.001', '>=0.1.010'));
        $this->assertTrue($resolver->satisfies('0.1.030', '*'));
    }

    public function test_requirements_satisfied_when_installed_versions_match(): void
    {
        $resolver = new VersionResolver;

        $result = $resolver->checkRequirements($this->pelicanRequires(), [
            'gaming-hub-core' => '0.1.030',
            'gaming-hub-panel' => '0.1.000',
        ]);

        $this->assertTrue($result->satisfied());
        $this->assertEmpty($result->missing);
        $this->assertEmpty($result->mismatched);
    }

    public function test_detects_missing_dependency(): void
    {
        $resolver = new VersionResolver;

        $result = $resolver->checkRequirements($this->pelicanRequires(), [
            'gaming-hub-core' => '0.1.030',
        ]);

        $this->assertFalse($result->satisfied());
        $this->assertSame(['gaming-hub-panel'], $result->missing);
    }

    public function test_detects_version_mismatch(): void
    {
        $resolver = new VersionResolver;

        $result = $resolver->checkRequirements($this->pelicanRequires(), [
            'gaming-hub-core' => '0.1.001',
            'gaming-hub-panel' => '0.1.000',
        ]);

        $this->assertFalse($result->satisfied());
        $this->assertArrayHasKey('gaming-hub-core', $result->mismatched);
        $this->assertSame('0.1.001', $result->mismatched['gaming-hub-core']['installed']);
    }

    /** @return array<string, string> */
    private function pelicanRequires(): array
    {
        return [
            'gaming-hub-core' => '>=0.1.010',
            'gaming-hub-panel' => '*',
        ];
    }
}
