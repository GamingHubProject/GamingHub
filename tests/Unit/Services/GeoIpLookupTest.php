<?php

namespace Tests\Unit\Services;

use App\Services\GeoIpLookup;
use PHPUnit\Framework\TestCase;

class GeoIpLookupTest extends TestCase
{
    public function test_null_ip_returns_null(): void
    {
        $this->assertNull((new GeoIpLookup)->countryCode(null));
    }

    public function test_loopback_address_returns_null_without_needing_a_database_file(): void
    {
        $this->assertNull((new GeoIpLookup)->countryCode('127.0.0.1'));
    }

    public function test_private_range_address_returns_null(): void
    {
        $this->assertNull((new GeoIpLookup)->countryCode('192.168.1.1'));
        $this->assertNull((new GeoIpLookup)->countryCode('10.0.0.5'));
        $this->assertNull((new GeoIpLookup)->countryCode('172.20.0.3'));
    }

    public function test_invalid_ip_string_returns_null(): void
    {
        $this->assertNull((new GeoIpLookup)->countryCode('not-an-ip'));
    }

    public function test_a_public_ip_with_no_database_file_present_returns_null_gracefully(): void
    {
        // No .mmdb file exists in this test environment — this is exactly
        // the "never downloaded yet" / "download failed" case the lookup
        // must degrade gracefully from, not throw.
        $this->assertNull((new GeoIpLookup)->countryCode('8.8.8.8'));
    }
}
