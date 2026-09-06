<?php

namespace App\Console\Commands;

use App\Experience\ThemePackage;
use App\Experience\ThemePackageException;
use Illuminate\Console\Command;

class ImportTheme extends Command
{
    protected $signature = 'themes:import {file : Path to a theme package zip}
        {--name= : Import under this name instead of the one in the package}
        {--replace : Overwrite an existing theme of the same name, keeping where it is applied}
        {--inspect : Report what the package contains without importing it}';

    protected $description = 'Unpack a theme package into a theme folder';

    public function handle(ThemePackage $packages): int
    {
        $file = $this->argument('file');

        if (! is_file($file)) {
            $this->error("No file at [{$file}].");

            return self::FAILURE;
        }

        try {
            $info = $packages->inspect($file);

            $this->line("  <info>{$info['name']}</info>  v{$info['version']}  ({$info['files']} file(s))");

            if ($this->option('inspect')) {
                return self::SUCCESS;
            }

            $name = $this->option('name') ?: $info['name'];
            $conflict = $packages->conflictFor($name);

            if ($conflict && ! $this->option('replace')) {
                $this->warn("A theme named [{$name}] already exists. Importing as a separate copy — pass --replace to overwrite it instead.");
            }

            $theme = $packages->import($file, $name, $this->option('replace') ? 'replace' : 'copy');

            $this->info("Imported as [{$theme->slug}].");

            return self::SUCCESS;
        } catch (ThemePackageException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
