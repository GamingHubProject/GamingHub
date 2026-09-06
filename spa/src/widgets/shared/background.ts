import type { CSSProperties } from 'react';
import { patternBackground } from './backgroundPattern';

/**
 * One background builder, shared by the two things that draw one: a
 * widget's own chrome (widgets/shared/widgetStyle) and the site background
 * a theme sets.
 *
 * It's shared rather than duplicated because the alternative is a
 * background type that works on the page but not on a card — exactly the
 * kind of inconsistency that reads as a bug. Adding a fifth type later
 * means editing this file and nothing else.
 */
export type BackgroundType = 'color' | 'pattern' | 'image' | 'gradient';

/**
 * cover/contain carry their usual CSS meaning; `tile` repeats at natural
 * size; `fill` stretches to the box, ignoring aspect ratio. `fill` moved
 * here from PictureWidget's own private map when the Hero grew the same
 * control — two builders that disagreed on what a fit mode means is the
 * drift this file exists to prevent.
 */
export type BackgroundImageFit = 'cover' | 'contain' | 'tile' | 'fill';

/**
 * Where the artwork sits in its box, as the nine CSS background-position
 * keyword pairs.
 *
 * Presets rather than a free focal point (an x%/y% pair picked by clicking
 * the image): the presets cover "the subject is on the right, put the text
 * left of it", which is what this is actually for, and they drop into the
 * existing Listbox with no new picker component. A focal point stays open
 * as a later refinement — it would be a superset of these values, so a
 * stored preset would still resolve.
 *
 * Written horizontal-then-vertical ('right top', not 'top right').
 * Browsers accept either keyword order; jsdom's CSS parser only accepts
 * this one and silently drops the other, so emitting it this way keeps
 * the rendered value assertable in a test rather than checkable only by
 * eye.
 */
export type BackgroundPosition =
  | 'left top'
  | 'center top'
  | 'right top'
  | 'left center'
  | 'center'
  | 'right center'
  | 'left bottom'
  | 'center bottom'
  | 'right bottom';

/** The default every caller falls back to — and what this builder did
 *  unconditionally before the setting existed. */
export const DEFAULT_BACKGROUND_POSITION: BackgroundPosition = 'center';

/** Shared by every form that offers the control, so Picture and Hero can
 *  never end up with different labels for the same value. */
export const BACKGROUND_POSITION_OPTIONS: { value: BackgroundPosition; label: string }[] = [
  { value: 'left top', label: 'Top left' },
  { value: 'center top', label: 'Top' },
  { value: 'right top', label: 'Top right' },
  { value: 'left center', label: 'Left' },
  { value: 'center', label: 'Centre' },
  { value: 'right center', label: 'Right' },
  { value: 'left bottom', label: 'Bottom left' },
  { value: 'center bottom', label: 'Bottom' },
  { value: 'right bottom', label: 'Bottom right' },
];

/**
 * A fit mode's CSS background-size. Exported because the fit control's
 * labels and this mapping belong together — 'fill' isn't a real
 * background-size keyword, so it can only ever be understood through here.
 */
export const BACKGROUND_IMAGE_SIZE: Record<BackgroundImageFit, string> = {
  cover: 'cover',
  contain: 'contain',
  tile: 'auto',
  fill: '100% 100%',
};

export type GradientKind = 'linear' | 'radial';

export interface GradientStop {
  color: string;
  /** Percentage along the gradient, 0–100. */
  position: number;
}

export interface GradientSpec {
  kind: GradientKind;
  /** Degrees, linear only — ignored for radial, which has no direction. */
  angle: number;
  stops: GradientStop[];
}

/**
 * Everything a background can be, already resolved — no nulls, no
 * fallthrough. Whoever calls this has done the instance -> global ->
 * default resolution themselves.
 */
export interface BackgroundSpec {
  type: BackgroundType;
  color: string | undefined;
  /** 0–1. Tints the base fill and a pattern's ink. Never an image. */
  opacity: number;
  pattern: string | undefined;
  patternColor: string | undefined;
  imageUrl: string | undefined;
  imageFit: BackgroundImageFit;
  /**
   * The one optional field on an otherwise fully-resolved spec. Undefined
   * means centred, which is the only behaviour that existed before this
   * field — so the callers that don't offer the control yet (the site
   * background, a region's, a widget's universal Style background) render
   * exactly as they did, with no config migration.
   */
  imagePosition?: BackgroundPosition;
  gradient: GradientSpec | undefined;
}

/** #rrggbb (what a colour input produces) + a 0–1 opacity -> rgba(). */
export function hexWithOpacity(hex: string, opacity: number): string {
  const match = /^#?([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i.exec(hex);
  if (!match) return hex;
  const [, r, g, b] = match;
  return `rgba(${parseInt(r, 16)}, ${parseInt(g, 16)}, ${parseInt(b, 16)}, ${opacity})`;
}

/**
 * CSS for a gradient, or null when there isn't enough of one to draw —
 * a single stop is a solid colour, not a gradient, and rendering it as
 * one would just look like a bug.
 */
export function gradientCss(gradient: GradientSpec | undefined, opacity = 1): string | null {
  if (!gradient || gradient.stops.length < 2) return null;

  const stops = gradient.stops
    .map((stop) => `${opacity < 1 ? hexWithOpacity(stop.color, opacity) : stop.color} ${stop.position}%`)
    .join(', ');

  return gradient.kind === 'radial'
    ? `radial-gradient(circle at center, ${stops})`
    : `linear-gradient(${gradient.angle}deg, ${stops})`;
}

/**
 * The CSS for a resolved background, as one object to spread.
 *
 * Returns an empty object when nothing is configured, which is the normal
 * case — spreading `{}` leaves the caller's style untouched, so a widget
 * or a page nobody has set a background on renders exactly as it did
 * before any of this existed.
 */
export function backgroundCss(spec: BackgroundSpec): CSSProperties {
  const base = spec.color ? hexWithOpacity(spec.color, spec.opacity) : undefined;
  const orBaseOnly = (): CSSProperties => (base ? { backgroundColor: base } : {});

  if (spec.type === 'gradient') {
    const image = gradientCss(spec.gradient, spec.opacity);

    return image ? { backgroundColor: base, backgroundImage: image } : orBaseOnly();
  }

  if (spec.type === 'pattern') {
    // The ink carries the same opacity as the base fill — one "how solid
    // is this background" control for the whole thing, rather than a
    // second slider that only applies to half of it.
    const ink = spec.patternColor ? hexWithOpacity(spec.patternColor, spec.opacity) : undefined;
    const pattern = ink ? patternBackground(spec.pattern, ink) : null;
    if (!pattern) return orBaseOnly();

    return { backgroundColor: base, backgroundImage: pattern.backgroundImage, backgroundSize: pattern.backgroundSize };
  }

  if (spec.type === 'image') {
    if (!spec.imageUrl) return orBaseOnly();

    return {
      backgroundColor: base,
      backgroundImage: `url(${spec.imageUrl})`,
      // Falls back rather than indexing blind: imageFit reaches here from
      // a stored JSON blob (a region's background, a widget's style), so
      // a value written by a newer build — or a typo — must degrade to
      // the default, not to an empty backgroundSize (which CSS reads as
      // `auto`, i.e. the image at natural size, nothing like a fit mode).
      backgroundSize: BACKGROUND_IMAGE_SIZE[spec.imageFit] ?? BACKGROUND_IMAGE_SIZE.cover,
      backgroundRepeat: spec.imageFit === 'tile' ? 'repeat' : 'no-repeat',
      backgroundPosition: spec.imagePosition ?? DEFAULT_BACKGROUND_POSITION,
    };
  }

  return orBaseOnly();
}
