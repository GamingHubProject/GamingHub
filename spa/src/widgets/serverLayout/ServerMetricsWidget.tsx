import { ProgressBar } from '../shared/ProgressBar';
import type { Server } from '../../api/types';

export function ServerMetricsWidget({ server }: { server: Server }) {
  if (server.cpu_percent === null && server.memory_percent === null) {
    return (
      <div style={{ padding: 12 }}>
        <p style={{ margin: 0, fontSize: '0.85rem', opacity: 0.7 }}>No resource data yet.</p>
      </div>
    );
  }

  return (
    <div style={{ padding: 12 }}>
      {server.cpu_percent !== null && <ProgressBar label="CPU" percent={server.cpu_percent} />}
      {server.memory_percent !== null && <ProgressBar label="RAM" percent={server.memory_percent} />}
    </div>
  );
}
