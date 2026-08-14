<?php

namespace App\Filament\Resources\ConnectorInstanceResource\Pages;

use App\Filament\Resources\ConnectorInstanceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateConnectorInstance extends CreateRecord
{
    protected static string $resource = ConnectorInstanceResource::class;

    /**
     * The form asks for real, labeled fields per auth style instead of a
     * "guess the JSON key" KeyValue — pack them into the model's single
     * "credentials" column here, right before it's saved.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['credentials'] = $data['type'] === 'pelican'
            ? ['token' => $data['pelican_token'] ?? null]
            : (($data['rest_auth_style'] ?? 'basic') === 'bearer'
                ? ['token' => $data['rest_token'] ?? null]
                : ['username' => $data['rest_username'] ?? null, 'password' => $data['rest_password'] ?? null]);

        unset(
            $data['pelican_token'],
            $data['rest_auth_style'],
            $data['rest_username'],
            $data['rest_password'],
            $data['rest_token'],
        );

        return $data;
    }
}
