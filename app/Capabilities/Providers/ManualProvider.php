<?php

namespace App\Capabilities\Providers;

use App\Capabilities\CapabilityValue;
use App\Contracts\CapabilityProviderContract;
use App\Models\CapabilityBinding;

/**
 * The only provider that exists before real Connector packages do — the
 * "value" is whatever an admin typed into the binding directly. Every other
 * provider goes through the exact same contract, so swapping this out for a
 * real Pelican/RCON Connector later doesn't change anything upstream.
 */
class ManualProvider implements CapabilityProviderContract
{
    public static function id(): string
    {
        return 'manual';
    }

    public function fetch(CapabilityBinding $binding): CapabilityValue
    {
        if (! $binding->enabled) {
            return CapabilityValue::unavailable($binding->capability);
        }

        return CapabilityValue::ok($binding->capability, $binding->value ?? []);
    }
}
