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

/** cover/contain carry their usual CSS meaning; `tile` repeats at natural size. */
export type BackgroundImageFit = 'cover' | 'contain' | 'tile';

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
      backgroundSize: spec.imageFit === 'tile' ? 'auto' : spec.imageFit,
      backgroundRepeat: spec.imageFit === 'tile' ? 'repeat' : 'no-repeat',
      backgroundPosition: 'center',
    };
  }

  return orBaseOnly();
}
