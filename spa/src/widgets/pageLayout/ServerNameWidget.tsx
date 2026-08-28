import { useEffect, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useApi } from '../../providers/ApiClientProvider';
import type { Game, Server } from '../../api/types';
import type { ResolvedWidgetStyle } from '../shared/widgetStyle';
import type { PageLayoutWidgetConfigFormProps, PageLayoutWidgetContext } from './registry';

export interface ServerNameWidgetConfig {
  // font_size/text_color used to live here — folded into the universal
  // `style` key every widget now has (see the migration that moved
  // existing rows), so this config no longer carries its own bespoke
  // text-styling fields. See the component below for why the *color*
  // half of that (not the size half) still gets applied conditionally
  // rather than just inheriting the resolved style outright.
  server_id: number | null;
}

export const serverNameWidgetDefaultConfig: ServerNameWidgetConfig = {
  server_id: null,
};

/**
 * Split out of what used to be PictureWidget's built-in <h1> — the
 * picture is now purely a background layer (see its own docblock), and
 * the name is its own independent, layerable widget so an admin can place
 * it over a picture (or anywhere else) like any other widget.
 *
 * The universal style system's text_color is applied only while
 * `layered` — unchanged from before that system existed. A background
 * picture is arbitrary admin-chosen art, so a fixed color only ever makes
 * sense as a legibility choice *against* that art; off a picture, this
 * still just wants its ambient/default heading color, not whatever the
 * platform-wide text color default happens to be. text_size doesn't have
 * that conditional — it's applied whenever set, layered or not, same as
 * before. The text-shadow isn't configurable and always applies while
 * layered — a legibility floor under whatever color is picked, not a
 * style choice.
 *
 * validFor now covers every page type, not just 'server' — on a Server
 * page context.server is always set and wins outright (the widget still
 * "just knows" which server it's on, unchanged from before); everywhere
 * else there's no server in context to fall back on, so it behaves like
 * ServerCardWidget's cross-linking pickers and fetches config.server_id
 * instead. No server in context AND no server_id configured just shows a
 * placeholder, same empty-state convention as the other cross-linking
 * widgets.
 */
export function ServerNameWidget({
  context,
  config,
  layered,
  resolvedStyle,
}: {
  context: PageLayoutWidgetContext;
  config: ServerNameWidgetConfig;
  layered?: boolean;
  resolvedStyle?: ResolvedWidgetStyle;
}) {
  const api = useApi();

  const { data: fetchedServer, isLoading } = useQuery({
    queryKey: ['server', config.server_id],
    queryFn: () => api.get<Server>(`/api/v1/servers/${config.server_id}`),
    enabled: !context.server && !!config.server_id,
  });

  const server = context.server ?? fetchedServer;

  if (!server) {
    if (!context.server && !config.server_id) {
      return <p style={{ padding: 12, opacity: 0.7 }}>No server selected yet.</p>;
    }
    if (isLoading) return <p style={{ padding: 12 }}>Loading…</p>;
    return <p style={{ padding: 12, opacity: 0.7 }}>Server not found.</p>;
  }

  return (
    <div style={{ padding: layered ? 0 : '8px 12px', height: '100%', display: 'flex', alignItems: 'center' }}>
      <h1
        style={{
          margin: 0,
          fontSize: resolvedStyle?.textSize,
          color: layered ? resolvedStyle?.textColor : undefined,
          textShadow: layered ? '0 1px 3px rgba(0, 0, 0, 0.8)' : undefined,
        }}
      >
        {server.name}
      </h1>
    </div>
  );
}

export function ServerNameWidgetConfigForm({ config, onChange }: PageLayoutWidgetConfigFormProps<ServerNameWidgetConfig>) {
  const api = useApi();
  const { data: games } = useQuery({
    queryKey: ['games'],
    queryFn: () => api.get<Game[]>('/api/v1/games'),
  });

  // Same cascading game -> server picker as ServerCardWidgetConfigForm.
  // Harmless to fill in even when this instance is on a Server page (its
  // own context.server wins at render time regardless), but there's no
  // subjectType available here to conditionally hide it — see
  // PageLayoutWidgetConfigFormProps.
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
        Server (only used when not already on that server's own page)
        <div style={{ marginTop: 4 }}>
          <select
            value={gameSlug ?? ''}
            onChange={(event) => {
              setGameSlug(event.target.value || null);
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
    </div>
  );
}
