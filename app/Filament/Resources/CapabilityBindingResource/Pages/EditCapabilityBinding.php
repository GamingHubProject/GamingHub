<?php

namespace App\Filament\Resources\CapabilityBindingResource\Pages;

use App\Filament\Resources\CapabilityBindingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCapabilityBinding extends EditRecord
{
    protected static string $resource = CapabilityBindingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * Unpack the model's single "value" JSON column into the form's
     * separate per-provider fields (see CreateCapabilityBinding for why).
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (($data['provider'] ?? null) === 'connector') {
            $value = $data['value'] ?? [];
            $data['connector_instance_id'] = $value['connector_instance_id'] ?? null;
            $data['connector_call'] = $value['call'] ?? [];
            $data['connector_normalizer'] = $value['normalizer'] ?? null;
        } else {
            $data['manual_value'] = $data['value'] ?? [];
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
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
