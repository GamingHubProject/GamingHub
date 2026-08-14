<?php

namespace App\Experience;

use App\Contracts\BlockContract;
use InvalidArgumentException;

/**
 * The one registry for page-builder blocks. Hub Extensions register their
 * own block classes here (App::make(BlockRegistry::class)->register(...))
 * instead of Experience knowing about them ahead of time.
 */
class BlockRegistry
{
    /** @var array<string, class-string<BlockContract>> */
    protected array $blocks = [];

    public function register(string $blockClass): void
    {
        if (! is_subclass_of($blockClass, BlockContract::class)) {
            throw new InvalidArgumentException("{$blockClass} must implement ".BlockContract::class);
        }

        $this->blocks[$blockClass::id()] = $blockClass;
    }

    /**
     * @return array<string, class-string<BlockContract>>
     */
    public function all(): array
    {
        return $this->blocks;
    }

    /**
     * @return class-string<BlockContract>|null
     */
    public function get(string $id): ?string
    {
        return $this->blocks[$id] ?? null;
    }

    public function options(): array
    {
        $options = [];

        foreach ($this->blocks as $id => $class) {
            $options[$id] = $class::label();
        }

        return $options;
    }
}
