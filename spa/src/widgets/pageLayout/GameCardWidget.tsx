import { useQuery } from '@tanstack/react-query';
import { useApi } from '../../providers/ApiClientProvider';
import { GameCard } from '../../components/GameCard';
import type { Game } from '../../api/types';
import type { PageLayoutWidgetConfigFormProps } from './registry';

export interface GameCardWidgetConfig {
  mode: 'single' | 'all';
  // Both kept, deliberately redundant, same reasoning as
  // ServerBannerWidgetConfig's background_asset_id/background_url: game_id
  // is the real reference, game_slug lets 'single' mode render straight
  // from the existing slug-based /games/{slug} endpoint without a second
  // "resolve id to slug" lookup. Both null/unused in 'all' mode.
  game_id: number | null;
  game_slug: string | null;
}

export const gameCardWidgetDefaultConfig: GameCardWidgetConfig = {
  mode: 'all',
  game_id: null,
  game_slug: null,
};

// 'all' mode renders the exact same grid+GameCard markup the Home and
// Games-listing pages always hardcoded — real reuse, not a rebuild, so
// this is a drop-in default widget for both (see PageLayoutController's
// DEFAULT_WIDGETS). 'single' mode is the cross-linking case: one specific
// game's card placeable anywhere game-card is validFor (Home, the games
// list, or a Server Detail page).
export function GameCardWidget({ config }: { config: GameCardWidgetConfig }) {
  const api = useApi();

  const { data: games, isLoading: allLoading } = useQuery({
    queryKey: ['games'],
    queryFn: () => api.get<Game[]>('/api/v1/games'),
    enabled: config.mode === 'all',
  });

  const { data: singleGame, isLoading: singleLoading } = useQuery({
    queryKey: ['game', config.game_slug],
    queryFn: () => api.get<Game>(`/api/v1/games/${config.game_slug}`),
    enabled: config.mode === 'single' && !!config.game_slug,
  });

  if (config.mode === 'single') {
    if (!config.game_slug) return <p style={{ padding: 12, opacity: 0.7 }}>No game selected yet.</p>;
    if (singleLoading) return <p style={{ padding: 12 }}>Loading…</p>;
    if (!singleGame) return <p style={{ padding: 12, opacity: 0.7 }}>Game not found.</p>;
    return (
      <div style={{ padding: 12, maxWidth: 260 }}>
        <GameCard game={singleGame} />
      </div>
    );
  }

  if (allLoading) return <p>Loading games…</p>;

  // No padding here on purpose — this is the exact markup the Home/Games
  // pages always hardcoded (no wrapping padding of its own), now just
  // living inside a chromeless widget instead. See registry.ts's
  // chromeless flag on this widget's registration.
  return (
    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(220px, 1fr))', gap: 16 }}>
      {games?.map((game) => (
        <GameCard key={game.id} game={game} />
      ))}
    </div>
  );
}

export function GameCardWidgetConfigForm({ config, onChange }: PageLayoutWidgetConfigFormProps<GameCardWidgetConfig>) {
  const api = useApi();
  const { data: games } = useQuery({
    queryKey: ['games'],
    queryFn: () => api.get<Game[]>('/api/v1/games'),
  });

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
      <label>
        <input
          type="radio"
          checked={config.mode === 'all'}
          onChange={() => onChange({ ...config, mode: 'all' })}
        />{' '}
        All games (grid)
      </label>
      <label>
        <input
          type="radio"
          checked={config.mode === 'single'}
          onChange={() => onChange({ ...config, mode: 'single' })}
        />{' '}
        One game
      </label>

      {config.mode === 'single' && (
        <select
          value={config.game_slug ?? ''}
          onChange={(event) => {
            const game = games?.find((g) => g.slug === event.target.value);
            onChange({ ...config, game_id: game?.id ?? null, game_slug: game?.slug ?? null });
          }}
        >
          <option value="">Choose a game…</option>
          {games?.map((game) => (
            <option key={game.id} value={game.slug}>
              {game.name}
            </option>
          ))}
        </select>
      )}
    </div>
  );
}
