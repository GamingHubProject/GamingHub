import type { CSSProperties } from 'react';
import { AssetPicker } from '../../components/AssetPicker';
import type { AssetPreview } from '../../components/AssetPicker';
import type { Asset } from '../../api/types';
import type { PageLayoutWidgetConfigFormProps, PageLayoutWidgetContext } from './registry';
import { Listbox } from '../../components/Listbox';
import { backgroundCss, BACKGROUND_POSITION_OPTIONS, DEFAULT_BACKGROUND_POSITION } from '../shared/background';
import type { BackgroundImageFit, BackgroundPosition } from '../shared/background';

/**
 * The subset of the shared BackgroundImageFit this widget offers. Narrowed
 * rather than aliased: 'tile' is a texture mode that belongs to the
 * universal Style background, not to a picture that's meant to be the
 * content of its box.
 */
export type PictureFit = Extract<BackgroundImageFit, 'cover' | 'contain' | 'fill'>;

export interface PictureWidgetConfig {
  // Both kept, deliberately redundant: id for a future "is this asset in
  // use" check (not built yet), url so the picture renders directly without
  // an extra fetch per view. Neither is the source of truth for the asset
  // itself — that's the Asset row; this is just a snapshot reference.
  background_asset_id: number | null;
  background_url: string | null;
  // Handed to the shared background builder, which owns the translation
  // to CSS (see widgets/shared/background.ts's BACKGROUND_IMAGE_SIZE —
  // 'fill' isn't a real background-size keyword). This used to be a
  // private map in this file; it moved to the shared builder when the
  // Hero grew the same control, so the two can't disagree on what a fit
  // mode means.
  fit: PictureFit;
  /** Which part of the artwork survives a crop. Read with a `?? 'center'`
   *  fallback at render — a picture saved before this control existed has
   *  no such key, and centred is exactly what it was doing. */
  position: BackgroundPosition;
  // 0 = no overlay, 1 = fully opaque black. A flat number rather than a
  // color picker — this exists purely to keep foreground widgets (Name,
  // Status) readable when layered on top, not as a design/branding knob.
  overlay_opacity: number;
  // Per-instance opt-out of being a layerTarget (registry.ts's type-level
  // flag says this widget TYPE is *capable* of having other widgets
  // dragged onto it; this says whether *this particular* placement
  // actually allows it). Defaults true so an upgraded install's existing
  // Server Detail layouts (Name/Status already layered on their banner)
  // keep working unchanged — see PageLayoutEditor's isValidOverlapLayout/
  // layeredWidgetIds, which both read this off the widget's own config
  // rather than the registry now.
  allow_layering: boolean;
}

export const pictureWidgetDefaultConfig: PictureWidgetConfig = {
  background_asset_id: null,
  background_url: null,
  fit: 'cover',
  position: DEFAULT_BACKGROUND_POSITION,
  overlay_opacity: 0,
  allow_layering: true,
};

/**
 * The artwork's CSS, via the one shared builder (widgets/shared/
 * background.ts) rather than this file's own — see the `fit` field.
 * Returns `{}` with no artwork picked, leaving the empty widget exactly
 * as bare as it was.
 */
function pictureBackground(config: PictureWidgetConfig): CSSProperties {
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

// A generic background-image widget, usable on any page — no longer
// Server-specific (see ServerNameWidget/ServerStatusWidget for the
// widgets that still assume a Server subject). Isolating it this way
// means future work on the background (new fit modes, video backgrounds,
// ...) only ever touches this file.
export function PictureWidget({ config }: { context: PageLayoutWidgetContext; config: PictureWidgetConfig }) {
  return (
    <div
      style={{
        position: 'relative',
        height: '100%',
        ...pictureBackground(config),
      }}
    >
      {config.overlay_opacity > 0 && (
        <div
          style={{
            position: 'absolute',
            inset: 0,
            background: `rgba(0, 0, 0, ${config.overlay_opacity})`,
          }}
        />
      )}
    </div>
  );
}

export function PictureWidgetConfigForm({ config, onChange }: PageLayoutWidgetConfigFormProps<PictureWidgetConfig>) {
  function handleAssetChange(asset: Asset | null) {
    onChange({ ...config, background_asset_id: asset?.id ?? null, background_url: asset?.url ?? null });
  }

  const preview: AssetPreview | null = config.background_url
    ? { thumbnail_url: config.background_url, alt_text: null }
    : null;

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
      <label>
        Background image
        <div style={{ marginTop: 4 }}>
          <AssetPicker value={preview} onChange={handleAssetChange} />
        </div>
      </label>
      <label>
        Fit
        <div style={{ marginTop: 4 }}>
          <Listbox<PictureFit>
            label="Fit"
            value={config.fit}
            options={[
              { value: 'cover', label: 'Cover (crop to fill)' },
              { value: 'contain', label: 'Contain (fit whole image)' },
              { value: 'fill', label: 'Fill (stretch)' },
            ]}
            onChange={(next) => onChange({ ...config, fit: next })}
          />
        </div>
      </label>
      <label>
        Position
        <div style={{ marginTop: 4 }}>
          <Listbox<BackgroundPosition>
            label="Position"
            value={config.position ?? DEFAULT_BACKGROUND_POSITION}
            options={BACKGROUND_POSITION_OPTIONS}
            onChange={(next) => onChange({ ...config, position: next })}
          />
        </div>
      </label>
      <label>
        Dark overlay ({Math.round(config.overlay_opacity * 100)}%)
        <div style={{ marginTop: 4 }}>
          <input
            type="range"
            min={0}
            max={1}
            step={0.05}
            value={config.overlay_opacity}
            onChange={(event) => onChange({ ...config, overlay_opacity: Number(event.target.value) })}
          />
        </div>
      </label>
      <label style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
        <input
          type="checkbox"
          checked={config.allow_layering}
          onChange={(event) => onChange({ ...config, allow_layering: event.target.checked })}
        />
        Allow layering (other widgets like Name/Status can be dragged onto this picture)
      </label>
    </div>
  );
}
