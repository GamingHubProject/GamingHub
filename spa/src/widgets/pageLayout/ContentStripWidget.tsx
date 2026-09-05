import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { useApi } from '../../providers/ApiClientProvider';
import { AssetPicker } from '../../components/AssetPicker';
import type { AssetPreview } from '../../components/AssetPicker';
import { GameCard } from '../../components/GameCard';
import { ServerCard } from '../../components/ServerCard';
import { Listbox } from '../../components/Listbox';
import { isExternal } from '../../layout/useNavigation';
import type { Asset, Game, Server } from '../../api/types';
import type { PageLayoutWidgetConfigFormProps } from './registry';

export type StripSource = 'games' | 'servers' | 'custom';

export interface StripItem {
  title: string;
  image_asset_id: number | null;
  image_url: string | null;
  url: string;
}

export interface ContentStripWidgetConfig {
  /** Optional heading above the row. */
  heading: string;
  source: StripSource;
  /** Servers mode: whose servers. Unused by the other two. */
  game_slug: string | null;
  /** Custom mode: the items themselves. */
  items: StripItem[];
  /** Card width in px — what makes the row scroll rather than squash. */
  card_width: number;
}

export const contentStripWidgetDefaultConfig: ContentStripWidgetConfig = {
  heading: '',
  source: 'games',
  game_slug: null,
  items: [],
  card_width: 220,
};

/**
 * A horizontally scrolling row of cards.
 *
 * The three sources reuse what already exists rather than inventing a
 * fourth card: `games` and `servers` render the same GameCard and
 * ServerCard the grids use, so a strip and a grid of the same thing look
 * identical, and `custom` is for the case neither covers.
 *
 * Cards keep a fixed width and the row scrolls. The alternative — letting
 * them shrink to fit — turns a strip of ten into ten slivers, which is the
 * one thing a strip is supposed to avoid.
 */
export function ContentStripWidget({ config }: { config: ContentStripWidgetConfig }) {
  const api = useApi();

  // A widget's config is an opaque JSON blob that an older build, a hand
  // edit or an import can write, so nothing here may assume a field is
  // present. A missing `items` used to take the whole page down with it,
  // which is a steep price for one absent array.
  const source = config.source ?? contentStripWidgetDefaultConfig.source;
  const items = config.items ?? [];
  const cardWidth = config.card_width ?? contentStripWidgetDefaultConfig.card_width;

  const { data: games } = useQuery({
    queryKey: ['games'],
    queryFn: () => api.get<Game[]>('/api/v1/games'),
    enabled: source === 'games',
  });

  const { data: servers } = useQuery({
    queryKey: ['games', config.game_slug, 'servers'],
    queryFn: () => api.get<Server[]>(`/api/v1/games/${config.game_slug}/servers`),
    enabled: source === 'servers' && !!config.game_slug,
  });

  const cards = (() => {
    if (source === 'games') {
      return (games ?? []).map((game) => <GameCard key={game.id} game={game} />);
    }
    if (source === 'servers') {
      return (servers ?? []).map((server) => (
        <ServerCard key={server.id} server={server} gameSlug={config.game_slug ?? ''} />
      ));
    }
    return items.map((item, index) => <CustomCard key={index} item={item} />);
  })();

  return (
    <div style={{ height: '100%', display: 'flex', flexDirection: 'column', gap: 'var(--space-normal, 12px)' }}>
      {config.heading && (
        <h3 style={{ margin: 0, padding: '0 var(--space-normal, 12px)', fontSize: '1.05rem' }}>{config.heading}</h3>
      )}

      {cards.length === 0 ? (
        <p style={{ opacity: 0.7, padding: '0 var(--space-normal, 12px)' }}>Nothing to show here yet.</p>
      ) : (
        <div
          style={{
            display: 'flex',
            gap: 'var(--space-loose, 16px)',
            // The strip itself scrolls; the page never does sideways.
            overflowX: 'auto',
            overflowY: 'hidden',
            padding: '0 var(--space-normal, 12px) var(--space-tight, 6px)',
            // Cards land on their own edges rather than half off-screen.
            scrollSnapType: 'x proximity',
            flex: 1,
          }}
        >
          {cards.map((card, index) => (
            <div
              key={index}
              style={{
                flex: `0 0 ${cardWidth}px`,
                scrollSnapAlign: 'start',
                // Each card is its own container-query context, so the
                // shared card scaling sizes text per card exactly as it
                // does in a grid.
                containerType: 'size',
                minHeight: 140,
              }}
            >
              {card}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

function CustomCard({ item }: { item: StripItem }) {
  const body = (
    <>
      {item.image_url && (
        <img
          src={item.image_url}
          alt=""
          style={{ width: '100%', height: 110, objectFit: 'cover', borderRadius: 'calc(var(--radius, 8px) / 1.5)' }}
        />
      )}
      <strong style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{item.title}</strong>
    </>
  );

  const style = {
    display: 'flex',
    flexDirection: 'column' as const,
    gap: 'var(--space-tight, 6px)',
    height: '100%',
    padding: 'var(--space-normal, 12px)',
    border: '1px solid var(--border, #ddd)',
    borderRadius: 'var(--radius, 8px)',
    color: 'inherit',
    textDecoration: 'none',
  };

  if (!item.url) return <div style={style}>{body}</div>;

  return isExternal(item.url) ? (
    <a href={item.url} style={style} rel="noreferrer noopener" target="_blank">
      {body}
    </a>
  ) : (
    <Link to={item.url} style={style}>
      {body}
    </Link>
  );
}

export function ContentStripWidgetConfigForm({
  config,
  onChange,
}: PageLayoutWidgetConfigFormProps<ContentStripWidgetConfig>) {
  const api = useApi();
  const { data: games } = useQuery({ queryKey: ['games'], queryFn: () => api.get<Game[]>('/api/v1/games') });

  // Same defensiveness as the renderer: opening the settings on a config
  // written by something else shouldn't crash the editor either.
  const source = config.source ?? contentStripWidgetDefaultConfig.source;
  const items = config.items ?? [];

  function updateItem(index: number, patch: Partial<StripItem>) {
    onChange({
      ...config,
      items: items.map((item, i) => (i === index ? { ...item, ...patch } : item)),
    });
  }

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
      <label>
        Heading
        <input
          value={config.heading ?? ''}
          onChange={(event) => onChange({ ...config, heading: event.target.value })}
          placeholder="Optional"
          style={{ width: '100%', marginTop: 4 }}
        />
      </label>

      <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
        <span>Show</span>
        <Listbox<StripSource>
          label="Show"
          value={source}
          options={[
            { value: 'games', label: 'Every game' },
            { value: 'servers', label: "One game's servers" },
            { value: 'custom', label: 'Items I choose' },
          ]}
          onChange={(source) => onChange({ ...config, source })}
        />
      </div>

      {source === 'servers' && (
        <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
          <span>Game</span>
          <Listbox
            label="Game"
            value={config.game_slug ?? ''}
            options={[
              { value: '', label: 'Choose a game…' },
              ...(games ?? []).map((game) => ({ value: game.slug, label: game.name })),
            ]}
            onChange={(slug) => onChange({ ...config, game_slug: slug || null })}
          />
        </div>
      )}

      {source === 'custom' && (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
          {items.map((item, index) => (
            <div
              key={index}
              style={{
                display: 'flex',
                gap: 8,
                alignItems: 'center',
                flexWrap: 'wrap',
                padding: 8,
                border: '1px solid var(--border, #ddd)',
                borderRadius: 'calc(var(--radius, 8px) / 1.5)',
              }}
            >
              <AssetPicker
                value={item.image_url ? ({ thumbnail_url: item.image_url, alt_text: null } as AssetPreview) : null}
                onChange={(asset: Asset | null) =>
                  updateItem(index, { image_asset_id: asset?.id ?? null, image_url: asset?.url ?? null })
                }
              />
              <input
                value={item.title}
                placeholder="Title"
                onChange={(event) => updateItem(index, { title: event.target.value })}
                style={{ flex: 1, minWidth: 120 }}
              />
              <input
                value={item.url}
                placeholder="/games or https://…"
                onChange={(event) => updateItem(index, { url: event.target.value })}
                style={{ flex: 1, minWidth: 140 }}
              />
              <button
                type="button"
                aria-label={`Remove ${item.title || 'item'}`}
                onClick={() => onChange({ ...config, items: items.filter((_, i) => i !== index) })}
              >
                ×
              </button>
            </div>
          ))}
          <button
            type="button"
            onClick={() =>
              onChange({
                ...config,
                items: [...items, { title: 'New item', image_asset_id: null, image_url: null, url: '' }],
              })
            }
          >
            + Add item
          </button>
        </div>
      )}

      <label style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
        Card width ({config.card_width ?? contentStripWidgetDefaultConfig.card_width}px)
        <input
          type="range"
          min={140}
          max={360}
          step={10}
          value={config.card_width ?? contentStripWidgetDefaultConfig.card_width}
          onChange={(event) => onChange({ ...config, card_width: Number(event.target.value) })}
        />
      </label>
    </div>
  );
}
