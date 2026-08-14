<?php

namespace App\Contracts;

use Illuminate\Contracts\View\View;

interface BlockContract
{
    /**
     * Stable machine identifier for this block type, e.g. "rich-text".
     */
    public static function id(): string;

    /**
     * Human-readable name shown in the page builder.
     */
    public static function label(): string;

    /**
     * Filament form schema (array of Forms\Components) used to edit this
     * block's config when it's placed on a page.
     */
    public static function configSchema(): array;

    /**
     * Render this block for the public page given its saved config.
     */
    public function render(array $config): View;
}
