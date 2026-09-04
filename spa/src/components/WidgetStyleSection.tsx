import { getPageLayoutWidgetDefinition } from '../widgets/pageLayout/registry';
import { useWidgetStyleDefaults } from '../providers/ThemeProvider';
import { AssetPicker } from './AssetPicker';
import type { AssetPreview } from './AssetPicker';
import { Listbox } from './Listbox';
import { BACKGROUND_PATTERNS } from '../widgets/shared/backgroundPattern';
import { contrastRatio, resolveWidgetStyle, MIN_READABLE_CONTRAST, EMPTY_WIDGET_STYLE } from '../widgets/shared/widgetStyle';
import type { BackgroundImageFit, BackgroundType, WidgetStyleOverride } from '../widgets/shared/widgetStyle';
import type { Asset } from '../api/types';

/**
 * Switching background type seeds whatever that mode needs in order to
 * actually render, so the controls never show a value that isn't stored —
 * picking "Pattern" with a null background_pattern would display the first
 * pattern in the dropdown while saving nothing, and the widget would come
 * back blank. Existing values are kept, so toggling color -> pattern ->
 * color doesn't lose the pattern the admin already picked.
 */
function backgroundTypePatch(type: BackgroundType, style: Partial<WidgetStyleOverride>): Partial<WidgetStyleOverride> {
  if (type === 'pattern') {
    return {
      background_type: type,
      background_pattern: style.background_pattern ?? BACKGROUND_PATTERNS[0].id,
      background_pattern_color: style.background_pattern_color ?? '#000000',
    };
  }

  if (type === 'image') {
    return { background_type: type, background_image_fit: style.background_image_fit ?? 'cover' };
  }

  return { background_type: type };
}

/**
 * Rendered once, universally, by PageLayoutWidgetConfigModal — every
 * widget type gets Border/Text/Background for free, with no per-type code
 * (unlike font_size/text_color, which used to be Server Name's own bespoke
 * fields; see the migration that folded those into `style`). Three
 * independent sync/override groups rather than per-property toggles: a
 * thickness field means nothing while border is synced, so granularity
 * below the group level just adds noise.
 */
export function WidgetStyleSection({
  widgetType,
  config,
  onChange,
}: {
  widgetType: string;
  config: Record<string, unknown>;
  onChange: (next: Record<string, unknown>) => void;
}) {
  const style: Partial<WidgetStyleOverride> = (config.style as Partial<WidgetStyleOverride>) ?? {};
  const globalDefaults = useWidgetStyleDefaults();
  // Card widgets already have a proportional clamp()-based size from
  // widgets/shared/cardScale.ts — a fixed px override would just fight
  // that (an explicit value always wins over the clamp() it's laid over),
  // so these get a percentage *multiplier* on the existing clamp() bounds
  // instead (text_scale), not a disabled control. Color has no such
  // conflict — it's not part of the scaling mechanism at all — so it
  // stays a normal, always-available field regardless. See registry.ts's
  // selfScaling docblock.
  const selfScaling = getPageLayoutWidgetDefinition(widgetType)?.selfScaling ?? false;

  function updateStyle(patch: Partial<WidgetStyleOverride>) {
    onChange({ ...config, style: { ...EMPTY_WIDGET_STYLE, ...style, ...patch } });
  }

  const borderOverridden = style.border_enabled !== null && style.border_enabled !== undefined;
  const textSizeOverridden = selfScaling
    ? style.text_scale !== null && style.text_scale !== undefined
    : style.text_size !== null && style.text_size !== undefined;
  const textColorOverridden = style.text_color !== null && style.text_color !== undefined;
  const textOverridden = textSizeOverridden || textColorOverridden;
  const backgroundOverridden = style.background_color !== null && style.background_color !== undefined;
  // Which sub-fields the Background group shows. Not read from `resolved`
  // — while the group is overridden this is a property of what the admin
  // is editing right here, and falling through to the global default's
  // type would silently show the wrong controls for the value being saved.
  const backgroundType: BackgroundType = style.background_type ?? 'color';

  // Advisory only — checked against what will *actually* render (this
  // instance's override, falling through to the global default exactly
  // like resolveWidgetStyle does), not just whatever's explicitly set on
  // this one instance. Skipped entirely when no background color resolves
  // at all (nothing to check contrast against — the page's own background
  // is unknown here, and warning about that would just be noise).
  const resolved = resolveWidgetStyle(config, globalDefaults);
  const ratio = resolved.textColor && resolved.backgroundColor ? contrastRatio(resolved.textColor, resolved.backgroundColor) : null;
  const lowContrast = ratio !== null && ratio < MIN_READABLE_CONTRAST;

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 16, marginTop: 16, paddingTop: 16, borderTop: '1px solid var(--border, #ddd)' }}>
      <h3 style={{ margin: 0, fontSize: '0.9rem' }}>Style</h3>

      {/* --- Border --- */}
      <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
        <label style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
          <input
            type="checkbox"
            checked={borderOverridden}
            onChange={(event) =>
              updateStyle(
                event.target.checked
                  ? {
                      border_enabled: true,
                      border_thickness: style.border_thickness ?? 1,
                      border_color: style.border_color ?? '#dddddd',
                      border_radius: style.border_radius ?? 8,
                    }
                  : { border_enabled: null, border_thickness: null, border_color: null, border_radius: null }
              )
            }
          />
          Override border (otherwise syncs to the global default)
        </label>
        {borderOverridden && (
          <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: 16, paddingLeft: 24 }}>
            <label style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
              <input
                type="checkbox"
                checked={style.border_enabled ?? true}
                onChange={(event) => updateStyle({ border_enabled: event.target.checked })}
              />
              Show border
            </label>
            <label style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
              Thickness (px)
              <input
                type="number"
                min={1}
                value={style.border_thickness ?? 1}
                onChange={(event) => updateStyle({ border_thickness: Number(event.target.value) })}
                style={{ width: 60 }}
              />
            </label>
            <label style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
              Color
              <input
                type="color"
                value={style.border_color ?? '#dddddd'}
                onChange={(event) => updateStyle({ border_color: event.target.value })}
              />
            </label>
            <label style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
              Roundness (px)
              <input
                type="number"
                min={0}
                value={style.border_radius ?? 8}
                onChange={(event) => updateStyle({ border_radius: Number(event.target.value) })}
                style={{ width: 60 }}
              />
            </label>
          </div>
        )}
      </div>

      {/* --- Text --- */}
      <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
        <label style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
          <input
            type="checkbox"
            checked={textOverridden}
            onChange={(event) =>
              updateStyle(
                event.target.checked
                  ? selfScaling
                    ? { text_scale: style.text_scale ?? 1, text_color: style.text_color ?? '#000000' }
                    : { text_size: style.text_size ?? 16, text_color: style.text_color ?? '#000000' }
                  : { text_size: null, text_scale: null, text_color: null }
              )
            }
          />
          Override text style (otherwise syncs to the global default)
        </label>
        {selfScaling && (
          <p style={{ margin: '0 0 0 24px', fontSize: '0.8rem', opacity: 0.7 }}>
            This widget scales its own text proportionally — size is a relative adjustment, not a fixed value, so the
            title stays bigger than the body text at every size.
          </p>
        )}
        {textOverridden && (
          <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: 16, paddingLeft: 24 }}>
            {selfScaling ? (
              <label style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                Size adjustment ({Math.round((style.text_scale ?? 1) * 100)}%)
                <input
                  type="range"
                  min={0.5}
                  max={2}
                  step={0.05}
                  value={style.text_scale ?? 1}
                  onChange={(event) => updateStyle({ text_scale: Number(event.target.value) })}
                />
              </label>
            ) : (
              <label style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                Size (px)
                <input
                  type="number"
                  min={1}
                  value={style.text_size ?? 16}
                  onChange={(event) => updateStyle({ text_size: Number(event.target.value) })}
                  style={{ width: 60 }}
                />
              </label>
            )}
            <label style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
              Color
              <input
                type="color"
                value={style.text_color ?? '#000000'}
                onChange={(event) => updateStyle({ text_color: event.target.value })}
              />
            </label>
          </div>
        )}
        {lowContrast && (
          <p role="alert" style={{ margin: '0 0 0 24px', fontSize: '0.8rem', color: '#b45309' }}>
            ⚠ This text may be hard to read against the background (contrast ratio {ratio!.toFixed(1)}:1 — WCAG
            recommends at least {MIN_READABLE_CONTRAST}:1). You can still save this if it's intentional.
          </p>
        )}
      </div>

      {/* --- Background --- */}
      <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
        <label style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
          <input
            type="checkbox"
            checked={backgroundOverridden}
            onChange={(event) =>
              updateStyle(
                event.target.checked
                  ? {
                      background_type: style.background_type ?? 'color',
                      background_color: style.background_color ?? '#ffffff',
                      background_opacity: style.background_opacity ?? 1,
                    }
                  : {
                      background_type: null,
                      background_color: null,
                      background_opacity: null,
                      background_pattern: null,
                      background_pattern_color: null,
                      background_image_asset_id: null,
                      background_image_url: null,
                      background_image_fit: null,
                    }
              )
            }
          />
          Override background (otherwise syncs to the global default)
        </label>
        {backgroundOverridden && (
          <div style={{ display: 'flex', flexDirection: 'column', gap: 12, paddingLeft: 24 }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
              <span>Type</span>
              <Listbox<BackgroundType>
                label="Type"
                value={backgroundType}
                options={[
                  { value: 'color', label: 'Solid color' },
                  { value: 'pattern', label: 'Pattern' },
                  { value: 'image', label: 'Image' },
                ]}
                onChange={(next) => updateStyle(backgroundTypePatch(next, style))}
              />
            </div>

            <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: 16 }}>
              {/* The base fill in every mode, so it stays visible for all
                  three rather than only for 'color' — a pattern's ink and
                  a non-covering image both sit on top of it. */}
              <label style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                {backgroundType === 'color' ? 'Color' : 'Base color'}
                <input
                  type="color"
                  value={style.background_color ?? '#ffffff'}
                  onChange={(event) => updateStyle({ background_color: event.target.value })}
                />
              </label>

              {backgroundType === 'pattern' && (
                <>
                  <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                    <span>Pattern</span>
                    <Listbox
                      label="Pattern"
                      value={style.background_pattern ?? BACKGROUND_PATTERNS[0].id}
                      options={BACKGROUND_PATTERNS.map((p) => ({ value: p.id, label: p.label }))}
                      onChange={(next) => updateStyle({ background_pattern: next })}
                    />
                  </div>
                  <label style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                    Pattern color
                    <input
                      type="color"
                      value={style.background_pattern_color ?? '#000000'}
                      onChange={(event) => updateStyle({ background_pattern_color: event.target.value })}
                    />
                  </label>
                </>
              )}

              {backgroundType === 'image' && (
                <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                  <span>Fit</span>
                  <Listbox<BackgroundImageFit>
                    label="Fit"
                    value={style.background_image_fit ?? 'cover'}
                    options={[
                      { value: 'cover', label: 'Cover (crop to fill)' },
                      { value: 'contain', label: 'Contain (fit whole image)' },
                      { value: 'tile', label: 'Tile (repeat)' },
                    ]}
                    onChange={(next) => updateStyle({ background_image_fit: next })}
                  />
                </div>
              )}

              {/* Opacity tints flat fills (the base color, and a pattern's
                  ink) — an image carries its own, so showing a slider that
                  wouldn't affect it would just be a lie. */}
              {backgroundType !== 'image' && (
                <label style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                  Opacity ({Math.round((style.background_opacity ?? 1) * 100)}%)
                  <input
                    type="range"
                    min={0}
                    max={1}
                    step={0.05}
                    value={style.background_opacity ?? 1}
                    onChange={(event) => updateStyle({ background_opacity: Number(event.target.value) })}
                  />
                </label>
              )}
            </div>

            {backgroundType === 'image' && (
              <label>
                Image
                <div style={{ marginTop: 4 }}>
                  <AssetPicker
                    value={
                      style.background_image_url
                        ? ({ thumbnail_url: style.background_image_url, alt_text: null } as AssetPreview)
                        : null
                    }
                    onChange={(asset: Asset | null) =>
                      updateStyle({
                        background_image_asset_id: asset?.id ?? null,
                        background_image_url: asset?.url ?? null,
                      })
                    }
                  />
                </div>
              </label>
            )}
          </div>
        )}
      </div>
    </div>
  );
}
