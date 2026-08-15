<?php

namespace App\Filament\Resources\ServerResource\RelationManagers;

use App\Models\ConnectorInstance;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use GamingHub\Core\Models\CapabilityBinding;
use GamingHub\Core\Models\Provider;

/**
 * "Add provider" on a Server's edit page — bind this server to a
 * previously-set-up Connector (Capabilities → Connectors), and, if that
 * connector is a Pelican panel, pick which discovered server UUID on that
 * panel corresponds to this server. Packs into Provider's config JSON
 * (Provider itself only holds a soft connector_instance_id reference — see
 * its docblock in Core for why).
 *
 * Each Provider drives its own CapabilityBinding (kept in sync here, see
 * syncBinding/removeBinding) so "Add provider" actually wires into the real
 * capability system instead of being a disconnected list. Priority controls
 * both drag-reorder here and the merge order CapabilityGateway uses when
 * more than one provider answers the same capability for this server — e.g.
 * a Pelican provider (cpu/memory) and a Palworld REST provider (players)
 * both feed "server-status" without either one overwriting the other's
 * fields (see CapabilityGateway::probe()).
 */
class ProvidersRelationManager extends RelationManager
{
    protected static string $relationship = 'providers';

    /** Which normalizer(s) a connector type can drive — and the capability/call config each implies. */
    protected const NORMALIZERS_BY_TYPE = [
        'pelican' => [
            'pelican-server-status' => 'Server Status (CPU / Memory / Online)',
        ],
        'rest' => [
            'palworld-server-status' => 'Palworld — Server Status (Players / Online)',
        ],
    ];

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('connector_instance_id')
                    ->label('Connection')
                    ->options(fn () => ConnectorInstance::query()->pluck('name', 'id'))
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Forms\Set $set) => $set('normalizer', null))
                    ->helperText('One of the connections set up under Capabilities → Connectors.'),
                Forms\Components\Select::make('server_identifier')
                    ->label('Pelican server (UUID)')
                    ->visible(fn (Get $get) => self::connectorType($get('connector_instance_id')) === 'pelican')
                    ->required(fn (Get $get) => self::connectorType($get('connector_instance_id')) === 'pelican')
                    ->options(fn (Get $get) => collect(
                        ConnectorInstance::find($get('connector_instance_id'))?->discovered_servers ?? []
                    )->mapWithKeys(fn (array $s) => [$s['identifier'] => "{$s['name']} ({$s['identifier']})"]))
                    ->helperText('Run "Discover Servers" on the connector first if nothing shows up here.'),
                Forms\Components\Select::make('normalizer')
                    ->label('Provides')
                    ->options(fn (Get $get) => self::NORMALIZERS_BY_TYPE[self::connectorType($get('connector_instance_id'))] ?? [])
                    ->required()
                    ->helperText('What this provider actually feeds — different providers can supply different fields for the same server.'),
                Forms\Components\TextInput::make('priority')
                    ->numeric()
                    ->default(0)
                    ->required()
                    ->helperText('Lower number = tried/merged first when another provider answers the same field.'),
                Forms\Components\Select::make('status')
                    ->options([
                        'connected' => 'Connected',
                        'disconnected' => 'Disconnected',
                        'error' => 'Error',
                    ])
                    ->default('disconnected')
                    ->required()
                    ->helperText('Set automatically once the background poller reaches this provider.'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->defaultSort('priority')
            ->reorderable('priority')
            ->columns([
                Tables\Columns\TextColumn::make('connection')
                    ->label('Connection')
                    ->getStateUsing(fn ($record) => ConnectorInstance::find($record->connector_instance_id)?->name ?? '—'),
                Tables\Columns\TextColumn::make('connector_type')
                    ->label('Type')
                    ->getStateUsing(fn ($record) => ConnectorInstance::find($record->connector_instance_id)?->type ?? '—')
                    ->badge(),
                Tables\Columns\TextColumn::make('provides')
                    ->getStateUsing(fn ($record) => $record->config['normalizer'] ?? '—'),
                Tables\Columns\TextColumn::make('uuid')
                    ->label('UUID')
                    ->getStateUsing(fn ($record) => $record->config['server_identifier'] ?? '—'),
                Tables\Columns\TextColumn::make('priority')
                    ->label('Priority'),
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
                    ->mutateFormDataUsing(fn (array $data) => self::packConfig($data))
                    ->after(fn ($record) => self::syncBinding($record)),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateRecordDataUsing(fn (array $data) => self::unpackConfig($data))
                    ->mutateFormDataUsing(fn (array $data) => self::packConfig($data))
                    ->after(fn ($record) => self::syncBinding($record)),
                Tables\Actions\DeleteAction::make()
                    ->before(fn ($record) => self::removeBinding($record)),
            ]);
    }

    protected static function connectorType(?int $connectorInstanceId): ?string
    {
        return $connectorInstanceId ? ConnectorInstance::find($connectorInstanceId)?->type : null;
    }

    protected static function packConfig(array $data): array
    {
        $data['config'] = array_filter([
            'server_identifier' => $data['server_identifier'] ?? null,
            'normalizer' => $data['normalizer'] ?? null,
        ]);

        unset($data['server_identifier'], $data['normalizer']);

        return $data;
    }

    protected static function unpackConfig(array $data): array
    {
        $data['server_identifier'] = $data['config']['server_identifier'] ?? null;
        $data['normalizer'] = $data['config']['normalizer'] ?? null;

        return $data;
    }

    /**
     * Every normalizer currently in use maps to the "server-status"
     * capability — different providers contribute different fields within
     * it (see the class docblock). If a normalizer ever needs its own
     * capability, look it up here instead of assuming.
     */
    protected static function syncBinding(Provider $record): void
    {
        $connector = ConnectorInstance::find($record->connector_instance_id);
        $normalizerId = $record->config['normalizer'] ?? null;

        if (! $connector || ! $normalizerId) {
            return;
        }

        $call = $connector->type === 'pelican'
            ? ['server_identifier' => $record->config['server_identifier'] ?? null]
            : ['endpoint' => '/v1/api/metrics'];

        CapabilityBinding::updateOrCreate(
            ['source_provider_id' => $record->id],
            [
                'capability' => 'server-status',
                'subject_type' => 'server',
                'subject_id' => $record->server_id,
                'provider' => 'connector',
                'priority' => $record->priority,
                'enabled' => true,
                'value' => [
                    'connector_instance_id' => $connector->id,
                    'call' => $call,
                    'normalizer' => $normalizerId,
                ],
            ]
        );
    }

    protected static function removeBinding(Provider $record): void
    {
        CapabilityBinding::where('source_provider_id', $record->id)->delete();
    }
}
