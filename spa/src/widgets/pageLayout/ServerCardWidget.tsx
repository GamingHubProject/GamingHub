import { useEffect, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { useApi } from '../../providers/ApiClientProvider';
import { StatusBadge } from '../shared/StatusBadge';
import { ProgressBar } from '../shared/ProgressBar';
import type { Game, Server } from '../../api/types';
import type { PageLayoutWidgetConfigFormProps } from './registry';

export interface ServerCardWidgetConfig {
  server_id: number | null;
  show_status: boolean;
  show_player_count: boolean;
  show_resources: boolean;
}

// Minimal by default (name + link only) — an admin opts into more, rather
// than a busy card being the default everywhere this gets dropped.
export const serverCardWidgetDefaultConfig: ServerCardWidgetConfig = {
  server_id: null,
  show_status: true,
  show_player_count: true,
  show_resources: false,
};

// Cross-linking widget: a specific server's card placeable on Home, the
// games list, or a Game Detail page — not just its own Server Detail page
// (see registry.ts's validFor on the registration). Fetches by id
// (/api/v1/servers/{id}, unlike GameCardWidget's slug-based single mode)
// since that endpoint already exists id-keyed; game_slug on the response
// (see ServerResource) is what makes the link buildable without a second
// fetch to resolve which game owns this server.
export function ServerCardWidget({ config }: { config: ServerCardWidgetConfig }) {
  const api = useApi();

  const { data: server, isLoading } = useQuery({
    queryKey: ['server', config.server_id],
    queryFn: () => api.get<Server>(`/api/v1/servers/${config.server_id}`),
    enabled: !!config.server_id,
  });

  if (!config.server_id) return <p style={{ padding: 12, opacity: 0.7 }}>No server selected yet.</p>;
  if (isLoading) return <p style={{ padding: 12 }}>Loading…</p>;
  if (!server || !server.game_slug) return <p style={{ padding: 12, opacity: 0.7 }}>Server not found.</p>;

  return (
    <Link
      to={`/games/${server.game_slug}/servers/${server.id}`}
      style={{
        display: 'block',
        padding: 16,
        height: '100%',
        boxSizing: 'border-box',
        textDecoration: 'none',
        color: 'inherit',
      }}
    >
      <h4 style={{ margin: '0 0 8px' }}>{server.name}</h4>
      {config.show_status && <StatusBadge status={server.status} />}
      {config.show_player_count && server.max_players !== null && (
        <p style={{ margin: '8px 0 0', fontSize: '0.9rem', opacity: 0.85 }}>
          {server.current_players ?? 0}/{server.max_players} players
        </p>
      )}
      {config.show_resources && (
        <div style={{ marginTop: 8 }}>
          {server.cpu_percent !== null && <ProgressBar label="CPU" percent={server.cpu_percent} />}
          {server.memory_percent !== null && <ProgressBar label="RAM" percent={server.memory_percent} />}
        </div>
      )}
    </Link>
  );
}

export function ServerCardWidgetConfigForm({ config, onChange }: PageLayoutWidgetConfigFormProps<ServerCardWidgetConfig>) {
  const api = useApi();
  const { data: games } = useQuery({
    queryKey: ['games'],
    queryFn: () => api.get<Game[]>('/api/v1/games'),
  });

  // Cascading game -> server pickers, reusing the two existing public
  // endpoints (/games, /games/{slug}/servers) — cheaper than adding a new
  // "list every server across every game" endpoint just for this dropdown.
  // gameSlug is its own bit of local state (not derived from config, which
  // has no game field) so the picker can pick a game before any server is
  // chosen; it initializes from the currently-configured server's game, if
  // any, once that loads.
  const [gameSlug, setGameSlug] = useState<string | null>(null);

  const { data: selectedServer } = useQuery({
    queryKey: ['server', config.server_id],
    queryFn: () => api.get<Server>(`/api/v1/servers/${config.server_id}`),
    enabled: !!config.server_id,
  });

  useEffect(() => {
    if (selectedServer?.game_slug) setGameSlug(selectedServer.game_slug);
  }, [selectedServer?.game_slug]);

  const { data: servers } = useQuery({
    queryKey: ['game', gameSlug, 'servers'],
    queryFn: () => api.get<Server[]>(`/api/v1/games/${gameSlug}/servers`),
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
              // The old server_id belongs to a different game's list.
              onChange({ ...config, server_id: null });
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
        Server
        <div style={{ marginTop: 4 }}>
          <select
            value={config.server_id ?? ''}
            disabled={!gameSlug}
            onChange={(event) => onChange({ ...config, server_id: event.target.value ? Number(event.target.value) : null })}
          >
            <option value="">Choose a server…</option>
            {servers?.map((server) => (
              <option key={server.id} value={server.id}>
                {server.name}
              </option>
            ))}
          </select>
        </div>
      </label>

      <label style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
        <input
          type="checkbox"
          checked={config.show_status}
          onChange={(event) => onChange({ ...config, show_status: event.target.checked })}
        />
        Show status
      </label>
      <label style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
        <input
          type="checkbox"
          checked={config.show_player_count}
          onChange={(event) => onChange({ ...config, show_player_count: event.target.checked })}
        />
        Show player count
      </label>
      <label style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
        <input
          type="checkbox"
          checked={config.show_resources}
          onChange={(event) => onChange({ ...config, show_resources: event.target.checked })}
        />
        Show CPU/RAM
      </label>
    </div>
  );
}
