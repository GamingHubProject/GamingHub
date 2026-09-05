import type { CSSProperties } from 'react';
import { backgroundCss } from '../widgets/shared/background';
import type { BackgroundImageFit, BackgroundType, GradientSpec } from '../widgets/shared/background';

/**
 * The header and the sidebar as symmetrical, independently styled regions.
 *
 * One resolver rather than two so the surfaces can't drift on what
 * "transparent" or "soft shadow" means — and so a region gaining a
 * property gains it on both. What differs between them is only which edge
 * carries the border, which the caller says.
 */
export interface RegionBorder {
  color?: string | null;
  thickness?: number | null;
}

export interface RegionStyle {
  transparent?: boolean;
  background?: {
    type?: BackgroundType;
    color?: string;
    opacity?: number;
    pattern?: string;
    pattern_color?: string;
    image_url?: string;
    image_fit?: BackgroundImageFit;
    gradient?: GradientSpec;
  };
  text_color?: string | null;
  accent_color?: string | null;
  border?: RegionBorder;
  shadow?: 'none' | 'soft' | 'strong';
  show_branding?: boolean;
}

/**
 * Named presets rather than a free-form box-shadow: an admin can judge
 * "soft" against "strong"; they can't judge a blur radius. Values are
 * side-agnostic so the same preset reads right under a header and beside
 * a sidebar.
 */
const SHADOWS: Record<string, string> = {
  none: '',
  soft: '0 1px 12px rgba(0, 0, 0, 0.12)',
  strong: '0 2px 24px rgba(0, 0, 0, 0.28)',
};

/**
 * CSS for one region. `edge` is which side its border sits on — the
 * sidebar's is always its right, the header's always its bottom, so the
 * caller states it rather than the theme carrying a control whose only
 * sensible value is its default.
 *
 * A transparent region drops its background AND its edge together: a
 * divider line floating over a page background is the seam transparency
 * exists to remove.
 */
export type RegionEdge = 'bottom' | 'right' | 'all';

export function regionCss(region: RegionStyle | undefined, edge: RegionEdge): CSSProperties {
  if (!region) return {};

  // 'all' is what a *contained* region wants: once the sidebar floats
  // clear of the viewport with rounded corners, a single curved line down
  // one side reads as a rendering bug rather than as a border.
  const borderProperty = edge === 'all' ? 'border' : edge === 'bottom' ? 'borderBottom' : 'borderRight';

  if (region.transparent) {
    return {
      background: 'transparent',
      [borderProperty]: 'none',
      color: region.text_color ?? undefined,
    };
  }

  const background = region.background?.type
    ? backgroundCss({
        type: region.background.type,
        color: region.background.color,
        opacity: region.background.opacity ?? 1,
        pattern: region.background.pattern,
        patternColor: region.background.pattern_color,
        imageUrl: region.background.image_url,
        imageFit: region.background.image_fit ?? 'cover',
        gradient: region.background.gradient,
      })
    : // Nothing configured falls back to the surface token, which is what
      // the region looked like before it had a background of its own.
      { backgroundColor: 'var(--surface, transparent)' };

  const thickness = region.border?.thickness ?? 1;
  const shadow = SHADOWS[region.shadow ?? 'none'];

  return {
    ...background,
    color: region.text_color ?? undefined,
    [borderProperty]: thickness > 0 ? `${thickness}px solid ${region.border?.color ?? 'var(--border, #ddd)'}` : 'none',
    ...(shadow ? { boxShadow: shadow } : {}),
  };
}

/**
 * The colour a region's current item takes. Falls back to the site accent
 * so a region that wants it needn't restate it — which also means changing
 * the theme's accent moves both surfaces unless one has opted out.
 */
export function regionAccent(region: RegionStyle | undefined): string {
  return region?.accent_color || 'var(--accent, inherit)';
}
