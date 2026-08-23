import { useQuery } from '@tanstack/react-query';
import { useApi } from '../providers/ApiClientProvider';
import { StatusBadge } from './shared/StatusBadge';
import { ProgressBar } from './shared/ProgressBar';
import type { Game, Server } from '../api/types';
import type { WidgetConfigFormProps } from './registry';

export interface ServerStatusWidgetConfig {
  server_id: number | null;
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
