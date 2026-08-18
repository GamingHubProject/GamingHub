import { useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { useApi } from '../providers/ApiClientProvider';
import { useThemeScope } from '../providers/ThemeProvider';
import type { Server } from '../api/types';

export function ServerDetail() {
  const { id } = useParams<{ id: string }>();
  const api = useApi();

  const { data: server, isLoading } = useQuery({
    queryKey: ['server', id],
    queryFn: () => api.get<Server>(`/api/v1/servers/${id}`),
    enabled: !!id,
    refetchInterval: 30_000,
  });

  useThemeScope({ gameId: server?.game_id, serverId: server?.id });

  if (isLoading) return <p>Loading…</p>;
  if (!server) return <p>Server not found.</p>;

  return (
    <div>
      <h1>{server.name}</h1>
      <p>Status: {server.status}</p>
      {server.max_players !== null && (
        <p>
          Players: {server.current_players ?? 0}/{server.max_players}
        </p>
      )}
      {server.cpu_percent !== null && <p>CPU: {server.cpu_percent}%</p>}
      {server.memory_percent !== null && <p>Memory: {server.memory_percent}%</p>}
      {server.disk_percent !== null && <p>Disk: {server.disk_percent}%</p>}
      {server.node_name && <p>Node: {server.node_name}</p>}

      {server.allocations.length > 0 && (
        <section>
          <h2>Allocations</h2>
          <ul>
            {server.allocations.map((allocation) => (
              <li key={allocation.id}>
                {allocation.ip}:{allocation.port} {allocation.is_default && '(default)'}
              </li>
            ))}
          </ul>
        </section>
      )}
    </div>
  );
}
