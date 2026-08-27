import { Link } from 'react-router-dom';
import { CardIcon } from '../widgets/shared/CardIcon';
import { cardBodyStyle, cardContainerStyle, cardHeaderRowStyle, cardMetaStyle, cardPaddingStyle, cardTitleStyle } from '../widgets/shared/cardScale';
import type { Game } from '../api/types';

/**
 * showIcon defaults true — GameCardWidget's 'all' mode passes its own
 * config.show_icon through, but a plain <GameCard game={g}/> (no widget
 * config in scope) should keep looking exactly as it always did.
 */
export function GameCard({ game, showIcon = true }: { game: Game; showIcon?: boolean }) {
  return (
    <Link
      to={`/games/${game.slug}`}
      style={{
        display: 'block',
        border: '1px solid var(--border, #ddd)',
        borderRadius: 8,
        textDecoration: 'none',
        color: 'inherit',
        ...cardContainerStyle,
        ...cardPaddingStyle,
      }}
    >
      <div style={{ ...cardHeaderRowStyle, marginBottom: 'clamp(2px, 2cqh, 8px)' }}>
        <CardIcon url={game.icon_url} show={showIcon} />
        <h3 style={{ ...cardTitleStyle, margin: 0 }}>{game.name}</h3>
      </div>
      {game.description && <p style={{ ...cardBodyStyle, margin: 0, whiteSpace: 'normal' }}>{game.description}</p>}
      {/* has_servers=false already means "hosted elsewhere, not Gaming
          Hub-managed" (see GameResource's Filament form helper text) —
          "External" reuses that existing signal rather than needing a
          separate flag. servers_count only ever means something when
          has_servers is true, so it's never shown otherwise. */}
      <p style={{ ...cardMetaStyle, margin: '8px 0 0' }}>
        {game.has_servers ? `${game.servers_count ?? 0} Servers` : 'External'}
      </p>
    </Link>
  );
}
