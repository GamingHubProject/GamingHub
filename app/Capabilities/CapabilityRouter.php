<?php

namespace App\Capabilities;

use App\Contracts\CapabilityProviderContract;
use App\Models\CapabilityBinding;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * The one registry of capability providers, and how a (capability, subject)
 * pair resolves to a binding + the provider that serves it. Extensions never
 * touch this directly — they go through CapabilityGateway.
 */
class CapabilityRouter
{
    /** @var array<string, CapabilityProviderContract> */
    protected array $providers = [];

    public function registerProvider(CapabilityProviderContract $provider): void
    {
        $this->providers[$provider::id()] = $provider;
    }

    public function providerFor(string $id): CapabilityProviderContract
    {
        return $this->providers[$id]
            ?? throw new InvalidArgumentException("No capability provider registered for [{$id}].");
    }

    public function findBinding(string $capability, Model $subject): ?CapabilityBinding
    {
        return CapabilityBinding::query()
            ->where('capability', $capability)
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->first();
    }
}
