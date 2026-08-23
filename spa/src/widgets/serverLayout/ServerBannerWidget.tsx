import { StatusBadge } from '../shared/StatusBadge';
import { AssetPicker } from '../../components/AssetPicker';
import type { AssetPreview } from '../../components/AssetPicker';
import type { Asset, Server } from '../../api/types';
import type { ServerLayoutWidgetConfigFormProps } from './registry';

export interface ServerBannerWidgetConfig {
  show_status: boolean;
  // Both kept, deliberately redundant: id for a future "is this asset in
  // use" check (not built yet), url so the banner renders directly without
  // an extra fetch per view. Neither is the source of truth for the asset
  // itself — that's the Asset row; this is just a snapshot reference.
  background_asset_id: number | null;
  background_url: string | null;
}

export const serverBannerWidgetDefaultConfig: ServerBannerWidgetConfig = {
  show_status: false,
  background_asset_id: null,
  background_url: null,
};

// Isolating the banner as its own widget (rather than folding the title
// into the status card) means that future work only ever touches this
// file, not server-status/server-metrics/etc. show_status lets an admin
// fold the status badge in here instead of adding a separate status card,
// if they'd rather — the two aren't mutually exclusive, just optional.
export function ServerBannerWidget({ server, config }: { server: Server; config: ServerBannerWidgetConfig }) {
  return (
    <div
      style={{
        padding: '16px 20px',
        height: '100%',
        display: 'flex',
        flexDirection: 'column',
        justifyContent: 'center',
        gap: 8,
        backgroundImage: config.background_url ? `url(${config.background_url})` : undefined,
        backgroundSize: 'cover',
        backgroundPosition: 'center',
      }}
    >
      <h1 style={{ margin: 0, fontSize: '1.5rem' }}>{server.name}</h1>
      {config.show_status && <StatusBadge status={server.status} />}
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
      <label style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
        <input
          type="checkbox"
          checked={config.show_status}
          onChange={(event) => onChange({ ...config, show_status: event.target.checked })}
        />
        Show status badge
      </label>
    </div>
  );
}
