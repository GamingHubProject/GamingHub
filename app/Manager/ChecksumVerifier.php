<?php

namespace App\Manager;

final class ChecksumVerifier
{
    /**
     * Parse a standard `sha256sum` output file into filename => hash.
     */
    public function parse(string $checksumFileContents): array
    {
        $hashes = [];

        foreach (preg_split('/\R/', trim($checksumFileContents)) as $line) {
            $line = trim($line);

            if ($line === '' || ! preg_match('/^([a-f0-9]{64})\s+\*?(.+)$/i', $line, $matches)) {
                continue;
            }

            $hashes[$matches[2]] = strtolower($matches[1]);
        }

        return $hashes;
    }

    public function verify(string $filePath, string $expectedHash): bool
    {
        return hash_equals(strtolower($expectedHash), hash_file('sha256', $filePath));
    }

    /**
     * Convenience: parse the checksum file and verify $filePath against the
     * entry for $filename in one call.
     */
    public function verifyAgainstManifest(string $filePath, string $checksumFileContents, string $filename): bool
    {
        $hashes = $this->parse($checksumFileContents);

        if (! isset($hashes[$filename])) {
            return false;
        }

        return $this->verify($filePath, $hashes[$filename]);
    }
}
