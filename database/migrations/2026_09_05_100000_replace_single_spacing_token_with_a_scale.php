<?php

use App\Experience\ThemeStorage;
use App\Models\Theme;
use Illuminate\Database\Migrations\Migration;

/**
 * The `spacing` token was a single base value that every consumer
 * multiplied for itself — which is a scale, just an undocumented one
 * nobody could change coherently. It's replaced by four named steps
 * (see ThemeBundle::SCALE_TOKENS).
 *
 * A theme carrying the old key would otherwise keep it as an unrecognised
 * extra token: harmless, but it would surface in the admin's "Additional
 * tokens" escape hatch looking like something they'd added, while doing
 * nothing. So it's converted rather than left behind — the old base
 * becomes the `normal` step, and the others are derived from it in the
 * same proportions the built-in themes use, which reproduces what the
 * single value actually produced at each site.
 */
return new class extends Migration
{
    public function up(): void
    {
        $storage = app(ThemeStorage::class);

        foreach (Theme::all() as $theme) {
            $bundle = $theme->bundle();
            $base = $bundle->extraTokens['spacing'] ?? $bundle->tokens['spacing'] ?? null;

            if ($base === null || ! is_numeric($base)) {
                continue;
            }

            unset($bundle->extraTokens['spacing'], $bundle->tokens['spacing']);

            $base = (int) $base;
            $bundle->tokens += [
                'space-tight' => (int) round($base * 0.5),
                'space-normal' => $base,
                'space-loose' => (int) round($base * 1.5),
                'space-section' => $base * 2,
            ];

            $storage->writeBundle($theme, $bundle);
        }
    }

    public function down(): void
    {
        // Not reversed: collapsing four steps back into one base would
        // silently discard whatever an admin set them to individually.
    }
};
