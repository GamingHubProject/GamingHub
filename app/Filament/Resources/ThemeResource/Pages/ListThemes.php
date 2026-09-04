<?php

namespace App\Filament\Resources\ThemeResource\Pages;

use App\Experience\ThemeResolver;
use App\Experience\ThemeStorage;
use App\Filament\Resources\ThemeResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListThemes extends ListRecords
{
    protected static string $resource = ThemeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('New theme'),

            /*
             * Editing a theme changes it in place, which is the right
             * default but makes experimenting risky once a theme is live.
             * This is the escape hatch: fork whatever the site is
             * currently using, so an admin can try things on the copy and
             * apply it only if they like the result.
             */
            Actions\Action::make('forkActive')
                ->label('Duplicate the live theme')
                ->icon('heroicon-o-square-2-stack')
                ->color('gray')
                ->visible(fn () => app(ThemeResolver::class)->effectiveTheme() !== null)
                ->form([
                    Forms\Components\TextInput::make('name')
                        ->label('Name for the copy')
                        ->required()
                        ->default(fn () => app(ThemeResolver::class)->effectiveTheme()?->name.' copy'),
                ])
                ->action(function (array $data) {
                    $active = app(ThemeResolver::class)->effectiveTheme();
                    if (! $active) {
                        return;
                    }

                    $copy = app(ThemeStorage::class)->duplicateTheme($active, $data['name']);

                    Notification::make()
                        ->title("Created {$copy->name}")
                        ->body('Edit it, then Apply when you\'re happy — the live theme is untouched until you do.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
