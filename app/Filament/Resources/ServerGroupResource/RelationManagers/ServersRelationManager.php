<?php

namespace App\Filament\Resources\ServerGroupResource\RelationManagers;

use App\Filament\Resources\ServerResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use GamingHub\Core\Models\Server;

/**
 * The group's table previously only showed a servers_count column — this
 * is the actual list, so an admin doesn't have to leave the group to see
 * which servers are in it. List-only: Server's own create/edit form
 * already lives in ServerResource (with its provider-driven read-only
 * field logic), so rows link out to it via "View" rather than
 * reimplementing that form here.
 */
class ServersRelationManager extends RelationManager
{
    protected static string $relationship = 'servers';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'online' => 'success',
                        'maintenance' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('current_players')
                    ->label('Players')
                    ->formatStateUsing(fn (Server $record) => "{$record->current_players}/{$record->max_players}"),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Server $record) => ServerResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}
