<?php

namespace App\Services;

use MaxMind\Db\Reader;
use Throwable;

/**
 * Resolves an IP to a 2-letter ISO country code using a local DB-IP "IP to
 * Country Lite" MMDB file (see gaming-hub:geoip-update) — no runtime API
 * call, so a missing/stale file degrades to null rather than blocking or
 * slowing down whatever triggered the lookup (AdminAuditObserver, in
 * particular, must never fail an admin action because geo-IP data isn't
 * available).
 */
class GeoIpLookup
{
    public const RELATIVE_PATH = 'geoip/dbip-country-lite.mmdb';

    public function countryCode(?string $ip): ?string
    {
        if (! $ip || ! $this->isPublic($ip)) {
            return null;
        }

        $path = $this->path();

        if (! is_file($path)) {
            return null;
        }

        try {
            $reader = new Reader($path);
            $record = $reader->get($ip);
            $reader->close();

            return $record['country']['iso_code'] ?? null;
        } catch (Throwable) {
            // A corrupt/unreadable file must never break the caller — same
            // "geo-IP is best-effort" principle as the missing-file case.
            return null;
        }
    }

    public function path(): string
    {
        return storage_path('app/'.self::RELATIVE_PATH);
    }

    /**
     * Private/reserved-range IPs (127.0.0.1, Docker-internal networks,
     * RFC1918 addresses) are the common case for local dev and same-host
     * admin access, and will never resolve to a country in a public geo-IP
     * database — skip the lookup entirely rather than doing one guaranteed
     * to return nothing.
     */
    protected function isPublic(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
}
