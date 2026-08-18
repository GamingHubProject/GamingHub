<?php

namespace App\Filament\Resources\ServerResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Read-only — allocations are entirely connector-owned, replaced wholesale
 * on every poll tick by ServerAllocationSyncer (see PollProviders), so
 * there's nothing here for an admin to create/edit/delete. No form(), no
 * header/row actions: this is a live view of what the connector last
 * reported, not an editable list.
 */
class AllocationsRelationManager extends RelationManager
{
    protected static string $relationship = 'allocations';

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return 'Allocations';
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('ip')
            ->columns([
                Tables\Columns\TextColumn::make('ip'),
                Tables\Columns\TextColumn::make('ip_alias')
                    ->label('Alias')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('port'),
                Tables\Columns\IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),
                Tables\Columns\TextColumn::make('notes')
                    ->placeholder('—')
                    ->limit(40),
            ]);
    }
}
