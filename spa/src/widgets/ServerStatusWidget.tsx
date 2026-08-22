import { useQuery } from '@tanstack/react-query';
import { useApi } from '../providers/ApiClientProvider';
import type { Game, Server } from '../api/types';
import type { WidgetConfigFormProps } from './registry';

export interface ServerStatusWidgetConfig {
  server_id: number | null;
}

// Mirrors the label/color groupings in App\Capabilities\ServerStatusBadge
// (PHP, used by Filament) — kept as three simple buckets here since the
// widget only needs a color signal, not per-state labels.
const STATUS_COLOR: Record<string, string> = {
  running: '#16a34a',
  online: '#16a34a',
  offline: '#dc2626',
  exited: '#dc2626',
  dead: '#dc2626',
  missing: '#dc2626',
  suspended: '#dc2626',
  install_failed: '#dc2626',
  reinstall_failed: '#dc2626',
  starting: '#ca8a04',
  stopping: '#ca8a04',
  restarting: '#ca8a04',
  paused: '#ca8a04',
  removing: '#ca8a04',
  installing: '#ca8a04',
  restoring_backup: '#ca8a04',
  transferring: '#ca8a04',
  node_maintenance: '#ca8a04',
  maintenance: '#ca8a04',
  created: '#6b7280',
};

function statusColor(status: string): string {
  return STATUS_COLOR[status] ?? '#6b7280';
}

function statusLabel(status: string): string {
  return status
    .split('_')
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ');
}

function StatusBadge({ status }: { status: string }) {
  const color = statusColor(status);
  return (
    <span
      style={{
        display: 'inline-block',
        padding: '2px 8px',
        borderRadius: 999,
        fontSize: '0.75rem',
        fontWeight: 600,
        color: '#fff',
        background: color,
      }}
    >
      {statusLabel(status)}
    </span>
  );
}

function ProgressBar({ label, percent }: { label: string; percent: number }) {
  const clamped = Math.max(0, Math.min(100, percent));
  const color = clamped >= 90 ? '#dc2626' : clamped >= 70 ? '#ca8a04' : '#16a34a';

  return (
    <div style={{ marginTop: 6 }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.75rem', opacity: 0.7 }}>
        <span>{label}</span>
        <span>{Math.round(clamped)}%</span>
      </div>
      <div style={{ height: 6, borderRadius: 3, background: 'var(--border, #ddd)', overflow: 'hidden' }}>
        <div style={{ height: '100%', width: `${clamped}%`, background: color, borderRadius: 3 }} />
      </div>
    </div>
  );
}

export function ServerStatusWidget({ config }: { widgetId: number; config: ServerStatusWidgetConfig }) {
  const api = useApi();

  const { data: server, isLoading } = useQuery({
    queryKey: ['server', config.server_id],
    queryFn: () => api.get<Server>(`/api/v1/servers/${config.server_id}`),
    enabled: config.server_id != null,
    refetchInterval: 30_000,
  });

  if (config.server_id == null) {
    return <p>No server selected — edit this widget to pick one.</p>;
  }

  if (isLoading || !server) {
    return <p>Loading…</p>;
  }

  return (
    <div
      style={{
        border: '1px solid var(--border, #ddd)',
        borderRadius: 6,
        padding: 10,
      }}
    >
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <strong>{server.name}</strong>
        <StatusBadge status={server.status} />
      </div>

      {server.max_players !== null && (
        <p style={{ margin: '8px 0 0', fontSize: '0.85rem' }}>
          Players: {server.current_players ?? 0}/{server.max_players}
        </p>
      )}

      {server.cpu_percent !== null && <ProgressBar label="CPU" percent={server.cpu_percent} />}
      {server.memory_percent !== null && <ProgressBar label="RAM" percent={server.memory_percent} />}
    </div>
  );
}

export function ServerStatusWidgetConfigForm({ config, onChange }: WidgetConfigFormProps<ServerStatusWidgetConfig>) {
  const api = useApi();

  const { data: games } = useQuery({
    queryKey: ['games'],
    queryFn: () => api.get<Game[]>('/api/v1/games'),
  });

  const { data: allServers } = useQuery({
    queryKey: ['all-servers-for-picker', games?.map((g) => g.slug)],
    queryFn: async () => {
      if (!games) return [];
      const lists = await Promise.all(games.map((g) => api.get<Server[]>(`/api/v1/games/${g.slug}/servers`)));
      return lists.flat();
    },
    enabled: !!games,
  });

  return (
    <label>
      Server
      <select
        value={config.server_id ?? ''}
        onChange={(event) => onChange({ server_id: event.target.value ? Number(event.target.value) : null })}
      >
        <option value="">Select a server…</option>
        {allServers?.map((server) => (
          <option key={server.id} value={server.id}>
            {server.name}
          </option>
        ))}
      </select>
    </label>
  );
}
