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
     * "credentials" column here, right before it's saved. Pelican gets two
     * real keys (Application + Client) since it genuinely has two separate
     * API surfaces — see PelicanConnector's docblock.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['credentials'] = $data['type'] === 'pelican'
            ? [
                'application_token' => $data['pelican_application_token'] ?? null,
                'client_token' => $data['pelican_client_token'] ?? null,
            ]
            : (($data['rest_auth_style'] ?? 'basic') === 'bearer'
                ? ['token' => $data['rest_token'] ?? null]
                : ['username' => $data['rest_username'] ?? null, 'password' => $data['rest_password'] ?? null]);

        unset(
            $data['pelican_application_token'],
            $data['pelican_client_token'],
            $data['rest_auth_style'],
            $data['rest_username'],
            $data['rest_password'],
            $data['rest_token'],
        );

        return $data;
    }
}
