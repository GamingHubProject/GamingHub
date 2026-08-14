<?php

namespace App\Manager;

use RuntimeException;
use ZipArchive;

/**
 * Downloads a release zip for an extension version, verifies it against the
 * registry's checksum manifest, and extracts it into a destination
 * directory. Never trusts an unverified download — checksum failure always
 * throws rather than silently installing.
 */
final class PackageDownloader
{
    public function __construct(
        private readonly HttpClientContract $http,
        private readonly ChecksumVerifier $checksums = new ChecksumVerifier,
    ) {}

    public function resolveAssetFilename(ExtensionDefinition $extension, string $version): string
    {
        return str_replace('*', $version, $extension->releaseAsset);
    }

    public function releaseUrl(ExtensionDefinition $extension, string $version, string $assetFilename): string
    {
        return rtrim($extension->repository, '/')."/releases/download/v{$version}/{$assetFilename}";
    }

    public function checksumUrl(ExtensionDefinition $extension, string $version): string
    {
        return rtrim($extension->repository, '/')."/releases/download/v{$version}/{$extension->checksumAsset}";
    }

    /**
     * Downloads, verifies, and extracts $extension at $version into
     * $destinationDir (created if missing). Throws on any failure — never
     * partially installs.
     */
    public function install(ExtensionDefinition $extension, string $version, string $destinationDir): void
    {
        $assetFilename = $this->resolveAssetFilename($extension, $version);

        $zipBytes = $this->http->get($this->releaseUrl($extension, $version, $assetFilename));
        $checksumManifest = $this->http->get($this->checksumUrl($extension, $version));

        $tmpZip = tempnam(sys_get_temp_dir(), 'ghm_');

        try {
            file_put_contents($tmpZip, $zipBytes);

            if (! $this->checksums->verifyAgainstManifest($tmpZip, $checksumManifest, $assetFilename)) {
                throw new RuntimeException(
                    "Checksum verification failed for [{$assetFilename}] — refusing to install a package that doesn't match its published checksum."
                );
            }

            $this->extract($tmpZip, $destinationDir);
        } finally {
            @unlink($tmpZip);
        }
    }

    private function extract(string $zipPath, string $destinationDir): void
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException("Could not open downloaded archive at [{$zipPath}] — it may be corrupt.");
        }

        $tmpExtractDir = $zipPath.'_extracted';
        @mkdir($tmpExtractDir, 0755, true);

        $zip->extractTo($tmpExtractDir);
        $zip->close();

        $sourceRoot = $this->resolveSourceRoot($tmpExtractDir);

        if (! is_dir($destinationDir)) {
            mkdir($destinationDir, 0755, true);
        }

        $this->moveContents($sourceRoot, $destinationDir);
        $this->removeDirectory($tmpExtractDir);
    }

    /**
     * GitHub-style release zips usually wrap everything in a single
     * top-level directory (e.g. "gaming-hub-core-0.1.010/") — strip it so
     * $destinationDir gets the package's actual files directly.
     */
    private function resolveSourceRoot(string $extractedDir): string
    {
        $entries = array_values(array_diff(scandir($extractedDir), ['.', '..']));

        if (count($entries) === 1 && is_dir($extractedDir.'/'.$entries[0])) {
            return $extractedDir.'/'.$entries[0];
        }

        return $extractedDir;
    }

    private function moveContents(string $from, string $to): void
    {
        foreach (array_diff(scandir($from), ['.', '..']) as $entry) {
            rename($from.'/'.$entry, $to.'/'.$entry);
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (array_diff(scandir($dir), ['.', '..']) as $entry) {
            $path = $dir.'/'.$entry;
            is_dir($path) ? $this->removeDirectory($path) : @unlink($path);
        }

        @rmdir($dir);
    }
}
