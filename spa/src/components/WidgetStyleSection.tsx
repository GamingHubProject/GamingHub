import { getPageLayoutWidgetDefinition } from '../widgets/pageLayout/registry';
import { EMPTY_WIDGET_STYLE } from '../widgets/shared/widgetStyle';
import type { WidgetStyleOverride } from '../widgets/shared/widgetStyle';

/**
 * Rendered once, universally, by PageLayoutWidgetConfigModal — every
 * widget type gets Border/Text/Background for free, with no per-type code
 * (unlike font_size/text_color, which used to be Server Name's own bespoke
 * fields; see the migration that folded those into `style`). Three
 * independent sync/override groups rather than six per-property toggles:
 * a thickness field means nothing while border is synced, so granularity
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
  // Card widgets already scale their own text proportionally via
  // container queries — a fixed size/color override would be silently
  // ineffective (an explicit inline style always wins over an inherited
  // one), so Text is disabled outright here rather than accepting a
  // setting that won't visibly apply. See registry.ts's selfScaling
  // docblock.
  const textDisabled = getPageLayoutWidgetDefinition(widgetType)?.selfScaling ?? false;

  function updateStyle(patch: Partial<WidgetStyleOverride>) {
    onChange({ ...config, style: { ...EMPTY_WIDGET_STYLE, ...style, ...patch } });
  }

  const borderOverridden = style.border_enabled !== null && style.border_enabled !== undefined;
  const textOverridden = !textDisabled && (style.text_size !== null && style.text_size !== undefined || style.text_color !== null && style.text_color !== undefined);
  const backgroundOverridden = style.background_color !== null && style.background_color !== undefined;

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
                  ? { border_enabled: true, border_thickness: style.border_thickness ?? 1 }
                  : { border_enabled: null, border_thickness: null }
              )
            }
          />
          Override border (otherwise syncs to the global default)
        </label>
        {borderOverridden && (
          <div style={{ display: 'flex', alignItems: 'center', gap: 16, paddingLeft: 24 }}>
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
          </div>
        )}
      </div>

      {/* --- Text --- */}
      <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
        <label style={{ display: 'flex', alignItems: 'center', gap: 8, opacity: textDisabled ? 0.5 : 1 }}>
          <input
            type="checkbox"
            checked={textOverridden}
            disabled={textDisabled}
            onChange={(event) =>
              updateStyle(
                event.target.checked
                  ? { text_size: style.text_size ?? 16, text_color: style.text_color ?? '#000000' }
                  : { text_size: null, text_color: null }
              )
            }
          />
          Override text style (otherwise syncs to the global default)
        </label>
        {textDisabled && (
          <p style={{ margin: '0 0 0 24px', fontSize: '0.8rem', opacity: 0.7 }}>
            This widget scales its own text automatically and doesn't support a fixed size/color override.
          </p>
        )}
        {textOverridden && (
          <div style={{ display: 'flex', alignItems: 'center', gap: 16, paddingLeft: 24 }}>
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
                  ? { background_color: style.background_color ?? '#ffffff', background_opacity: style.background_opacity ?? 1 }
                  : { background_color: null, background_opacity: null }
              )
            }
          />
          Override background (otherwise syncs to the global default)
        </label>
        {backgroundOverridden && (
          <div style={{ display: 'flex', alignItems: 'center', gap: 16, paddingLeft: 24 }}>
            <label style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
              Color
              <input
                type="color"
                value={style.background_color ?? '#ffffff'}
                onChange={(event) => updateStyle({ background_color: event.target.value })}
              />
            </label>
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
          </div>
        )}
      </div>
    </div>
  );
}
