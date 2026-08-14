<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CapabilityBindingResource\Pages;
use App\Models\ConnectorInstance;
use App\Models\Map;
use GamingHub\Core\Models\CapabilityBinding;
use GamingHub\Core\Models\Game;
use GamingHub\Core\Models\Instance;
use GamingHub\Core\Models\Server;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CapabilityBindingResource extends Resource
{
    protected static ?string $model = CapabilityBinding::class;

    protected static ?string $navigationIcon = 'heroicon-o-bolt';

    protected static ?string $navigationGroup = 'Capabilities';

    protected static ?string $navigationLabel = 'Bindings';

    protected static ?string $modelLabel = 'Capability Binding';

    protected const SUBJECT_MODELS = [
        'game' => Game::class,
        'server' => Server::class,
        'instance' => Instance::class,
        'map' => Map::class,
    ];

    protected const CAPABILITIES = [
        'player-positions' => 'Player Positions',
        'server-status' => 'Server Status',
        'player-identities' => 'Player Identities',
        'configuration' => 'Configuration',
        'events' => 'Events',
        'chat' => 'Chat',
        'commands' => 'Commands',
    ];

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Binding')
                    ->description(
                        'Binds a capability to a subject with a provider — "manual" is a value you '
                        .'type in yourself; "connector" calls a real external system (see '
                        .'Capabilities → Connectors) and normalizes what comes back.'
                    )
                    ->schema([
                        Forms\Components\Select::make('capability')
                            ->options(self::CAPABILITIES)
                            ->required(),
                        Forms\Components\Select::make('subject_type')
                            ->label('Subject type')
                            ->options([
                                'game' => 'Game',
                                'server' => 'Server',
                                'instance' => 'Instance',
                                'map' => 'Map',
                            ])
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('subject_id', null)),
                        Forms\Components\Select::make('subject_id')
                            ->label('Subject')
                            ->options(function (Get $get) {
                                $class = self::SUBJECT_MODELS[$get('subject_type')] ?? null;

                                return $class ? $class::query()->pluck('name', 'id') : [];
                            })
                            ->required()
                            ->searchable(),
                        Forms\Components\Select::make('provider')
                            ->options([
                                'manual' => 'Manual (admin-entered value)',
                                'connector' => 'Connector (real external call)',
                            ])
                            ->default('manual')
                            ->required()
                            ->live(),
                        Forms\Components\Toggle::make('enabled')
                            ->default(true),
                        Forms\Components\KeyValue::make('manual_value')
                            ->label('Value')
                            ->helperText('Shape depends on the capability — e.g. server-status: online, players, uptime.')
                            ->visible(fn (Get $get) => $get('provider') === 'manual')
                            ->dehydrated(fn (Get $get) => $get('provider') === 'manual')
                            ->columnSpanFull(),
                        Forms\Components\Select::make('connector_instance_id')
                            ->label('Connector')
                            ->options(fn () => ConnectorInstance::query()->pluck('name', 'id'))
                            ->required()
                            ->visible(fn (Get $get) => $get('provider') === 'connector')
                            ->dehydrated(fn (Get $get) => $get('provider') === 'connector')
                            ->helperText('Manage these under Capabilities → Connectors.'),
                        Forms\Components\KeyValue::make('connector_call')
                            ->label('Call config')
                            ->visible(fn (Get $get) => $get('provider') === 'connector')
                            ->dehydrated(fn (Get $get) => $get('provider') === 'connector')
                            ->helperText(
                                'REST: key "endpoint" (e.g. /v1/api/metrics), optional "method". '
                                .'Pelican: key "server_identifier".'
                            ),
                        Forms\Components\Select::make('connector_normalizer')
                            ->label('Normalizer')
                            ->options([
                                'palworld-server-status' => 'Palworld — Server Status',
                                'pelican-server-status' => 'Pelican — Server Status',
                            ])
                            ->required()
                            ->visible(fn (Get $get) => $get('provider') === 'connector')
                            ->dehydrated(fn (Get $get) => $get('provider') === 'connector')
                            ->helperText('Which normalizer parses this connector\'s raw response.'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('capability')
                    ->badge()
                    ->searchable(),
                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Subject type')
                    ->badge(),
                Tables\Columns\TextColumn::make('subject.name')
                    ->label('Subject'),
                Tables\Columns\TextColumn::make('provider'),
                Tables\Columns\IconColumn::make('enabled')
                    ->boolean(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('capability')
                    ->options(self::CAPABILITIES),
                Tables\Filters\SelectFilter::make('subject_type')
                    ->options([
                        'game' => 'Game',
                        'server' => 'Server',
                        'instance' => 'Instance',
                        'map' => 'Map',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCapabilityBindings::route('/'),
            'create' => Pages\CreateCapabilityBinding::route('/create'),
            'edit' => Pages\EditCapabilityBinding::route('/{record}/edit'),
        ];
    }
}
