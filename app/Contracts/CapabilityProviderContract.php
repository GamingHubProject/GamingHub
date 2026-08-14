<?php

namespace App\Contracts;

use App\Capabilities\CapabilityValue;
use App\Models\CapabilityBinding;

/**
 * How a capability's value actually gets fetched for a binding. Real
 * Connector packages (Pelican, RCON, etc.) will implement this once the
 * package system exists — for now only ManualProvider (an admin-entered
 * value) implements it.
 */
interface CapabilityProviderContract
{
    public static function id(): string;

    public function fetch(CapabilityBinding $binding): CapabilityValue;
}
