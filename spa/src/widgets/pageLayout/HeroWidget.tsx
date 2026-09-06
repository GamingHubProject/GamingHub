import type { CSSProperties } from 'react';
import { Link } from 'react-router-dom';
import { AssetPicker } from '../../components/AssetPicker';
import type { AssetPreview } from '../../components/AssetPicker';
import { Listbox } from '../../components/Listbox';
import { isExternal } from '../../layout/useNavigation';
import type { Asset } from '../../api/types';
import type { PageLayoutWidgetConfigFormProps } from './registry';
import { backgroundCss, BACKGROUND_POSITION_OPTIONS, DEFAULT_BACKGROUND_POSITION } from '../shared/background';
import type { BackgroundPosition } from '../shared/background';
import type { ResolvedWidgetStyle } from '../shared/widgetStyle';
import type { PictureFit } from './PictureWidget';

export type HeroAlign = 'left' | 'center';

export interface HeroWidgetConfig {
  // Same redundant id+url pair as PictureWidget: the id is the real
  // reference, the url a snapshot so the hero renders without a second
  // fetch per view.
  background_asset_id: number | null;
  background_url: string | null;
  /**
   * How the artwork fills the hero, and which part of it survives a crop.
   * The same two controls the Picture widget offers, sharing its
   * PictureFit type and the one builder in widgets/shared/background.ts —
   * "cover" meaning something different on a hero than on a picture would
   * be indefensible.
   *
   * Both are read with a fallback at render: PageLayoutWidgetContainer
   * hands a widget its stored config as-is, with no merge against
   * defaultConfig, so a hero placed before these existed has neither key.
   * The fallbacks are what it was already doing.
   */
  fit: PictureFit;
  position: BackgroundPosition;
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
  /**
   * Per-instance opt-out of being a layerTarget, exactly as
   * PictureWidgetConfig has one — PageLayoutEditor's isLayerTargetWidget
   * reads this key off any layerTarget type's config, so the hero needed
   * no editor change to get it. Defaults true: a hero an admin has never
   * opened the settings for should still accept a Status or Metrics
   * dropped on it, which is the whole point of issue 3.
   */
  allow_layering: boolean;
}

export const heroWidgetDefaultConfig: HeroWidgetConfig = {
  background_asset_id: null,
  background_url: null,
  fit: 'cover',
  position: DEFAULT_BACKGROUND_POSITION,
  title: 'Your headline here',
  subtitle: '',
  cta_label: '',
  cta_url: '',
  // Artwork with text over it needs *some* scrim by default, or the first
  // thing an admin sees after picking an image is unreadable text.
  overlay_opacity: 0.45,
  align: 'left',
  allow_layering: true,
};

/**
 * The subtitle's size as a fraction of the headline's, used only when an
 * admin has set an explicit Text size (the universal style control). The
 * default clamp()s below run 1.4–2.6rem against 0.85–1.05rem, i.e. a
 * subtitle roughly 0.4–0.6× its headline; 0.45 sits inside that, so
 * setting a size rescales the pair instead of flattening them to one.
 */
const SUBTITLE_SIZE_RATIO = 0.45;

/**
 * The artwork's CSS, through the same shared builder the Picture widget
 * uses. `{}` when no artwork is picked — a hero that's still a plain
 * panel keeps whatever background its Style section gives it.
 */
function heroArtwork(config: HeroWidgetConfig): CSSProperties {
  if (!config.background_url) return {};

  return backgroundCss({
    type: 'image',
    color: undefined,
    opacity: 1,
    pattern: undefined,
    patternColor: undefined,
    imageUrl: config.background_url,
    imageFit: config.fit ?? 'cover',
    imagePosition: config.position ?? DEFAULT_BACKGROUND_POSITION,
    gradient: undefined,
  });
}

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
export function HeroWidget({
  config,
  resolvedStyle,
}: {
  config: HeroWidgetConfig;
  resolvedStyle?: ResolvedWidgetStyle;
}) {
  const centered = config.align === 'center';
  // Every widget component is handed this by PageLayoutWidgetContainer;
  // the hero simply never declared it, which is why the universal Text
  // controls appeared to do nothing here. Colour applies unconditionally,
  // unlike ServerNameWidget's layered-only rule: that widget's default
  // state is sitting on an ordinary card, where it wants the ambient
  // heading colour, while a hero's text sits over admin-chosen artwork by
  // definition — picking a colour against that art is the normal case,
  // not the exception.
  const textSize = resolvedStyle?.textSize;

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
        ...heroArtwork(config),
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
          color: resolvedStyle?.textColor,
        }}
      >
        {config.title && (
          <h2
            style={{
              margin: 0,
              // An explicit size wins over the container-query clamp; with
              // none set the clamp is untouched, so an existing hero looks
              // exactly as it did. Applied to the text elements rather than
              // their wrapper on purpose — the button below is a control,
              // not body copy, and shouldn't grow to headline size.
              fontSize: textSize ?? 'clamp(1.4rem, 5cqh, 2.6rem)',
              lineHeight: 1.1,
              textWrap: 'balance',
            }}
          >
            {config.title}
          </h2>
        )}
        {config.subtitle && (
          <p
            style={{
              margin: 0,
              fontSize: textSize ? textSize * SUBTITLE_SIZE_RATIO : 'clamp(0.85rem, 2.4cqh, 1.05rem)',
              opacity: 0.9,
            }}
          >
            {config.subtitle}
          </p>
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

      <div style={{ display: 'flex', gap: 12, flexWrap: 'wrap' }}>
        <label style={{ flex: 1, minWidth: 160 }}>
          Fit
          <div style={{ marginTop: 4 }}>
            <Listbox<PictureFit>
              label="Fit"
              value={config.fit ?? 'cover'}
              options={[
                { value: 'cover', label: 'Cover (crop to fill)' },
                { value: 'contain', label: 'Contain (fit whole image)' },
                { value: 'fill', label: 'Fill (stretch)' },
              ]}
              onChange={(fit) => onChange({ ...config, fit })}
            />
          </div>
        </label>
        <label style={{ flex: 1, minWidth: 160 }}>
          Position
          <div style={{ marginTop: 4 }}>
            <Listbox<BackgroundPosition>
              label="Position"
              value={config.position ?? DEFAULT_BACKGROUND_POSITION}
              options={BACKGROUND_POSITION_OPTIONS}
              onChange={(position) => onChange({ ...config, position })}
            />
          </div>
        </label>
      </div>

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

      <label style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
        <input
          type="checkbox"
          checked={config.allow_layering !== false}
          onChange={(event) => onChange({ ...config, allow_layering: event.target.checked })}
        />
        Allow layering (widgets like Status, Metrics or Player Count can be dragged onto this hero)
      </label>
    </div>
  );
}
