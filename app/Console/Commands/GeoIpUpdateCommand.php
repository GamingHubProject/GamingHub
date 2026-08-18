<?php

namespace App\Console\Commands;

use App\Services\GeoIpLookup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use MaxMind\Db\Reader;
use Throwable;

/**
 * Downloads DB-IP's free "IP to Country Lite" MMDB (CC BY 4.0, no account
 * required — see https://db-ip.com/db/download/ip-to-country-lite),
 * validates it actually works, and atomically swaps it into place at
 * GeoIpLookup::RELATIVE_PATH. Released monthly with the month baked into
 * the filename (dbip-country-lite-YYYY-MM.mmdb.gz) rather than a stable
 * "latest" URL, so this tries the current month first and falls back to
 * the previous one — there's a real window early each month where the
 * current month's file isn't published yet.
 *
 * Failure here is never fatal to the caller: exits non-zero so a direct
 * invocation gets honest feedback, but the installer that calls this as
 * part of its Update action treats that as a warning, not a blocker — and
 * the existing database file (if any) is left completely untouched until
 * a newly downloaded one is confirmed to actually work, so a failed
 * update never leaves country lookups worse off than before the attempt.
 */
class GeoIpUpdateCommand extends Command
{
    protected $signature = 'gaming-hub:geoip-update';

    protected $description = 'Download the latest DB-IP Country Lite geo-IP database';

    public function handle(GeoIpLookup $lookup): int
    {
        foreach ([now(), now()->subMonth()] as $month) {
            $release = $month->format('Y-m');
            $this->info("Trying the {$release} release...");

            if ($this->tryInstall($release, $lookup)) {
                $this->info("Geo-IP database updated to the {$release} release.");

                return self::SUCCESS;
            }
        }

        $this->error('Could not download a working Geo-IP database (tried the current and previous month\'s release). The existing database, if any, is unchanged — country lookups will keep using it as-is.');

        return self::FAILURE;
    }

    protected function tryInstall(string $release, GeoIpLookup $lookup): bool
    {
        $url = "https://download.db-ip.com/free/dbip-country-lite-{$release}.mmdb.gz";

        try {
            $response = Http::timeout(30)->get($url);
        } catch (Throwable $e) {
            $this->warn("  Download failed: {$e->getMessage()}");

            return false;
        }

        if (! $response->successful()) {
            $this->warn("  Not available yet (HTTP {$response->status()}).");

            return false;
        }

        $decompressed = @gzdecode($response->body());

        if ($decompressed === false || $decompressed === '') {
            $this->warn('  Downloaded file is not a valid gzip archive.');

            return false;
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'geoip_');
        file_put_contents($tmpPath, $decompressed);

        if (! $this->isValidMmdb($tmpPath)) {
            $this->warn('  Downloaded database failed validation (test lookup did not return a plausible result).');
            @unlink($tmpPath);

            return false;
        }

        File::ensureDirectoryExists(dirname($lookup->path()));
        // rename() is atomic on the same filesystem — a lookup running
        // concurrently always sees either the complete old file or the
        // complete new one, never a partial write.
        rename($tmpPath, $lookup->path());

        return true;
    }

    /**
     * Not just "is this file non-empty" — actually open it and look up a
     * known address, the same "prove it works, not just that bytes
     * arrived" standard backup_database() uses in the installer.
     */
    protected function isValidMmdb(string $path): bool
    {
        try {
            $reader = new Reader($path);
            $record = $reader->get('8.8.8.8');
            $reader->close();

            return ($record['country']['iso_code'] ?? null) === 'US';
        } catch (Throwable) {
            return false;
        }
    }
}
