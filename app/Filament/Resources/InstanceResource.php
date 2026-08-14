<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InstanceResource\Pages;
use App\Models\ConfigurationPreset;
use App\Models\Instance;
use App\Models\Server;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class InstanceResource extends Resource
{
    protected static ?string $model = Instance::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('server_id')
                    ->relationship('server', 'name')
                    ->required()
                    ->live(),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('apply_preset')
                    ->label('Apply preset')
                    ->dehydrated(false)
                    ->live()
                    ->options(fn (Get $get) => static::presetOptions($get('server_id')))
                    ->visible(fn (Get $get) => static::presetOptions($get('server_id')) !== [])
                    ->helperText('Copies the preset\'s values into the fields below — still needs Save.')
                    ->afterStateUpdated(function (?string $state, Set $set) {
                        if (! $state) {
                            return;
                        }
                        $preset = ConfigurationPreset::find($state);
                        foreach ($preset?->values ?? [] as $key => $value) {
                            $set("configuration.{$key}", $value);
                        }
                    }),
                Forms\Components\Group::make()
                    ->schema(fn (Get $get) => static::configurationFields($get('server_id')))
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Builds one typed form field per entry in the selected server's game's
     * configuration_schema, bound to configuration.{key} via dot notation
     * (Filament resolves this against the model's array-cast `configuration`
     * column automatically).
     *
     * @return array<int, Forms\Components\Component>
     */
    protected static function configurationFields(?string $serverId): array
    {
        $schema = static::schemaFor($serverId);

        if ($schema === []) {
            return [
                Forms\Components\Placeholder::make('no_schema')
                    ->label('')
                    ->content('Select a server whose game has a configuration schema to edit settings here.'),
            ];
        }

        return collect($schema)->map(function (array $setting) {
            $key = $setting['key'] ?? null;

            if (! $key) {
                return null;
            }

            $name = "configuration.{$key}";
            $label = ($setting['description'] ?? '') ?: $key;

            $field = match ($setting['type'] ?? 'string') {
                'boolean' => Forms\Components\Toggle::make($name)->label($label),
                'integer', 'decimal' => Forms\Components\TextInput::make($name)
                    ->label($label)
                    ->numeric()
                    ->minValue(is_numeric($setting['min'] ?? null) ? (float) $setting['min'] : null)
                    ->maxValue(is_numeric($setting['max'] ?? null) ? (float) $setting['max'] : null),
                default => Forms\Components\TextInput::make($name)->label($label),
            };

            if (! empty($setting['requiresRestart'])) {
                $field->hintIcon('heroicon-o-arrow-path', tooltip: 'Requires a server restart to apply.');
            }

            if (($setting['default'] ?? null) !== null && ($setting['default'] ?? '') !== '') {
                $field->default($setting['default']);
            }

            return $field;
        })->filter()->values()->all();
    }

    protected static function schemaFor(?string $serverId): array
    {
        if (! $serverId) {
            return [];
        }

        return Server::find($serverId)?->game?->configuration_schema ?? [];
    }

    protected static function presetOptions(?string $serverId): array
    {
        if (! $serverId) {
            return [];
        }

        $gameId = Server::find($serverId)?->game_id;

        if (! $gameId) {
            return [];
        }

        return ConfigurationPreset::where('game_id', $gameId)->pluck('name', 'id')->all();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('server.name')
                    ->label('Server')
                    ->sortable(),
                Tables\Columns\TextColumn::make('server.game.name')
                    ->label('Game'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('server_id')
                    ->relationship('server', 'name')
                    ->label('Server'),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInstances::route('/'),
            'create' => Pages\CreateInstance::route('/create'),
            'edit' => Pages\EditInstance::route('/{record}/edit'),
        ];
    }
}
