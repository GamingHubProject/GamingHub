import { AssetPicker } from '../../components/AssetPicker';
import type { AssetPreview } from '../../components/AssetPicker';
import type { Asset } from '../../api/types';
import type { PageLayoutWidgetConfigFormProps, PageLayoutWidgetContext } from './registry';

export type PictureFit = 'cover' | 'contain' | 'fill';

export interface PictureWidgetConfig {
  // Both kept, deliberately redundant: id for a future "is this asset in
  // use" check (not built yet), url so the picture renders directly without
  // an extra fetch per view. Neither is the source of truth for the asset
  // itself — that's the Asset row; this is just a snapshot reference.
  background_asset_id: number | null;
  background_url: string | null;
  // Maps directly to CSS background-size (cover/contain/fill's stretch
  // behavior — 'fill' isn't a real background-size keyword, so it's
  // translated to '100% 100%' at render time).
  fit: PictureFit;
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
  overlay_opacity: 0,
  allow_layering: true,
};

const BACKGROUND_SIZE: Record<PictureFit, string> = {
  cover: 'cover',
  contain: 'contain',
  fill: '100% 100%',
};

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
        backgroundImage: config.background_url ? `url(${config.background_url})` : undefined,
        backgroundSize: BACKGROUND_SIZE[config.fit],
        backgroundPosition: 'center',
        backgroundRepeat: 'no-repeat',
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
          <select value={config.fit} onChange={(event) => onChange({ ...config, fit: event.target.value as PictureFit })}>
            <option value="cover">Cover (crop to fill)</option>
            <option value="contain">Contain (fit whole image)</option>
            <option value="fill">Fill (stretch)</option>
          </select>
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
