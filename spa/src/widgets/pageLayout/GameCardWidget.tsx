import { useQuery } from '@tanstack/react-query';
import { useApi } from '../../providers/ApiClientProvider';
import { GameCard } from '../../components/GameCard';
import { AssetPicker } from '../../components/AssetPicker';
import type { AssetPreview } from '../../components/AssetPicker';
import type { Asset, Game } from '../../api/types';
import type { PageLayoutWidgetConfigFormProps } from './registry';

export interface GameCardWidgetConfig {
  mode: 'single' | 'all';
  // Both kept, deliberately redundant, same reasoning as
  // PictureWidgetConfig's background_asset_id/background_url: game_id
  // is the real reference, game_slug lets 'single' mode render straight
  // from the existing slug-based /games/{slug} endpoint without a second
  // "resolve id to slug" lookup. Both null/unused in 'all' mode.
  game_id: number | null;
  game_slug: string | null;
  // Applies in both modes (unlike icon_asset_id/icon_url below) — same
  // show_icon-toggle pattern as ServerCardWidget/ServerGroupCardWidget
  // (see widgets/shared/CardIcon.tsx). Defaults true, unlike those two:
  // Game already has its own icon_url most of the time, so showing it is
  // the natural default here rather than something an admin opts into.
  show_icon: boolean;
  // 'single' mode only — an icon override for just this widget instance,
  // not an edit to the Game record itself (same reasoning as the banner
  // override above: this is widget config, not shared data). Falls back
  // to the game's own icon_url when unset. Meaningless/unused in 'all'
  // mode, same as game_id/game_slug are unused in 'single' mode's
  // opposite direction — each card in the grid there uses its own game's
  // icon_url.
  icon_asset_id: number | null;
  icon_url: string | null;
}

export const gameCardWidgetDefaultConfig: GameCardWidgetConfig = {
  mode: 'all',
  game_id: null,
  game_slug: null,
  show_icon: true,
  icon_asset_id: null,
  icon_url: null,
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
    // Override applied at render time only — never sent back to the Game
    // record itself, see the config field's docblock.
    const displayGame = config.icon_url ? { ...singleGame, icon_url: config.icon_url } : singleGame;
    return (
      <div style={{ padding: 12, maxWidth: 260, height: '100%', boxSizing: 'border-box' }}>
        <GameCard game={displayGame} showIcon={config.show_icon} />
      </div>
    );
  }

  if (allLoading) return <p>Loading games…</p>;

  // No padding here on purpose — this is the exact markup the Home/Games
  // pages always hardcoded (no wrapping padding of its own), now just
  // living inside a chromeless widget instead. See registry.ts's
  // chromeless flag on this widget's registration.
  //
  // gridAutoRows is an explicit height (not auto) so each GameCard gets a
  // definite box to run its own container query against — "each card
  // scales individually" (per the confirmed design) needs every card to
  // be its own container-query context, not just the widget as a whole.
  // height:100% + overflow:hidden means a grid that overflows its widget
  // box clips rather than growing/scrolling, matching "grid clips, not
  // scrolls" for 'all' mode specifically.
  return (
    <div
      style={{
        display: 'grid',
        gridTemplateColumns: 'repeat(auto-fill, minmax(220px, 1fr))',
        gridAutoRows: '200px',
        gap: 16,
        height: '100%',
        overflow: 'hidden',
      }}
    >
      {games?.map((game) => (
        <GameCard key={game.id} game={game} showIcon={config.show_icon} />
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

      <label style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
        <input
          type="checkbox"
          checked={config.show_icon}
          onChange={(event) => onChange({ ...config, show_icon: event.target.checked })}
        />
        Show icon
      </label>

      {config.mode === 'single' && (
        <>
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

          <label>
            Icon override (optional)
            <div style={{ marginTop: 4 }}>
              <AssetPicker
                value={
                  config.icon_url
                    ? ({ thumbnail_url: config.icon_url, alt_text: null } as AssetPreview)
                    : null
                }
                onChange={(asset: Asset | null) =>
                  onChange({ ...config, icon_asset_id: asset?.id ?? null, icon_url: asset?.url ?? null })
                }
              />
            </div>
          </label>
        </>
      )}
    </div>
  );
}
