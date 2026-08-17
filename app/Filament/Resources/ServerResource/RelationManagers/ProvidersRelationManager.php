<?php

namespace App\Filament\Resources\ServerResource\RelationManagers;

use App\Capabilities\CapabilityGateway;
use App\Models\ConnectorInstance;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use GamingHub\Core\Capabilities\CapabilityRegistry;
use GamingHub\Core\Models\Provider;

/**
 * "Add provider" on a Server's edit page — one unified priority stack that
 * mixes two provider types (Provider.type):
 *
 * - 'connector': bind this server to a previously-set-up Connector
 *   (Capabilities → Connectors). What it feeds depends on the connector's
 *   own type — Pelican always answers with a fixed, built-in normalizer
 *   (it reports process-level stats the same way for any game); a generic
 *   REST connector has no fixed normalizer at all, since REST APIs vary
 *   per game — the admin maps that game's own JSON field names to the
 *   capability's expected shape directly in this form
 *   (GamingHub\Core\Normalizers\FieldMappingNormalizer reads that mapping
 *   at resolution time, Core has no per-game normalizer classes).
 * - 'manual': an admin-entered {capability, value} pair — no connection,
 *   no external I/O, good for testing before a real connector exists.
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
                    ->helperText('One of the connections set up under Capabilities → Connectors.'),
                Forms\Components\Select::make('server_identifier')
                    ->label('Pelican server (UUID)')
                    ->visible(fn (Get $get) => $get('type') === 'connector' && self::connectorType($get('connector_instance_id')) === 'pelican')
                    ->required(fn (Get $get) => $get('type') === 'connector' && self::connectorType($get('connector_instance_id')) === 'pelican')
                    ->options(fn (Get $get) => collect(
                        ConnectorInstance::find($get('connector_instance_id'))?->discovered_servers ?? []
                    )->mapWithKeys(fn (array $s) => [$s['identifier'] => "{$s['name']} ({$s['identifier']})"]))
                    ->helperText('Run "Discover Servers" on the connector first if nothing shows up here.'),
                Forms\Components\TextInput::make('endpoint')
                    ->label('Endpoint')
                    ->visible(fn (Get $get) => $get('type') === 'connector' && self::connectorType($get('connector_instance_id')) === 'rest')
                    ->required(fn (Get $get) => $get('type') === 'connector' && self::connectorType($get('connector_instance_id')) === 'rest')
                    ->helperText('The path this connection\'s REST API returns status data from, e.g. "/v1/api/metrics".'),
                Forms\Components\Select::make('capability')
                    ->label('Provides')
                    ->visible(fn (Get $get) => $get('type') === 'manual'
                        || ($get('type') === 'connector' && self::connectorType($get('connector_instance_id')) === 'rest'))
                    ->required(fn (Get $get) => $get('type') === 'manual'
                        || ($get('type') === 'connector' && self::connectorType($get('connector_instance_id')) === 'rest'))
                    ->options(fn () => app(CapabilityRegistry::class)->all()->pluck('name', 'id'))
                    ->helperText('Which capability this provider answers.'),
                Forms\Components\KeyValue::make('field_map')
                    ->label('Field mapping')
                    ->visible(fn (Get $get) => $get('type') === 'connector' && self::connectorType($get('connector_instance_id')) === 'rest')
                    ->keyLabel('Raw JSON field')
                    ->valueLabel('Capability field')
                    ->helperText('Maps this API\'s own field names to the capability\'s expected ones, e.g. "currentplayernum" → "players", "maxplayernum" → "max_players".'),
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
                        : ($record->config['capability'] ?? $record->config['normalizer'] ?? '—')),
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
                Tables\Columns\TextColumn::make('error_message')
                    ->label('Last error')
                    ->placeholder('—')
                    ->limit(40)
                    ->tooltip(fn (?string $state) => $state)
                    ->toggleable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add provider')
                    ->mutateFormDataUsing(fn (array $data) => self::packConfig($data)),
            ])
            ->actions([
                Tables\Actions\Action::make('test')
                    ->label('Test')
                    ->icon('heroicon-o-signal')
                    ->action(function (Provider $record) {
                        $value = app(CapabilityGateway::class)->testProvider($record);
                        $record->refresh();

                        Notification::make()
                            ->title($value->isOk() ? 'Provider responded successfully' : 'Provider test failed')
                            ->body($value->isOk() ? null : $record->error_message)
                            ->color($value->isOk() ? 'success' : 'danger')
                            ->send();
                    }),
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

            unset($data['capability'], $data['value'], $data['server_identifier'], $data['endpoint'], $data['field_map']);

            return $data;
        }

        $connectorType = self::connectorType($data['connector_instance_id'] ?? null);

        $data['config'] = $connectorType === 'pelican'
            ? array_filter([
                'server_identifier' => $data['server_identifier'] ?? null,
                'normalizer' => 'pelican-server-status',
                'call' => array_filter(['server_identifier' => $data['server_identifier'] ?? null]),
            ])
            : array_filter([
                'normalizer' => 'field-mapping',
                'capability' => $data['capability'] ?? null,
                'call' => array_filter(['endpoint' => $data['endpoint'] ?? null]),
                'field_map' => $data['field_map'] ?? [],
            ], fn ($value) => $value !== null && $value !== []);

        unset($data['server_identifier'], $data['endpoint'], $data['capability'], $data['field_map'], $data['value']);

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
        $data['endpoint'] = $data['config']['call']['endpoint'] ?? null;
        $data['capability'] = $data['config']['capability'] ?? null;
        $data['field_map'] = $data['config']['field_map'] ?? [];

        return $data;
    }
}
