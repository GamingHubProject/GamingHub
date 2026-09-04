import { Link } from 'react-router-dom';
import type { Server } from '../api/types';

export function ServerCard({ server, gameSlug }: { server: Server; gameSlug: string }) {
  return (
    <Link
      to={`/games/${gameSlug}/servers/${server.id}`}
      style={{
        display: 'block',
        border: '1px solid var(--border, #ddd)',
        borderRadius: 'var(--radius, 8px)',
        padding: 16,
        textDecoration: 'none',
        color: 'inherit',
      }}
    >
      <h4 style={{ margin: '0 0 4px' }}>{server.name}</h4>
      <span>{server.status}</span>
      {server.max_players !== null && (
        <span style={{ marginLeft: 8, opacity: 0.8 }}>
          {server.current_players ?? 0}/{server.max_players} players
        </span>
      )}
    </Link>
  );
}
