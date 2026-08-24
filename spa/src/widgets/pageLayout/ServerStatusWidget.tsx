import { StatusBadge } from '../shared/StatusBadge';
import type { PageLayoutWidgetConfigFormProps, PageLayoutWidgetContext } from './registry';

export interface ServerStatusWidgetConfig {
  show_node: boolean;
}

export const serverStatusWidgetDefaultConfig: ServerStatusWidgetConfig = { show_node: false };

// No color/size config here, unlike ServerNameWidget — the badge already
// carries its own solid per-status background (see StatusBadge), so it
// stays legible against any banner image without needing one. Layered
// mode only drops the padding (so it isn't floating in from the widget's
// corner) and adds the node line's text-shadow, since that line — unlike
// the badge — is plain text with no background of its own. validFor:
// ['server'] guarantees context.server is set — see registry.ts.
export function ServerStatusWidget({
  context,
  config,
  layered,
}: {
  context: PageLayoutWidgetContext;
  config: ServerStatusWidgetConfig;
  layered?: boolean;
}) {
  const server = context.server!;

  return (
    <div style={{ padding: layered ? 0 : 12, height: '100%' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <StatusBadge status={server.status} />
      </div>
      {config.show_node && server.node_name && (
        <p
          style={{
            margin: '8px 0 0',
            fontSize: '0.85rem',
            opacity: 0.7,
            textShadow: layered ? '0 1px 3px rgba(0, 0, 0, 0.8)' : undefined,
          }}
        >
          Node: {server.node_name}
        </p>
      )}
    </div>
  );
}

export function ServerStatusWidgetConfigForm({ config, onChange }: PageLayoutWidgetConfigFormProps<ServerStatusWidgetConfig>) {
  return (
    <label style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
      <input
        type="checkbox"
        checked={config.show_node}
        onChange={(event) => onChange({ ...config, show_node: event.target.checked })}
      />
      Show node
    </label>
  );
}
