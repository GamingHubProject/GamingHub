<?php

namespace Database\Seeders;

use App\Experience\BuiltInThemes;
use App\Experience\ThemeStorage;
use Illuminate\Database\Seeder;

/**
 * The built-in themes (see App\Experience\BuiltInThemes, which owns the
 * palettes and the idempotency). Separate from the class itself so the
 * same seeding runs from `db:seed`, from a fresh install and from the
 * migration that backfills existing sites.
 */
class ThemeSeeder extends Seeder
{
    public function run(): void
    {
        BuiltInThemes::seed(app(ThemeStorage::class));
    }
}
