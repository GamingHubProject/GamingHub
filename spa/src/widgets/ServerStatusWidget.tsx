import { useQuery } from '@tanstack/react-query';
import { useApi } from '../providers/ApiClientProvider';
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
    <div>
      <h4 style={{ margin: '0 0 8px' }}>{server.name}</h4>
      <p style={{ margin: 0 }}>Status: {server.status}</p>
      {server.max_players !== null && (
        <p style={{ margin: 0 }}>
          Players: {server.current_players ?? 0}/{server.max_players}
        </p>
      )}
      {server.cpu_percent !== null && <p style={{ margin: 0 }}>CPU: {server.cpu_percent}%</p>}
      {server.memory_percent !== null && <p style={{ margin: 0 }}>Memory: {server.memory_percent}%</p>}
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
