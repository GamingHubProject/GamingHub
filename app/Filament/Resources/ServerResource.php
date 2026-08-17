<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServerResource\Pages;
use App\Filament\Resources\ServerResource\RelationManagers;
use App\Models\ConnectorInstance;
use App\Models\ServerGroup;
use GamingHub\Core\Models\Server;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ServerResource extends Resource
{
    protected static ?string $model = Server::class;

    protected static ?string $navigationIcon = 'heroicon-o-server';

    protected static ?string $navigationGroup = 'Servers';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('game_id')
                    ->relationship('game', 'name')
                    ->required()
                    ->live(),
                Forms\Components\Select::make('server_group_id')
                    ->label('Server group')
                    ->options(
                        fn (Forms\Get $get) => $get('game_id')
                            ? ServerGroup::where('game_id', $get('game_id'))->pluck('name', 'id')
                            : []
                    )
                    ->helperText('Optional — group into a cluster (e.g. an ARK cluster).'),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('description')
                    ->columnSpanFull(),
                Forms\Components\Select::make('status')
                    ->options([
                        'online' => 'Online',
                        'offline' => 'Offline',
                        'maintenance' => 'Maintenance',
                    ])
                    ->required()
                    ->default('offline')
                    ->disabled(fn (?Server $record) => static::isProviderDriven($record))
                    ->helperText(fn (?Server $record) => static::providerDrivenHelperText($record)),
                Forms\Components\TextInput::make('max_players')
                    ->numeric()
                    ->disabled(fn (?Server $record) => static::isProviderDriven($record))
                    ->helperText(fn (?Server $record) => static::providerDrivenHelperText($record)),
                Forms\Components\TextInput::make('current_players')
                    ->numeric()
                    ->disabled(fn (?Server $record) => static::isProviderDriven($record))
                    ->helperText(fn (?Server $record) => static::providerDrivenHelperText($record)),
                Forms\Components\TextInput::make('cpu_usage_percent')
                    ->label('CPU usage %')
                    ->numeric()
                    ->disabled(fn (?Server $record) => static::isProviderDriven($record))
                    ->helperText(fn (?Server $record) => static::providerDrivenHelperText($record)),
                Forms\Components\TextInput::make('memory_usage_bytes')
                    ->label('Memory usage (bytes)')
                    ->numeric()
                    ->disabled(fn (?Server $record) => static::isProviderDriven($record))
                    ->helperText(fn (?Server $record) => static::providerDrivenHelperText($record)),
                Forms\Components\Section::make('Resource limits')
                    ->description('Populated by a connector that reports resource limits (e.g. Pelican) — not all connectors support this.')
                    ->collapsed()
                    ->schema([
                        Forms\Components\TextInput::make('node_name')
                            ->label('Node')
                            ->disabled(fn (?Server $record) => static::isProviderDriven($record))
                            ->helperText(fn (?Server $record) => static::providerDrivenHelperText($record)),
                        Forms\Components\TextInput::make('cpu_current')
                            ->label('CPU current (%)')
                            ->numeric()
                            ->disabled(fn (?Server $record) => static::isProviderDriven($record)),
                        Forms\Components\TextInput::make('cpu_limit')
                            ->label('CPU limit (%)')
                            ->numeric()
                            ->disabled(fn (?Server $record) => static::isProviderDriven($record)),
                        Forms\Components\TextInput::make('cpu_percent')
                            ->label('CPU used (% of limit)')
                            ->numeric()
                            ->disabled(fn (?Server $record) => static::isProviderDriven($record)),
                        Forms\Components\TextInput::make('memory_current')
                            ->label('Memory current (bytes)')
                            ->numeric()
                            ->disabled(fn (?Server $record) => static::isProviderDriven($record)),
                        Forms\Components\TextInput::make('memory_limit')
                            ->label('Memory limit (bytes)')
                            ->numeric()
                            ->disabled(fn (?Server $record) => static::isProviderDriven($record)),
                        Forms\Components\TextInput::make('memory_percent')
                            ->label('Memory used (% of limit)')
                            ->numeric()
                            ->disabled(fn (?Server $record) => static::isProviderDriven($record)),
                        Forms\Components\TextInput::make('disk_current')
                            ->label('Disk current (bytes)')
                            ->numeric()
                            ->disabled(fn (?Server $record) => static::isProviderDriven($record)),
                        Forms\Components\TextInput::make('disk_limit')
                            ->label('Disk limit (bytes)')
                            ->numeric()
                            ->disabled(fn (?Server $record) => static::isProviderDriven($record)),
                        Forms\Components\TextInput::make('disk_percent')
                            ->label('Disk used (% of limit)')
                            ->numeric()
                            ->disabled(fn (?Server $record) => static::isProviderDriven($record)),
                        Forms\Components\TextInput::make('network_rx')
                            ->label('Network received (bytes)')
                            ->numeric()
                            ->disabled(fn (?Server $record) => static::isProviderDriven($record)),
                        Forms\Components\TextInput::make('network_tx')
                            ->label('Network sent (bytes)')
                            ->numeric()
                            ->disabled(fn (?Server $record) => static::isProviderDriven($record)),
                    ])
                    ->columns(2),
                Forms\Components\KeyValue::make('metadata')
                    ->columnSpanFull(),
            ]);
    }

    /**
     * True once a Server has any connector-backed provider — from that
     * point on, PollProviders is what actually keeps these fields
     * current, so letting an admin hand-type a value here would just get
     * silently overwritten on the next poll tick. A brand-new Server (no
     * record yet) or one with only manual providers stays editable, since
     * nothing else will ever populate these otherwise. Filament doesn't
     * submit disabled fields, so this alone is enough to stop admin edits
     * from reaching the database — no extra guard needed on save.
     */
    protected static function isProviderDriven(?Server $record): bool
    {
        return $record?->hasConnectorProvider() ?? false;
    }

    protected static function providerDrivenHelperText(?Server $record): ?string
    {
        if (! static::isProviderDriven($record)) {
            return null;
        }

        return 'Provided by '.static::primaryConnectorLabel($record).' — set automatically, not editable here.';
    }

    /**
     * The name of the highest-priority connector-type provider's
     * Connection, matching the exact priority-stack order
     * CapabilityGateway itself walks ("first wins").
     */
    protected static function primaryConnectorLabel(Server $record): string
    {
        $provider = $record->providers()
            ->where('type', 'connector')
            ->orderBy('priority')
            ->orderBy('id')
            ->first();

        return $provider ? (ConnectorInstance::find($provider->connector_instance_id)?->name ?? 'a connector') : 'a connector';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('game.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('slug')
                    ->searchable(),
                Tables\Columns\TextColumn::make('server_group_id')
                    ->label('Group')
                    ->formatStateUsing(fn (?int $state) => $state ? ServerGroup::find($state)?->name : null)
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'online' => 'success',
                        'maintenance' => 'warning',
                        default => 'gray',
                    })
                    ->searchable(),
                Tables\Columns\TextColumn::make('current_players')
                    ->label('Players')
                    ->formatStateUsing(fn (Server $record) => "{$record->current_players}/{$record->max_players}")
                    ->sortable(),
                Tables\Columns\TextColumn::make('node_name')
                    ->label('Node')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('cpu_percent')
                    ->label('CPU %')
                    ->placeholder('—')
                    ->suffix('%')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('memory_percent')
                    ->label('Mem %')
                    ->placeholder('—')
                    ->suffix('%')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('game_id')
                    ->relationship('game', 'name')
                    ->label('Game'),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'online' => 'Online',
                        'offline' => 'Offline',
                        'maintenance' => 'Maintenance',
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
            RelationManagers\ProvidersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServers::route('/'),
            'create' => Pages\CreateServer::route('/create'),
            'edit' => Pages\EditServer::route('/{record}/edit'),
        ];
    }
}
