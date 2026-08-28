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
  text_size: number | null;
  text_color: string | null;
  background_color: string | null;
  background_opacity: number | null;
}

export const EMPTY_WIDGET_STYLE: WidgetStyleOverride = {
  border_enabled: null,
  border_thickness: null,
  text_size: null,
  text_color: null,
  background_color: null,
  background_opacity: null,
};

export interface ResolvedWidgetStyle {
  borderEnabled: boolean;
  borderThickness: number;
  textSize: number | undefined;
  textColor: string | undefined;
  backgroundColor: string | undefined;
  backgroundOpacity: number;
}

/**
 * Reproduces PageLayoutWidgetContainer's pre-existing hardcoded look
 * exactly (a 1px border, no forced text size/color, no background) — the
 * state every widget is already in today, so a fresh install or a widget
 * nobody has touched the style of renders identically to before this
 * system existed.
 */
const FALLBACK: ResolvedWidgetStyle = {
  borderEnabled: true,
  borderThickness: 1,
  textSize: undefined,
  textColor: undefined,
  backgroundColor: undefined,
  backgroundOpacity: 1,
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
    textSize: instance.text_size ?? global.text_size ?? FALLBACK.textSize,
    textColor: instance.text_color ?? global.text_color ?? FALLBACK.textColor,
    backgroundColor: instance.background_color ?? global.background_color ?? FALLBACK.backgroundColor,
    backgroundOpacity: instance.background_opacity ?? global.background_opacity ?? FALLBACK.backgroundOpacity,
  };
}

/** #rrggbb (what a <input type="color"> / Filament ColorPicker produce) + a 0–1 opacity -> rgba(). */
export function hexWithOpacity(hex: string, opacity: number): string {
  const match = /^#?([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i.exec(hex);
  if (!match) return hex;
  const [, r, g, b] = match;
  return `rgba(${parseInt(r, 16)}, ${parseInt(g, 16)}, ${parseInt(b, 16)}, ${opacity})`;
}
