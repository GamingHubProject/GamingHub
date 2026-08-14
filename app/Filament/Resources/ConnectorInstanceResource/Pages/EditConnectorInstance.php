<?php

namespace App\Filament\Resources\ConnectorInstanceResource\Pages;

use App\Filament\Resources\ConnectorInstanceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditConnectorInstance extends EditRecord
{
    protected static string $resource = ConnectorInstanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * Unpack the model's single "credentials" column into the form's real,
     * labeled per-auth-style fields (see CreateConnectorInstance for why).
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $credentials = $data['credentials'] ?? [];

        if (($data['type'] ?? null) === 'pelican') {
            $data['pelican_application_token'] = $credentials['application_token'] ?? null;
            $data['pelican_client_token'] = $credentials['client_token'] ?? null;
        } elseif (array_key_exists('token', $credentials)) {
            $data['rest_auth_style'] = 'bearer';
            $data['rest_token'] = $credentials['token'] ?? null;
        } else {
            $data['rest_auth_style'] = 'basic';
            $data['rest_username'] = $credentials['username'] ?? null;
            $data['rest_password'] = $credentials['password'] ?? null;
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
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
