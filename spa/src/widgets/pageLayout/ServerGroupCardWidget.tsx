import { useEffect, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { useApi } from '../../providers/ApiClientProvider';
import type { Game, ServerGroup } from '../../api/types';
import type { PageLayoutWidgetConfigFormProps } from './registry';

export interface ServerGroupCardWidgetConfig {
  server_group_id: number | null;
}

export const serverGroupCardWidgetDefaultConfig: ServerGroupCardWidgetConfig = {
  server_group_id: null,
};

// Cross-linking widget like ServerCardWidget, but for a ServerGroup rather
// than one Server. Links to the parent Game Detail page — there's no
// dedicated Server Group page in the frontend today (a real one is future
// scope, not this widget's job to add). "N running / M total" is a simple
// health-at-a-glance fraction rather than a full per-status breakdown,
// mirroring ServerGroupResource's running_count on the backend — see its
// docblock for why "running" specifically.
export function ServerGroupCardWidget({ config }: { config: ServerGroupCardWidgetConfig }) {
  const api = useApi();

  const { data: group, isLoading } = useQuery({
    queryKey: ['server-group', config.server_group_id],
    queryFn: () => api.get<ServerGroup>(`/api/v1/server-groups/${config.server_group_id}`),
    enabled: !!config.server_group_id,
  });

  if (!config.server_group_id) return <p style={{ padding: 12, opacity: 0.7 }}>No server group selected yet.</p>;
  if (isLoading) return <p style={{ padding: 12 }}>Loading…</p>;
  if (!group || !group.game_slug) return <p style={{ padding: 12, opacity: 0.7 }}>Server group not found.</p>;

  return (
    <Link
      to={`/games/${group.game_slug}`}
      style={{
        display: 'block',
        padding: 16,
        height: '100%',
        boxSizing: 'border-box',
        textDecoration: 'none',
        color: 'inherit',
      }}
    >
      <h4 style={{ margin: '0 0 8px' }}>{group.name}</h4>
      <p style={{ margin: 0, fontSize: '0.9rem', opacity: 0.85 }}>
        {group.running_count}/{group.servers_count} running
      </p>
    </Link>
  );
}

export function ServerGroupCardWidgetConfigForm({
  config,
  onChange,
}: PageLayoutWidgetConfigFormProps<ServerGroupCardWidgetConfig>) {
  const api = useApi();
  const { data: games } = useQuery({
    queryKey: ['games'],
    queryFn: () => api.get<Game[]>('/api/v1/games'),
  });

  // Same cascading game -> group picker as ServerCardWidgetConfigForm's
  // game -> server one, reusing /games/{slug}/server-groups.
  const [gameSlug, setGameSlug] = useState<string | null>(null);

  const { data: selectedGroup } = useQuery({
    queryKey: ['server-group', config.server_group_id],
    queryFn: () => api.get<ServerGroup>(`/api/v1/server-groups/${config.server_group_id}`),
    enabled: !!config.server_group_id,
  });

  useEffect(() => {
    if (selectedGroup?.game_slug) setGameSlug(selectedGroup.game_slug);
  }, [selectedGroup?.game_slug]);

  const { data: groups } = useQuery({
    queryKey: ['game', gameSlug, 'server-groups'],
    queryFn: () => api.get<ServerGroup[]>(`/api/v1/games/${gameSlug}/server-groups`),
    enabled: !!gameSlug,
  });

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
      <label>
        Game
        <div style={{ marginTop: 4 }}>
          <select
            value={gameSlug ?? ''}
            onChange={(event) => {
              setGameSlug(event.target.value || null);
              // The old server_group_id belongs to a different game's list.
              onChange({ ...config, server_group_id: null });
            }}
          >
            <option value="">Choose a game…</option>
            {games?.map((game) => (
              <option key={game.id} value={game.slug}>
                {game.name}
              </option>
            ))}
          </select>
        </div>
      </label>

      <label>
        Server group
        <div style={{ marginTop: 4 }}>
          <select
            value={config.server_group_id ?? ''}
            disabled={!gameSlug}
            onChange={(event) =>
              onChange({ ...config, server_group_id: event.target.value ? Number(event.target.value) : null })
            }
          >
            <option value="">Choose a server group…</option>
            {groups?.map((group) => (
              <option key={group.id} value={group.id}>
                {group.name}
              </option>
            ))}
          </select>
        </div>
      </label>
    </div>
  );
}
