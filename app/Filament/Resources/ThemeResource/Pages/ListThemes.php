<?php

namespace App\Filament\Resources\ThemeResource\Pages;

use App\Experience\ThemePackage;
use App\Experience\ThemePackageException;
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

            static::importAction(),

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

    /**
     * The other way a theme gets onto this install: as a file. Sits beside
     * "New theme" because that's what it produces — the fact that the
     * contents came from a zip is an implementation detail of creating it.
     *
     * The upload is deliberately NOT stored in the Asset Library
     * (storeFiles(false)): the zip is a courier, not an asset. What the
     * admin wants kept is what comes out of it, and that gets its own
     * folder like every other theme.
     */
    private static function importAction(): Actions\Action
    {
        return Actions\Action::make('import')
            ->label('Import a theme')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('gray')
            ->modalDescription('A theme package is a zip holding theme.json plus the theme\'s own font, favicon and background files.')
            ->form([
                Forms\Components\FileUpload::make('package')
                    ->label('Theme package (.zip)')
                    ->acceptedFileTypes(['application/zip', 'application/x-zip-compressed', 'multipart/x-zip'])
                    ->storeFiles(false)
                    ->required(),
                Forms\Components\TextInput::make('name')
                    ->label('Name')
                    ->helperText('Leave blank to use the name inside the package.'),
                Forms\Components\Radio::make('on_conflict')
                    ->label('If a theme of that name already exists')
                    ->options([
                        'copy' => 'Import it alongside, as a separate copy',
                        'replace' => 'Replace it, keeping where it is applied',
                    ])
                    ->descriptions([
                        'replace' => 'The existing theme keeps its folder name and stays applied to whatever it is applied to — only its contents change.',
                    ])
                    ->default('copy')
                    ->required(),
            ])
            ->action(function (array $data) {
                $file = $data['package'];
                $path = is_array($file) ? reset($file)->getRealPath() : $file->getRealPath();

                try {
                    $theme = app(ThemePackage::class)->import(
                        $path,
                        $data['name'] ?: null,
                        $data['on_conflict'],
                    );
                } catch (ThemePackageException $e) {
                    // The exception messages are written for exactly this
                    // audience, so they are shown rather than summarised.
                    Notification::make()
                        ->title('That package could not be imported')
                        ->body($e->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title("Imported {$theme->name}")
                    ->body('It is not applied to anything yet — use Apply when you want it live.')
                    ->success()
                    ->send();
            });
    }
}
