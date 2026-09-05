import type { CSSProperties } from 'react';
import { backgroundCss, hexWithOpacity } from './background';
import type { BackgroundImageFit, BackgroundType, GradientSpec } from './background';

// Re-exported so existing importers (WidgetStyleSection, the container)
// keep one place to get these from.
export type { BackgroundImageFit, BackgroundType, GradientSpec };
export { hexWithOpacity };

/**
 * Universal per-widget style overrides — Border/Text/Background, the same
 * null-means-sync-to-global convention as the font system's
 * `font_asset_id`. Lives inside a widget's own `config.style` (no new
 * column — `config` is already a free-form JSON blob every widget owns),
 * merged against SiteOption's `widget_style_defaults` (fetched via
 * `/api/v1/theme`'s `widgetStyle` key) and finally a hardcoded fallback
 * that reproduces today's pre-existing look exactly — this is additive,
 * not a default-appearance change, unless an admin actually sets
 * something.
 *
 * Deliberately purely per-widget-instance — no page-level tier like font
 * has (confirmed: border/text/background are naturally per-widget
 * choices, not a whole-page aesthetic).
 */
export interface WidgetStyleOverride {
  border_enabled: boolean | null;
  border_thickness: number | null;
  border_color: string | null;
  border_radius: number | null;
  text_size: number | null;
  text_color: string | null;
  // Self-scaling widgets (see registry.ts's selfScaling) already have a
  // proportional clamp()-based size from widgets/shared/cardScale.ts —
  // text_size (an absolute px) would just fight that, so they use this
  // instead: a multiplier applied to every bound of the existing clamp(),
  // preserving "title bigger than body" while still letting an admin
  // nudge the whole card. 1 (or null/unset) = unchanged. Unused/ignored
  // by any non-self-scaling widget.
  text_scale: number | null;
  /**
   * Which of the three background fields below actually renders. Null (or
   * an unset/unknown value) resolves to 'color', which is exactly what
   * every widget did before pattern/image existed — so no migration and no
   * appearance change for a config written by an older build.
   */
  background_type: BackgroundType | null;
  /** The base fill, in every mode — a pattern's ink and an image both draw
   *  on top of it, so it doubles as the backdrop there. Unchanged meaning
   *  from before background_type existed. */
  background_color: string | null;
  /** Applies to the base fill above, and to a pattern's ink. NOT to an
   *  image — a half-transparent background image is a job for the image
   *  itself (or the Picture widget's dark-overlay control), not for a
   *  control whose whole purpose here is tinting flat fills. */
  background_opacity: number | null;
  /** One of backgroundPattern.ts's built-in ids. */
  background_pattern: string | null;
  /** The pattern's ink, drawn over background_color. Separate from it on
   *  purpose: one shared color would make "dark card, light dots"
   *  impossible without stacking two widgets. */
  background_pattern_color: string | null;
  // id + url kept as a redundant pair, exactly like PictureWidget's
  // background_asset_id/background_url: the id is the real reference (for
  // a future "is this asset still in use" check), the url is a snapshot so
  // the widget renders without a second fetch per view.
  background_image_asset_id: number | null;
  background_image_url: string | null;
  background_image_fit: BackgroundImageFit | null;
  /** Linear or radial with 2–3 stops. Shared with the site background —
   *  see widgets/shared/background.ts. */
  background_gradient: GradientSpec | null;
}



export const EMPTY_WIDGET_STYLE: WidgetStyleOverride = {
  border_enabled: null,
  border_thickness: null,
  border_color: null,
  border_radius: null,
  text_size: null,
  text_color: null,
  text_scale: null,
  background_type: null,
  background_color: null,
  background_opacity: null,
  background_pattern: null,
  background_pattern_color: null,
  background_image_asset_id: null,
  background_image_url: null,
  background_image_fit: null,
  background_gradient: null,
};

export interface ResolvedWidgetStyle {
  borderEnabled: boolean;
  borderThickness: number;
  borderColor: string | undefined;
  borderRadius: number;
  textSize: number | undefined;
  textColor: string | undefined;
  textScale: number;
  backgroundType: BackgroundType;
  backgroundColor: string | undefined;
  backgroundOpacity: number;
  backgroundPattern: string | undefined;
  backgroundPatternColor: string | undefined;
  backgroundImageUrl: string | undefined;
  backgroundImageFit: BackgroundImageFit;
  backgroundGradient: GradientSpec | undefined;
}

/**
 * Reproduces PageLayoutWidgetContainer's pre-existing hardcoded look
 * exactly (a 1px var(--border, #ddd) border with an 8px radius, no forced
 * text size/color, no background, cards at their normal 1× scale) — the
 * state every widget is already in today, so a fresh install or a widget
 * nobody has touched the style of renders identically to before this
 * system existed.
 */
const FALLBACK: ResolvedWidgetStyle = {
  borderEnabled: true,
  borderThickness: 1,
  borderColor: undefined,
  borderRadius: 8,
  textSize: undefined,
  textColor: undefined,
  textScale: 1,
  backgroundType: 'color',
  backgroundColor: undefined,
  backgroundOpacity: 1,
  backgroundPattern: undefined,
  backgroundPatternColor: undefined,
  backgroundImageUrl: undefined,
  backgroundImageFit: 'cover',
  backgroundGradient: undefined,
};

function readStyle(config: Record<string, unknown> | null | undefined): Partial<WidgetStyleOverride> {
  const style = config?.style;
  return style && typeof style === 'object' ? (style as Partial<WidgetStyleOverride>) : {};
}

/**
 * Per-property fallthrough: this widget instance's own value if set, else
 * the app-wide global default if set, else the hardcoded baseline above.
 * `globalDefaults` is whatever `/api/v1/theme`'s `widgetStyle` returned —
 * a partial object (an admin who's never touched Site Options gets `{}`).
 */
export function resolveWidgetStyle(
  instanceConfig: Record<string, unknown> | null | undefined,
  globalDefaults: Partial<WidgetStyleOverride> | null | undefined
): ResolvedWidgetStyle {
  const instance = readStyle(instanceConfig);
  const global = globalDefaults ?? {};

  return {
    borderEnabled: instance.border_enabled ?? global.border_enabled ?? FALLBACK.borderEnabled,
    borderThickness: instance.border_thickness ?? global.border_thickness ?? FALLBACK.borderThickness,
    borderColor: instance.border_color ?? global.border_color ?? FALLBACK.borderColor,
    borderRadius: instance.border_radius ?? global.border_radius ?? FALLBACK.borderRadius,
    textSize: instance.text_size ?? global.text_size ?? FALLBACK.textSize,
    textColor: instance.text_color ?? global.text_color ?? FALLBACK.textColor,
    textScale: instance.text_scale ?? global.text_scale ?? FALLBACK.textScale,
    backgroundType: instance.background_type ?? global.background_type ?? FALLBACK.backgroundType,
    backgroundColor: instance.background_color ?? global.background_color ?? FALLBACK.backgroundColor,
    backgroundOpacity: instance.background_opacity ?? global.background_opacity ?? FALLBACK.backgroundOpacity,
    backgroundPattern: instance.background_pattern ?? global.background_pattern ?? FALLBACK.backgroundPattern,
    backgroundPatternColor:
      instance.background_pattern_color ?? global.background_pattern_color ?? FALLBACK.backgroundPatternColor,
    backgroundImageUrl: instance.background_image_url ?? global.background_image_url ?? FALLBACK.backgroundImageUrl,
    backgroundImageFit: instance.background_image_fit ?? global.background_image_fit ?? FALLBACK.backgroundImageFit,
    backgroundGradient: instance.background_gradient ?? global.background_gradient ?? FALLBACK.backgroundGradient,
  };
}

/**
 * A widget's background, delegated to the shared builder so a widget and
 * the site background can never drift apart on what a "pattern" or a
 * "gradient" means. This function's only job is the mapping.
 */
export function backgroundStyle(style: ResolvedWidgetStyle): CSSProperties {
  return backgroundCss({
    type: style.backgroundType,
    color: style.backgroundColor,
    opacity: style.backgroundOpacity,
    pattern: style.backgroundPattern,
    patternColor: style.backgroundPatternColor,
    imageUrl: style.backgroundImageUrl,
    imageFit: style.backgroundImageFit,
    gradient: style.backgroundGradient,
  });
}

function relativeLuminance(hex: string): number | null {
  const match = /^#?([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i.exec(hex);
  if (!match) return null;
  const [r, g, b] = [match[1], match[2], match[3]].map((component) => {
    const c = parseInt(component, 16) / 255;
    return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
  });
  return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

/**
 * WCAG 2's relative-luminance contrast ratio, 1 (identical, illegible) to
 * 21 (black on white). Returns null when either color isn't a real
 * #rrggbb hex — callers should skip the check entirely rather than treat
 * that as "bad contrast".
 */
export function contrastRatio(foregroundHex: string, backgroundHex: string): number | null {
  const l1 = relativeLuminance(foregroundHex);
  const l2 = relativeLuminance(backgroundHex);
  if (l1 === null || l2 === null) return null;

  const lighter = Math.max(l1, l2);
  const darker = Math.min(l1, l2);
  return (lighter + 0.05) / (darker + 0.05);
}

/**
 * WCAG AA's own bar for normal-size body text — used here only to decide
 * when to show an advisory warning (see WidgetStyleSection), never to
 * block or silently rewrite an admin's chosen color. A real design might
 * deliberately want lower contrast (e.g. a subtle watermark), so this is
 * a nudge, not a rule.
 */
export const MIN_READABLE_CONTRAST = 4.5;
