import { StatusBadge } from '../shared/StatusBadge';
import type { Server } from '../../api/types';
import type { ServerLayoutWidgetConfigFormProps } from './registry';

export interface ServerStatusWidgetConfig {
  show_node: boolean;
}

export const serverStatusWidgetDefaultConfig: ServerStatusWidgetConfig = { show_node: false };

export function ServerStatusWidget({ server, config }: { server: Server; config: ServerStatusWidgetConfig }) {
  return (
    <div style={{ padding: 12, height: '100%' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <StatusBadge status={server.status} />
      </div>
      {config.show_node && server.node_name && (
        <p style={{ margin: '8px 0 0', fontSize: '0.85rem', opacity: 0.7 }}>Node: {server.node_name}</p>
      )}
    </div>
  );
}

export function ServerStatusWidgetConfigForm({ config, onChange }: ServerLayoutWidgetConfigFormProps<ServerStatusWidgetConfig>) {
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
