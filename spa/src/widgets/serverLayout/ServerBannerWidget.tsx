import { AssetPicker } from '../../components/AssetPicker';
import type { AssetPreview } from '../../components/AssetPicker';
import type { Asset, Server } from '../../api/types';
import type { ServerLayoutWidgetConfigFormProps } from './registry';

export type BannerFit = 'cover' | 'contain' | 'fill';

export interface ServerBannerWidgetConfig {
  // Both kept, deliberately redundant: id for a future "is this asset in
  // use" check (not built yet), url so the banner renders directly without
  // an extra fetch per view. Neither is the source of truth for the asset
  // itself — that's the Asset row; this is just a snapshot reference.
  background_asset_id: number | null;
  background_url: string | null;
  // Maps directly to CSS background-size (cover/contain/fill's stretch
  // behavior — 'fill' isn't a real background-size keyword, so it's
  // translated to '100% 100%' at render time).
  fit: BannerFit;
  // 0 = no overlay, 1 = fully opaque black. A flat number rather than a
  // color picker — this exists purely to keep foreground widgets (Name,
  // Status) readable when layered on top, not as a design/branding knob.
  overlay_opacity: number;
}

export const serverBannerWidgetDefaultConfig: ServerBannerWidgetConfig = {
  background_asset_id: null,
  background_url: null,
  fit: 'cover',
  overlay_opacity: 0,
};

const BACKGROUND_SIZE: Record<BannerFit, string> = {
  cover: 'cover',
  contain: 'contain',
  fill: '100% 100%',
};

// Purely a background layer now — no name/status of its own (those moved
// out to ServerNameWidget/ServerStatusWidget, both `layerable: true` in
// the registry so an admin can drag them visually on top of this widget;
// see registry.ts's layerable/layerTarget docblock and ServerDetail's
// isValidOverlapLayout for how that's enforced). Isolating it this way
// means future work on the background (new fit modes, video backgrounds,
// ...) only ever touches this file.
export function ServerBannerWidget({ config }: { server: Server; config: ServerBannerWidgetConfig }) {
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

export function ServerBannerWidgetConfigForm({ config, onChange }: ServerLayoutWidgetConfigFormProps<ServerBannerWidgetConfig>) {
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
          <select value={config.fit} onChange={(event) => onChange({ ...config, fit: event.target.value as BannerFit })}>
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
    </div>
  );
}
