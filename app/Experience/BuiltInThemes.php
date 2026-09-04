<?php

namespace App\Experience;

use App\Models\Theme;
use App\Models\ThemeAssignment;

/**
 * The themes a fresh install ships with, so the picker opens on real
 * options rather than an empty list and a "New theme" button.
 *
 * These are deliberately opinionated rather than neutral. They target the
 * product's actual visual direction — dark ground, one strong accent used
 * sparingly, generous corner rounding, roomy spacing — because a default
 * that looks like unstyled Bootstrap teaches an admin nothing about what
 * the theme system can do, and is the thing they'd have to undo first.
 *
 * Each palette is built the same way, which is what makes them read as a
 * set rather than three unrelated colour schemes:
 *
 *   background      the darkest ground, near-black with a hue bias toward
 *                   the accent so it reads as chosen rather than grey
 *   surface         one step up — cards, header, sidebar
 *   surface-muted   one step up again — hover states, inset rows
 *   border          low-contrast separator, close to surface, never a
 *                   hard line
 *   text / muted    high-contrast body, and a dimmed companion
 *   accent          the one saturated colour, for actions only
 *
 * Widget defaults come along with each theme because chrome is part of a
 * look: borderless cards on a lifted surface read very differently from
 * bordered cards, and that choice belongs to the theme rather than being
 * something an admin has to reproduce widget by widget.
 */
class BuiltInThemes
{
    /**
     * @return array<string, array{name: string, tokens: array<string, string|int>, widgetStyle: array<string, mixed>}>
     */
    public static function all(): array
    {
        return [
            // Violet on near-black. The primary direction: the accent
            // appears on actions and almost nowhere else, so a single
            // "Join now" button carries the whole page.
            'nebula' => [
                'name' => 'Nebula',
                'tokens' => [
                    'background' => '#0b0a14',
                    'surface' => '#151322',
                    'surface-muted' => '#1e1b33',
                    'text' => '#ece9f5',
                    'muted' => '#9d97b8',
                    'border' => '#272341',
                    'accent' => '#7c5cff',
                    'accent-contrast' => '#ffffff',
                    'radius' => 14,
                    'spacing' => 16,
                ],
                // No border, lifted surface: cards separate from the ground
                // by tone rather than by an outline, which is what keeps a
                // dense multi-column layout from turning into a grid of
                // boxes.
                'widgetStyle' => [
                    'border_enabled' => false,
                    'border_radius' => 14,
                    'background_type' => 'color',
                    'background_color' => '#151322',
                    'background_opacity' => 1,
                ],
            ],

            // Crimson on true black — higher contrast, heavier, for a site
            // that wants the artwork to sit against nothing at all.
            'crimson' => [
                'name' => 'Crimson',
                'tokens' => [
                    'background' => '#0c0c0d',
                    'surface' => '#171718',
                    'surface-muted' => '#212123',
                    'text' => '#f2f1f0',
                    'muted' => '#9b9a99',
                    'border' => '#2a2a2c',
                    'accent' => '#e63946',
                    'accent-contrast' => '#ffffff',
                    'radius' => 12,
                    'spacing' => 16,
                ],
                'widgetStyle' => [
                    'border_enabled' => false,
                    'border_radius' => 12,
                    'background_type' => 'color',
                    'background_color' => '#171718',
                    'background_opacity' => 1,
                ],
            ],

            // The same design language inverted, so "light" isn't a
            // fallback to nothing. Kept warm rather than pure white, and
            // the accent is darkened to hold contrast on a light ground —
            // Nebula's violet at the same value would be illegible here.
            'daybreak' => [
                'name' => 'Daybreak',
                'tokens' => [
                    'background' => '#f4f3f7',
                    'surface' => '#ffffff',
                    'surface-muted' => '#ebe9f0',
                    'text' => '#16141f',
                    'muted' => '#666080',
                    'border' => '#dedbe6',
                    'accent' => '#5b3fd9',
                    'accent-contrast' => '#ffffff',
                    'radius' => 14,
                    'spacing' => 16,
                ],
                // A border earns its place here: on a light ground, white
                // cards on near-white have too little tonal separation to
                // read on their own.
                'widgetStyle' => [
                    'border_enabled' => true,
                    'border_thickness' => 1,
                    'border_color' => '#dedbe6',
                    'border_radius' => 14,
                    'background_type' => 'color',
                    'background_color' => '#ffffff',
                    'background_opacity' => 1,
                ],
            ],
        ];
    }

    /**
     * Create any built-in theme that isn't present yet, and assign one to
     * the platform if nothing is assigned at all.
     *
     * Idempotent by slug, so it's safe on every deploy: an admin who has
     * edited Nebula keeps their edits, and one who deleted it doesn't get
     * it forced back on the next release. `is_builtin` marks them in the
     * list — it doesn't protect them, since a built-in an admin doesn't
     * want should be deletable like anything else.
     */
    public static function seed(ThemeStorage $storage): void
    {
        foreach (self::all() as $slug => $spec) {
            if (Theme::where('slug', $slug)->exists()) {
                continue;
            }

            $theme = $storage->createTheme($spec['name']);
            $theme->forceFill(['is_builtin' => true])->save();

            $storage->writeBundle($theme, new ThemeBundle(
                id: $theme->slug,
                name: $spec['name'],
                tokens: $spec['tokens'],
                widgetStyle: $spec['widgetStyle'],
            ));
        }

        if (! ThemeAssignment::where('level', ThemeAssignment::LEVEL_PLATFORM)->exists()) {
            if ($nebula = Theme::where('slug', 'nebula')->first()) {
                ThemeAssignment::assign(ThemeAssignment::LEVEL_PLATFORM, $nebula->id);
            }
        }
    }
}
