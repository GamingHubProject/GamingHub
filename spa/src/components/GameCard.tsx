import { Link } from 'react-router-dom';
import type { Game } from '../api/types';

export function GameCard({ game }: { game: Game }) {
  return (
    <Link
      to={`/games/${game.slug}`}
      style={{
        display: 'block',
        border: '1px solid var(--border, #ddd)',
        borderRadius: 8,
        padding: 16,
        textDecoration: 'none',
        color: 'inherit',
      }}
    >
      {game.icon_url && (
        <img src={game.icon_url} alt="" width={48} height={48} style={{ marginBottom: 8 }} />
      )}
      <h3 style={{ margin: '0 0 4px' }}>{game.name}</h3>
      {game.description && <p style={{ margin: 0, opacity: 0.8 }}>{game.description}</p>}
      {/* has_servers=false already means "hosted elsewhere, not Gaming
          Hub-managed" (see GameResource's Filament form helper text) —
          "External" reuses that existing signal rather than needing a
          separate flag. servers_count only ever means something when
          has_servers is true, so it's never shown otherwise. */}
      <p style={{ margin: '8px 0 0', fontSize: '0.8rem', opacity: 0.7 }}>
        {game.has_servers ? `${game.servers_count ?? 0} Servers` : 'External'}
      </p>
    </Link>
  );
}
