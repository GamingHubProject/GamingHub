<?php

namespace App\Manager;

/**
 * @param array<string> $missing package ids required but not installed at all
 * @param array<string, array{constraint: string, installed: string}> $mismatched
 *        package ids that are installed, but not at a version satisfying the constraint
 */
final class DependencyCheckResult
{
    public function __construct(
        public readonly array $missing = [],
        public readonly array $mismatched = [],
    ) {}

    public function satisfied(): bool
    {
        return empty($this->missing) && empty($this->mismatched);
    }
}
