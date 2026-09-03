/**
 * The built-in tileable pattern library for a widget's `pattern` background
 * mode (see widgetStyle.ts's background_type).
 *
 * Every pattern is pure CSS gradients — no image files, no assets, nothing
 * to upload or embed. That's deliberate on three counts: a gradient tiles
 * and scales perfectly at any widget size, it takes its ink color as a
 * parameter (so one pattern serves every palette rather than needing one
 * uploaded file per color), and a theme export (Phase C) only has to carry
 * the pattern's id string rather than embedding binary image data.
 *
 * Admin-uploadable custom patterns are deliberately NOT here — a built-in
 * set covers the "give this widget some texture" case, and a custom tile
 * is already expressible today via the `image` background mode with `tile`
 * fit, which reuses the Asset Library instead of inventing a parallel one.
 */
export interface BackgroundPattern {
  id: string;
  label: string;
  /** CSS `background-image` for this pattern, drawn in `color`. */
  image: (color: string) => string;
  /**
   * CSS `background-size` that makes one tile. Omitted for the
   * `repeating-*-gradient` patterns, which carry their own period in the
   * gradient stops and tile without needing a size at all.
   */
  size?: string;
}

export const BACKGROUND_PATTERNS: BackgroundPattern[] = [
  {
    id: 'dots',
    label: 'Dots',
    image: (color) => `radial-gradient(circle, ${color} 1.5px, transparent 1.6px)`,
    size: '12px 12px',
  },
  {
    id: 'grid',
    label: 'Grid',
    image: (color) => `linear-gradient(${color} 1px, transparent 1px), linear-gradient(90deg, ${color} 1px, transparent 1px)`,
    size: '16px 16px',
  },
  {
    id: 'diagonal-stripes',
    label: 'Diagonal stripes',
    image: (color) => `repeating-linear-gradient(45deg, ${color} 0 4px, transparent 4px 12px)`,
  },
  {
    id: 'crosshatch',
    label: 'Crosshatch',
    image: (color) =>
      `repeating-linear-gradient(45deg, ${color} 0 1px, transparent 1px 10px), repeating-linear-gradient(-45deg, ${color} 0 1px, transparent 1px 10px)`,
  },
  {
    id: 'checkerboard',
    label: 'Checkerboard',
    image: (color) => `conic-gradient(${color} 0 25%, transparent 0 50%, ${color} 0 75%, transparent 0)`,
    size: '16px 16px',
  },
];

export function getBackgroundPattern(id: string | undefined): BackgroundPattern | undefined {
  return BACKGROUND_PATTERNS.find((pattern) => pattern.id === id);
}

/**
 * The `background-image` + `background-size` pair for a pattern id, or null
 * when the id isn't one of the built-ins — an unknown id (a config written
 * by an older/newer build, or a hand-edited theme import) degrades to "no
 * pattern" rather than throwing or rendering something arbitrary.
 */
export function patternBackground(
  id: string | undefined,
  color: string
): { backgroundImage: string; backgroundSize: string | undefined } | null {
  const pattern = getBackgroundPattern(id);
  if (!pattern) return null;

  return { backgroundImage: pattern.image(color), backgroundSize: pattern.size };
}
