<?php

namespace App\Filament\Resources\ServerResource\RelationManagers;

use App\Models\ConnectorInstance;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use GamingHub\Core\Capabilities\CapabilityRegistry;

/**
 * "Add provider" on a Server's edit page — one unified priority stack that
 * mixes two provider types (Provider.type):
 *
 * - 'connector': bind this server to a previously-set-up Connector
 *   (Capabilities → Connectors), and, if that connector is a Pelican
 *   panel, pick which discovered server UUID corresponds to this server.
 * - 'manual': an admin-entered {capability, value} pair — no connection,
 *   no external I/O, good for testing before a real connector exists.
 *   This used to live entirely outside this list as a separate
 *   CapabilityBinding; it's now just another row in the same stack.
 *
 * This IS the routing mechanism — not a separately-maintained
 * CapabilityBinding. This drag-reorderable list ("REST above Pelican above
 * a Manual entry") is exactly the priority CapabilityGateway::probe()
 * walks: it queries Provider rows for this server directly, in this order,
 * merging fields so a lower-priority provider only fills gaps a
 * higher-priority one left open rather than overwriting them.
 */
class ProvidersRelationManager extends RelationManager
{
    protected static string $relationship = 'providers';

    /** Which normalizer(s) a connector type can drive, and the label shown for each in the "Provides" select. */
    protected const NORMALIZERS_BY_TYPE = [
        'pelican' => [
            'pelican-server-status' => 'Server Status (CPU / Memory / Online)',
        ],
        'rest' => [
            'palworld-server-status' => 'Palworld — Server Status (Players / Online)',
        ],
    ];

    /** The REST call each normalizer expects — Pelican's call config is built from the picked UUID instead, see packConfig(). */
    protected const REST_CALL_BY_NORMALIZER = [
        'palworld-server-status' => ['endpoint' => '/v1/api/metrics'],
    ];

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('type')
                    ->label('Provider type')
                    ->options([
                        'connector' => 'Connector',
                        'manual' => 'Manual (testing)',
                    ])
                    ->default('connector')
                    ->required()
                    ->live(),
                Forms\Components\Select::make('connector_instance_id')
                    ->label('Connection')
                    ->options(fn () => ConnectorInstance::query()->pluck('name', 'id'))
                    ->required(fn (Get $get) => $get('type') === 'connector')
                    ->visible(fn (Get $get) => $get('type') === 'connector')
                    ->live()
                    ->afterStateUpdated(fn (Forms\Set $set) => $set('normalizer', null))
                    ->helperText('One of the connections set up under Capabilities → Connectors.'),
                Forms\Components\Select::make('server_identifier')
                    ->label('Pelican server (UUID)')
                    ->visible(fn (Get $get) => $get('type') === 'connector' && self::connectorType($get('connector_instance_id')) === 'pelican')
                    ->required(fn (Get $get) => $get('type') === 'connector' && self::connectorType($get('connector_instance_id')) === 'pelican')
                    ->options(fn (Get $get) => collect(
                        ConnectorInstance::find($get('connector_instance_id'))?->discovered_servers ?? []
                    )->mapWithKeys(fn (array $s) => [$s['identifier'] => "{$s['name']} ({$s['identifier']})"]))
                    ->helperText('Run "Discover Servers" on the connector first if nothing shows up here.'),
                Forms\Components\Select::make('normalizer')
                    ->label('Provides')
                    ->visible(fn (Get $get) => $get('type') === 'connector')
                    ->required(fn (Get $get) => $get('type') === 'connector')
                    ->options(fn (Get $get) => self::NORMALIZERS_BY_TYPE[self::connectorType($get('connector_instance_id'))] ?? [])
                    ->helperText('What this provider actually feeds — different providers can supply different fields for the same server.'),
                Forms\Components\Select::make('capability')
                    ->label('Provides')
                    ->visible(fn (Get $get) => $get('type') === 'manual')
                    ->required(fn (Get $get) => $get('type') === 'manual')
                    ->options(fn () => app(CapabilityRegistry::class)->all()->pluck('name', 'id'))
                    ->helperText('Which capability this manually-entered value answers.'),
                Forms\Components\KeyValue::make('value')
                    ->label('Value')
                    ->visible(fn (Get $get) => $get('type') === 'manual')
                    ->helperText('The data returned for this capability, entered by hand — e.g. "online" → "true", "players" → "12".'),
                Forms\Components\TextInput::make('priority')
                    ->numeric()
                    ->default(0)
                    ->required()
                    ->helperText('Lower number = tried first, and wins any field another provider also answers. Drag rows below to reorder.'),
                Forms\Components\Select::make('status')
                    ->options([
                        'connected' => 'Connected',
                        'disconnected' => 'Disconnected',
                        'error' => 'Error',
                    ])
                    ->default('disconnected')
                    ->required()
                    ->helperText('Set automatically once the background poller (or a manual save) reaches this provider.'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->defaultSort('priority')
            ->reorderable('priority')
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'manual' ? 'Manual' : 'Connector'),
                Tables\Columns\TextColumn::make('connection')
                    ->label('Connection')
                    ->getStateUsing(fn ($record) => $record->type === 'manual'
                        ? '—'
                        : (ConnectorInstance::find($record->connector_instance_id)?->name ?? '—')),
                Tables\Columns\TextColumn::make('connector_type')
                    ->label('Connector type')
                    ->getStateUsing(fn ($record) => $record->type === 'manual'
                        ? '—'
                        : (ConnectorInstance::find($record->connector_instance_id)?->type ?? '—'))
                    ->badge(),
                Tables\Columns\TextColumn::make('provides')
                    ->getStateUsing(fn ($record) => $record->type === 'manual'
                        ? ($record->config['capability'] ?? '—')
                        : ($record->config['normalizer'] ?? '—')),
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
        if (($data['type'] ?? 'connector') === 'manual') {
            $data['connector_instance_id'] = null;
            $data['config'] = [
                'capability' => $data['capability'] ?? null,
                'value' => $data['value'] ?? [],
            ];

            unset($data['capability'], $data['value'], $data['server_identifier'], $data['normalizer']);

            return $data;
        }

        $normalizerId = $data['normalizer'] ?? null;
        $connectorType = self::connectorType($data['connector_instance_id'] ?? null);

        $call = $connectorType === 'pelican'
            ? array_filter(['server_identifier' => $data['server_identifier'] ?? null])
            : (self::REST_CALL_BY_NORMALIZER[$normalizerId] ?? []);

        $data['config'] = array_filter([
            'server_identifier' => $data['server_identifier'] ?? null,
            'normalizer' => $normalizerId,
            'call' => $call ?: null,
        ]);

        unset($data['server_identifier'], $data['normalizer'], $data['capability'], $data['value']);

        return $data;
    }

    protected static function unpackConfig(array $data): array
    {
        if (($data['type'] ?? 'connector') === 'manual') {
            $data['capability'] = $data['config']['capability'] ?? null;
            $data['value'] = $data['config']['value'] ?? [];

            return $data;
        }

        $data['server_identifier'] = $data['config']['server_identifier'] ?? null;
        $data['normalizer'] = $data['config']['normalizer'] ?? null;

        return $data;
    }
}
