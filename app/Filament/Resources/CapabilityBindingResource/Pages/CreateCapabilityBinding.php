<?php

namespace App\Filament\Resources\CapabilityBindingResource\Pages;

use App\Filament\Resources\CapabilityBindingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCapabilityBinding extends CreateRecord
{
    protected static string $resource = CapabilityBindingResource::class;

    /**
     * The form uses separate fields per provider (manual_value vs.
     * connector_instance_id/connector_call/connector_normalizer) so
     * Filament never binds two different component types to the same
     * "value" state path. Pack them into the model's single "value" JSON
     * column here, right before it's saved.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['value'] = $data['provider'] === 'connector'
            ? [
                'connector_instance_id' => $data['connector_instance_id'] ?? null,
                'call' => $data['connector_call'] ?? [],
                'normalizer' => $data['connector_normalizer'] ?? null,
            ]
            : ($data['manual_value'] ?? []);

        unset($data['manual_value'], $data['connector_instance_id'], $data['connector_call'], $data['connector_normalizer']);

        return $data;
    }
}
