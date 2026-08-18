<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AdminAuditResource\Pages;
use App\Models\AdminAudit;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read-only by construction — no create/edit routes, no Create/Edit/Delete
 * actions anywhere in the table. Audit rows are system-generated and
 * immutable; there's nothing here for an admin to change. Same pattern as
 * AllocationsRelationManager (connector-owned data, not admin-editable).
 *
 * No explicit permission grant exists for this resource anywhere in the
 * scoped-permission system, and Filament denies by default without one —
 * so this is Admin-only already, without needing its own auth override.
 */
class AdminAuditResource extends Resource
{
    protected static ?string $model = AdminAudit::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'Audit Log';

    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user_name')
                    ->label('User')
                    ->description(fn (AdminAudit $record) => $record->user_email)
                    ->searchable(['user_name', 'user_email']),
                Tables\Columns\TextColumn::make('action')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'created' => 'success',
                        'deleted' => 'danger',
                        'updated' => 'warning',
                        'role_changed', 'permissions_changed' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('resource_type')
                    ->label('Resource')
                    ->formatStateUsing(fn (AdminAudit $record) => $record->resource_id
                        ? "{$record->resource_type} #{$record->resource_id}"
                        : $record->resource_type),
                Tables\Columns\TextColumn::make('ip')
                    ->label('IP')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('country')
                    ->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('User')
                    ->options(fn () => User::query()->pluck('name', 'id')),
                Tables\Filters\SelectFilter::make('action')
                    ->options(fn () => AdminAudit::query()->distinct()->pluck('action', 'action')),
                Tables\Filters\SelectFilter::make('resource_type')
                    ->label('Resource type')
                    ->options(fn () => AdminAudit::query()->distinct()->pluck('resource_type', 'resource_type')),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
                            ->when($data['until'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('view_changes')
                    ->label('Changes')
                    ->icon('heroicon-o-eye')
                    ->visible(fn (AdminAudit $record) => filled($record->changes))
                    ->modalContent(fn (AdminAudit $record) => view('filament.admin-audit-changes', ['record' => $record]))
                    ->modalHeading(fn (AdminAudit $record) => "{$record->action}: {$record->resource_type} #{$record->resource_id}")
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalWidth(MaxWidth::TwoExtraLarge)
                    ->slideOver(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdminAudits::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
