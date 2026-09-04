<?php

namespace App\Filament\Resources\ThemeResource\Pages;

use App\Experience\ThemeStorage;
use App\Filament\Resources\ThemeResource;
use App\Models\Theme;
use Filament\Resources\Pages\CreateRecord;

/**
 * Creating a theme creates its folder — ThemeStorage::createTheme makes
 * /themes/{slug}/ with its subfolders, an Asset Library folder tree to
 * match, and a starting theme.json. Only then is the rest of the form
 * written into that bundle, so a half-created theme can't exist as a row
 * without a folder.
 *
 * The file-upload fields aren't visible until the record exists (they need
 * a folder to upload into), which is why the create form is colours and
 * name only in practice.
 */
class CreateTheme extends CreateRecord
{
    protected static string $resource = ThemeResource::class;

    protected function handleRecordCreation(array $data): Theme
    {
        $storage = app(ThemeStorage::class);
        $theme = $storage->createTheme($data['name'], auth()->id());

        return $storage->writeBundle($theme, EditTheme::formStateToBundle($data, $theme->bundle()));
    }
}
