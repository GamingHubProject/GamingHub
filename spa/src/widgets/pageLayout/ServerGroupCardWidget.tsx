import { useEffect, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { useApi } from '../../providers/ApiClientProvider';
import { AssetPicker } from '../../components/AssetPicker';
import type { AssetPreview } from '../../components/AssetPicker';
import { CardIcon } from '../shared/CardIcon';
import { cardBodyStyle, cardContainerStyle, cardHeaderRowStyle, cardPaddingStyle, cardTitleStyle } from '../shared/cardScale';
import type { Asset, Game, ServerGroup } from '../../api/types';
import type { PageLayoutWidgetConfigFormProps } from './registry';
import { Listbox } from '../../components/Listbox';

export interface ServerGroupCardWidgetConfig {
  server_group_id: number | null;
  // Same widget-level-only icon pattern as ServerCardWidget — ServerGroup
  // has no icon field of its own either.
  show_icon: boolean;
  icon_asset_id: number | null;
  icon_url: string | null;
}

export const serverGroupCardWidgetDefaultConfig: ServerGroupCardWidgetConfig = {
  server_group_id: null,
  show_icon: false,
  icon_asset_id: null,
  icon_url: null,
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
        textDecoration: 'none',
        color: 'inherit',
        ...cardContainerStyle,
        ...cardPaddingStyle,
      }}
    >
      <div style={{ ...cardHeaderRowStyle, marginBottom: 'clamp(2px, 2cqh, 8px)' }}>
        <CardIcon url={config.icon_url} show={config.show_icon} />
        <h4 style={{ ...cardTitleStyle, margin: 0 }}>{group.name}</h4>
      </div>
      <p style={{ ...cardBodyStyle, margin: 0 }}>
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
          <Listbox
            label="Game"
            value={gameSlug ?? ''}
            options={[
              { value: '', label: 'Choose a game…' },
              ...(games ?? []).map((game) => ({ value: game.slug, label: game.name })),
            ]}
            onChange={(next) => {
              setGameSlug(next || null);
              // The old server_group_id belongs to a different game's list.
              onChange({ ...config, server_group_id: null });
            }}
          />
        </div>
      </label>

      <label>
        Server group
        <div style={{ marginTop: 4 }}>
          <Listbox
            label="Server group"
            value={config.server_group_id ? String(config.server_group_id) : ''}
            disabled={!gameSlug}
            options={[
              { value: '', label: 'Choose a server group…' },
              ...(groups ?? []).map((group) => ({ value: String(group.id), label: group.name })),
            ]}
            onChange={(next) => onChange({ ...config, server_group_id: next ? Number(next) : null })}
          />
        </div>
      </label>

      <label style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
        <input
          type="checkbox"
          checked={config.show_icon}
          onChange={(event) => onChange({ ...config, show_icon: event.target.checked })}
        />
        Show icon
      </label>
      {config.show_icon && (
        <label>
          Icon
          <div style={{ marginTop: 4 }}>
            <AssetPicker
              value={config.icon_url ? ({ thumbnail_url: config.icon_url, alt_text: null } as AssetPreview) : null}
              onChange={(asset: Asset | null) => onChange({ ...config, icon_asset_id: asset?.id ?? null, icon_url: asset?.url ?? null })}
            />
          </div>
        </label>
      )}
    </div>
  );
}
