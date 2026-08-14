<?php

namespace Tests\Unit\Manager;

use App\Manager\ChecksumVerifier;
use PHPUnit\Framework\TestCase;

class ChecksumVerifierTest extends TestCase
{
    public function test_parses_standard_sha256sum_output(): void
    {
        $verifier = new ChecksumVerifier;

        $manifest = <<<TXT
        e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855  gaming-hub-core-v0.1.010.zip
        d41d8cd98f00b204e9800998ecf8427e  other-file.zip
        TXT;

        $hashes = $verifier->parse($manifest);

        $this->assertArrayHasKey('gaming-hub-core-v0.1.010.zip', $hashes);
        $this->assertArrayNotHasKey('other-file.zip', $hashes); // not a valid sha256 (32 hex chars, not 64)
    }

    public function test_verifies_a_file_matching_its_manifest_entry(): void
    {
        $verifier = new ChecksumVerifier;

        $file = tempnam(sys_get_temp_dir(), 'ghm_test_');
        file_put_contents($file, 'hello world');
        $hash = hash_file('sha256', $file);

        $manifest = "{$hash}  package.zip\n";

        $this->assertTrue($verifier->verifyAgainstManifest($file, $manifest, 'package.zip'));

        unlink($file);
    }

    public function test_rejects_a_file_with_wrong_hash(): void
    {
        $verifier = new ChecksumVerifier;

        $file = tempnam(sys_get_temp_dir(), 'ghm_test_');
        file_put_contents($file, 'hello world');

        $manifest = str_repeat('a', 64)."  package.zip\n";

        $this->assertFalse($verifier->verifyAgainstManifest($file, $manifest, 'package.zip'));

        unlink($file);
    }

    public function test_rejects_when_filename_not_in_manifest(): void
    {
        $verifier = new ChecksumVerifier;

        $file = tempnam(sys_get_temp_dir(), 'ghm_test_');
        file_put_contents($file, 'hello world');
        $hash = hash_file('sha256', $file);

        $manifest = "{$hash}  different-name.zip\n";

        $this->assertFalse($verifier->verifyAgainstManifest($file, $manifest, 'package.zip'));

        unlink($file);
    }
}
