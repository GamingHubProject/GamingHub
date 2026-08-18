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
    </Link>
  );
}
