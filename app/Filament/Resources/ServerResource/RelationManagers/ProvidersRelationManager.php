<?php

namespace App\Filament\Resources\ServerResource\RelationManagers;

use App\Models\ConnectorInstance;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * "Add provider" on a Server's edit page — bind this server to a
 * previously-set-up Connector (Capabilities → Connectors), and, if that
 * connector is a Pelican panel, pick which discovered server UUID on that
 * panel corresponds to this server. Packs into Provider's config JSON
 * (Provider itself only holds a soft connector_instance_id reference — see
 * its docblock in Core for why).
 */
class ProvidersRelationManager extends RelationManager
{
    protected static string $relationship = 'providers';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('connector_instance_id')
                    ->label('Connection')
                    ->options(fn () => ConnectorInstance::query()->pluck('name', 'id'))
                    ->required()
                    ->live()
                    ->helperText('One of the connections set up under Capabilities → Connectors.'),
                Forms\Components\Select::make('server_identifier')
                    ->label('Pelican server (UUID)')
                    ->visible(fn (Get $get) => self::connectorType($get('connector_instance_id')) === 'pelican')
                    ->required(fn (Get $get) => self::connectorType($get('connector_instance_id')) === 'pelican')
                    ->options(fn (Get $get) => collect(
                        ConnectorInstance::find($get('connector_instance_id'))?->discovered_servers ?? []
                    )->mapWithKeys(fn (array $s) => [$s['identifier'] => "{$s['name']} ({$s['identifier']})"]))
                    ->helperText('Run "Discover Servers" on the connector first if nothing shows up here.'),
                Forms\Components\Select::make('status')
                    ->options([
                        'connected' => 'Connected',
                        'disconnected' => 'Disconnected',
                        'error' => 'Error',
                    ])
                    ->default('disconnected')
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('connection')
                    ->label('Connection')
                    ->getStateUsing(fn ($record) => ConnectorInstance::find($record->connector_instance_id)?->name ?? '—'),
                Tables\Columns\TextColumn::make('connector_type')
                    ->label('Type')
                    ->getStateUsing(fn ($record) => ConnectorInstance::find($record->connector_instance_id)?->type ?? '—')
                    ->badge(),
                Tables\Columns\TextColumn::make('uuid')
                    ->label('UUID')
                    ->getStateUsing(fn ($record) => $record->config['server_identifier'] ?? '—'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'connected' => 'success',
                        'error' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add provider')
                    ->mutateFormDataUsing(fn (array $data) => self::packConfig($data)),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateRecordDataUsing(fn (array $data) => self::unpackConfig($data))
                    ->mutateFormDataUsing(fn (array $data) => self::packConfig($data)),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    protected static function connectorType(?int $connectorInstanceId): ?string
    {
        return $connectorInstanceId ? ConnectorInstance::find($connectorInstanceId)?->type : null;
    }

    protected static function packConfig(array $data): array
    {
        $data['config'] = filled($data['server_identifier'] ?? null)
            ? ['server_identifier' => $data['server_identifier']]
            : [];

        unset($data['server_identifier']);

        return $data;
    }

    protected static function unpackConfig(array $data): array
    {
        $data['server_identifier'] = $data['config']['server_identifier'] ?? null;

        return $data;
    }
}
