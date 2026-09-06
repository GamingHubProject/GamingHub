<?php

namespace App\Console\Commands;

use App\Experience\ThemePackage;
use App\Models\Theme;
use Illuminate\Console\Command;

/**
 * The command-line half of Phase C. Exists for two reasons beyond
 * convenience: it's how a theme package gets built for the registry
 * (Phase D) without going through a browser, and it makes the whole
 * export/import path testable without Filament in the way.
 */
class ExportTheme extends Command
{
    protected $signature = 'themes:export {slug : The theme folder to export} {--out= : Where to write the zip (defaults to the current directory)}';

    protected $description = 'Zip a theme folder into a portable theme package';

    public function handle(ThemePackage $packages): int
    {
        $theme = Theme::where('slug', $this->argument('slug'))->first();

        if (! $theme) {
            $this->error("No theme with slug [{$this->argument('slug')}].");

            return self::FAILURE;
        }

        $out = $this->option('out') ?: getcwd().'/'.$packages->filename($theme);

        if (is_dir($out)) {
            $out = rtrim($out, '/').'/'.$packages->filename($theme);
        }

        $packages->export($theme, $out);

        $this->info("Exported {$theme->name} to {$out} (".number_format(filesize($out) / 1024, 1).' KB)');

        return self::SUCCESS;
    }
}
