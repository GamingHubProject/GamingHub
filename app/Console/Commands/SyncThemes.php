<?php

namespace App\Console\Commands;

use App\Experience\ThemeStorage;
use App\Models\Theme;
use Illuminate\Console\Command;

/**
 * Rebuilds every theme's cached index row from its folder on disk.
 *
 * Needed because the folder is the source of truth and the folder is
 * reachable by things that aren't this app — an admin editing theme.json
 * in the Asset Library, a theme unpacked by an import (Phase C) or a
 * registry install (Phase D), or a file restored from a backup. Any of
 * those leaves `payload` and `checksum` describing an older version of the
 * theme until this runs.
 */
class SyncThemes extends Command
{
    protected $signature = 'themes:sync {slug? : Only this theme} {--stale : Only themes whose folder no longer matches the cache}';

    protected $description = 'Re-read every theme folder and refresh its cached index row';

    public function handle(ThemeStorage $storage): int
    {
        $themes = Theme::query()
            ->when($this->argument('slug'), fn ($q, $slug) => $q->where('slug', $slug))
            ->get()
            ->when($this->option('stale'), fn ($c) => $c->filter->isStale());

        if ($themes->isEmpty()) {
            $this->info('Nothing to sync.');

            return self::SUCCESS;
        }

        foreach ($themes as $theme) {
            $storage->sync($theme->refresh());
            // A theme whose checksum is still null after a sync had no
            // readable theme.json — worth surfacing rather than silently
            // leaving the last known payload in place.
            $this->line($theme->refresh()->checksum
                ? "  <info>synced</info>  {$theme->slug}"
                : "  <comment>missing theme.json</comment>  {$theme->slug}");
        }

        $this->info("Synced {$themes->count()} theme(s).");

        return self::SUCCESS;
    }
}
