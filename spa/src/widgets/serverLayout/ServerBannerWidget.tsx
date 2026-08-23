import { StatusBadge } from '../shared/StatusBadge';
import type { Server } from '../../api/types';
import type { ServerLayoutWidgetConfigFormProps } from './registry';

export interface ServerBannerWidgetConfig {
  show_status: boolean;
}

export const serverBannerWidgetDefaultConfig: ServerBannerWidgetConfig = { show_status: false };

// Plain for now — artwork/background comes once the Asset Library exists.
// Isolating the banner as its own widget (rather than folding the title
// into the status card) means that future work only ever touches this
// file, not server-status/server-metrics/etc. show_status lets an admin
// fold the status badge in here instead of adding a separate status card,
// if they'd rather — the two aren't mutually exclusive, just optional.
export function ServerBannerWidget({ server, config }: { server: Server; config: ServerBannerWidgetConfig }) {
  return (
    <div style={{ padding: '16px 20px', height: '100%', display: 'flex', flexDirection: 'column', justifyContent: 'center', gap: 8 }}>
      <h1 style={{ margin: 0, fontSize: '1.5rem' }}>{server.name}</h1>
      {config.show_status && <StatusBadge status={server.status} />}
    </div>
  );
}

export function ServerBannerWidgetConfigForm({ config, onChange }: ServerLayoutWidgetConfigFormProps<ServerBannerWidgetConfig>) {
  return (
    <label style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
      <input
        type="checkbox"
        checked={config.show_status}
        onChange={(event) => onChange({ ...config, show_status: event.target.checked })}
      />
      Show status badge
    </label>
  );
}
