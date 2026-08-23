import { StatusBadge } from '../shared/StatusBadge';
import type { Server } from '../../api/types';

export function ServerStatusWidget({ server }: { server: Server }) {
  return (
    <div style={{ padding: 12, height: '100%' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <StatusBadge status={server.status} />
      </div>
      {server.node_name && <p style={{ margin: '8px 0 0', fontSize: '0.85rem', opacity: 0.7 }}>Node: {server.node_name}</p>}
    </div>
  );
}
