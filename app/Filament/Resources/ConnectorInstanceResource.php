<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ConnectorInstanceResource\Pages;
use App\Models\ConnectorInstance;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ConnectorInstanceResource extends Resource
{
    protected static ?string $model = ConnectorInstance::class;

    protected static ?string $navigationIcon = 'heroicon-o-bolt';

    protected static ?string $navigationGroup = 'Capabilities';

    protected static ?string $navigationLabel = 'Connectors';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Connection')
                    ->description(
                        'A credentialed connection to one external system — e.g. one Palworld '
                        .'server\'s REST API, or one Pelican panel. Bind capabilities to this '
                        .'under Capabilities → Bindings.'
                    )
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->helperText('e.g. "EU-1 Palworld REST API".'),
                        Forms\Components\Select::make('type')
                            ->options([
                                'rest' => 'Generic REST (Basic/Bearer/API-key auth)',
                                'pelican' => 'Pelican Panel (Client API)',
                            ])
                            ->required()
                            ->live(),
                        Forms\Components\TextInput::make('base_url')
                            ->label('Base URL')
                            ->required()
                            ->url()
                            ->helperText(fn (Forms\Get $get) => match ($get('type')) {
                                'pelican' => 'Your Pelican panel URL, e.g. https://panel.example.com',
                                default => 'e.g. http://your-server-ip:8212 for Palworld\'s REST API',
                            }),
                        Forms\Components\Select::make('status')
                            ->options([
                                'untested' => 'Untested',
                                'ok' => 'OK',
                                'error' => 'Error',
                            ])
                            ->default('untested')
                            ->required(),
                        Forms\Components\KeyValue::make('credentials')
                            ->helperText(fn (Forms\Get $get) => match ($get('type')) {
                                'pelican' => 'Key: "token" → your Pelican Client API key.',
                                default => 'Palworld REST API: keys "username" (usually "admin") and '
                                    .'"password" (the server\'s AdminPassword). Or for bearer/API-key '
                                    .'REST APIs, use key "token".',
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge(),
                Tables\Columns\TextColumn::make('base_url')
                    ->label('Base URL'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'ok' => 'success',
                        'error' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'rest' => 'Generic REST',
                        'pelican' => 'Pelican Panel',
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
            'index' => Pages\ListConnectorInstances::route('/'),
            'create' => Pages\CreateConnectorInstance::route('/create'),
            'edit' => Pages\EditConnectorInstance::route('/{record}/edit'),
        ];
    }
}
