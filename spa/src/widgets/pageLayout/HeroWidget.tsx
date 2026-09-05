import { Link } from 'react-router-dom';
import { AssetPicker } from '../../components/AssetPicker';
import type { AssetPreview } from '../../components/AssetPicker';
import { Listbox } from '../../components/Listbox';
import { isExternal } from '../../layout/useNavigation';
import type { Asset } from '../../api/types';
import type { PageLayoutWidgetConfigFormProps } from './registry';

export type HeroAlign = 'left' | 'center';

export interface HeroWidgetConfig {
  // Same redundant id+url pair as PictureWidget: the id is the real
  // reference, the url a snapshot so the hero renders without a second
  // fetch per view.
  background_asset_id: number | null;
  background_url: string | null;
  title: string;
  subtitle: string;
  /**
   * The button. A plain URL rather than the target_type/target_id pair
   * navigation links use — deliberately, and it's worth saying why the
   * two differ.
   *
   * A navigation link is resolved server-side, so storing what it points
   * at costs nothing and protects it from a renamed slug. A page-layout
   * widget's config is an opaque blob rendered entirely client-side, with
   * no resolution step to hang that on; giving the hero a target id would
   * mean shipping the whole list of the site's pages to every visitor on
   * every page just to turn one id back into a path. If hero link-rot
   * becomes a real problem, the fix is a resolution step for widget
   * configs, not a different field here.
   */
  cta_label: string;
  cta_url: string;
  /** 0 = no scrim, 1 = solid black. Keeps the text readable over busy art. */
  overlay_opacity: number;
  align: HeroAlign;
}

export const heroWidgetDefaultConfig: HeroWidgetConfig = {
  background_asset_id: null,
  background_url: null,
  title: 'Your headline here',
  subtitle: '',
  cta_label: '',
  cta_url: '',
  // Artwork with text over it needs *some* scrim by default, or the first
  // thing an admin sees after picking an image is unreadable text.
  overlay_opacity: 0.45,
  align: 'left',
};

/**
 * A full-bleed banner: artwork, a headline, an optional line beneath it and
 * an optional button.
 *
 * The artwork is the hero's own config rather than the universal Style
 * section's background, because here it's the *content* — an admin adding
 * a hero is looking for "where do I put the picture", not for the chrome
 * settings every widget shares. The Style section still applies to the
 * container around it.
 */
export function HeroWidget({ config }: { config: HeroWidgetConfig }) {
  const centered = config.align === 'center';

  return (
    <div
      style={{
        position: 'relative',
        height: '100%',
        display: 'flex',
        flexDirection: 'column',
        justifyContent: 'center',
        alignItems: centered ? 'center' : 'flex-start',
        textAlign: centered ? 'center' : 'left',
        padding: 'var(--space-section, 24px)',
        gap: 'var(--space-normal, 12px)',
        backgroundImage: config.background_url ? `url(${config.background_url})` : undefined,
        backgroundSize: 'cover',
        backgroundPosition: 'center',
        overflow: 'hidden',
      }}
    >
      {config.overlay_opacity > 0 && config.background_url && (
        <div
          aria-hidden="true"
          style={{ position: 'absolute', inset: 0, background: `rgba(0, 0, 0, ${config.overlay_opacity})` }}
        />
      )}

      {/* Everything sits above the scrim. */}
      <div
        style={{
          position: 'relative',
          display: 'flex',
          flexDirection: 'column',
          alignItems: centered ? 'center' : 'flex-start',
          gap: 'var(--space-normal, 12px)',
          maxWidth: '46ch',
        }}
      >
        {config.title && (
          <h2 style={{ margin: 0, fontSize: 'clamp(1.4rem, 5cqh, 2.6rem)', lineHeight: 1.1, textWrap: 'balance' }}>
            {config.title}
          </h2>
        )}
        {config.subtitle && (
          <p style={{ margin: 0, fontSize: 'clamp(0.85rem, 2.4cqh, 1.05rem)', opacity: 0.9 }}>{config.subtitle}</p>
        )}
        {config.cta_label && config.cta_url && <HeroButton label={config.cta_label} url={config.cta_url} />}
      </div>
    </div>
  );
}

function HeroButton({ label, url }: { label: string; url: string }) {
  const style = {
    display: 'inline-block',
    padding: 'var(--space-normal, 12px) var(--space-loose, 20px)',
    borderRadius: 'var(--radius, 8px)',
    background: 'var(--accent, #333)',
    color: 'var(--accent-contrast, #fff)',
    textDecoration: 'none',
    fontWeight: 600,
    border: 'none',
  };

  // An off-site URL isn't a react-router route; handing it to <Link> would
  // resolve it against the app's own routes.
  return isExternal(url) ? (
    <a href={url} style={style} rel="noreferrer noopener" target="_blank">
      {label}
    </a>
  ) : (
    <Link to={url} style={style}>
      {label}
    </Link>
  );
}

export function HeroWidgetConfigForm({ config, onChange }: PageLayoutWidgetConfigFormProps<HeroWidgetConfig>) {
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
      <label>
        Artwork
        <div style={{ marginTop: 4 }}>
          <AssetPicker
            value={
              config.background_url
                ? ({ thumbnail_url: config.background_url, alt_text: null } as AssetPreview)
                : null
            }
            onChange={(asset: Asset | null) =>
              onChange({ ...config, background_asset_id: asset?.id ?? null, background_url: asset?.url ?? null })
            }
          />
        </div>
      </label>

      <label>
        Headline
        <input
          value={config.title}
          onChange={(event) => onChange({ ...config, title: event.target.value })}
          style={{ width: '100%', marginTop: 4 }}
        />
      </label>

      <label>
        Subtitle
        <input
          value={config.subtitle}
          onChange={(event) => onChange({ ...config, subtitle: event.target.value })}
          placeholder="Optional"
          style={{ width: '100%', marginTop: 4 }}
        />
      </label>

      <div style={{ display: 'flex', gap: 12, flexWrap: 'wrap' }}>
        <label style={{ flex: 1, minWidth: 140 }}>
          Button text
          <input
            value={config.cta_label}
            onChange={(event) => onChange({ ...config, cta_label: event.target.value })}
            placeholder="Optional"
            style={{ width: '100%', marginTop: 4 }}
          />
        </label>
        <label style={{ flex: 2, minWidth: 200 }}>
          Button link
          <input
            value={config.cta_url}
            onChange={(event) => onChange({ ...config, cta_url: event.target.value })}
            placeholder="/games or https://example.com"
            style={{ width: '100%', marginTop: 4 }}
          />
        </label>
      </div>
      {/* Both halves or neither — a button with no destination renders
          nothing, which looks like the setting failed to save. */}
      {Boolean(config.cta_label) !== Boolean(config.cta_url) && (
        <p role="status" style={{ margin: 0, fontSize: '0.8rem', color: 'var(--muted, #777)' }}>
          The button needs both a label and a link before it appears.
        </p>
      )}

      <div style={{ display: 'flex', gap: 16, alignItems: 'center', flexWrap: 'wrap' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
          <span>Align</span>
          <Listbox<HeroAlign>
            label="Align"
            value={config.align}
            options={[
              { value: 'left', label: 'Left' },
              { value: 'center', label: 'Centred' },
            ]}
            onChange={(align) => onChange({ ...config, align })}
          />
        </div>
        <label style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
          Darken artwork ({Math.round(config.overlay_opacity * 100)}%)
          <input
            type="range"
            min={0}
            max={1}
            step={0.05}
            value={config.overlay_opacity}
            onChange={(event) => onChange({ ...config, overlay_opacity: Number(event.target.value) })}
          />
        </label>
      </div>
    </div>
  );
}
