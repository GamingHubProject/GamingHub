import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useApi } from '../providers/ApiClientProvider';
import { ApiError } from '../api/client';

interface GameIdentityEntry {
  game_slug: string;
  label: string;
  linked: boolean;
}

export function GameIdentities() {
  const api = useApi();

  const { data: entries, isLoading } = useQuery({
    queryKey: ['game-identities'],
    queryFn: () => api.get<GameIdentityEntry[]>('/api/v1/user/game-identities'),
  });

  if (isLoading) return <p>Loading game identities…</p>;
  if (!entries || entries.length === 0) return null;

  return (
    <fieldset style={{ border: '1px solid var(--border, #ddd)', borderRadius: 6, padding: 12 }}>
      <legend>Game identities</legend>
      <p style={{ margin: '0 0 12px', fontSize: '0.875rem', color: 'var(--muted, #888)' }}>
        Link your in-game player ID so the site can track your stats. Your raw ID is never stored or shown — only a
        one-way hash.
      </p>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
        {entries.map((entry) => (
          <GameIdentityRow key={entry.game_slug} entry={entry} />
        ))}
      </div>
    </fieldset>
  );
}

function GameIdentityRow({ entry }: { entry: GameIdentityEntry }) {
  const api = useApi();
  const queryClient = useQueryClient();
  const [playerId, setPlayerId] = useState('');
  const [error, setError] = useState<string | null>(null);

  const link = useMutation({
    mutationFn: () =>
      api.post('/api/v1/user/game-identities', {
        game_slug: entry.game_slug,
        player_id: playerId,
      }),
    onSuccess: () => {
      setPlayerId('');
      setError(null);
      queryClient.invalidateQueries({ queryKey: ['game-identities'] });
    },
    onError: (caught) => {
      if (caught instanceof ApiError && caught.status === 422) {
        const body = caught.body as { errors?: Record<string, string[]>; message?: string } | null;
        setError(body?.errors?.player_id?.[0] ?? body?.message ?? 'Could not link.');
      } else {
        setError('Something went wrong.');
      }
    },
  });

  const unlink = useMutation({
    mutationFn: () => api.delete(`/api/v1/user/game-identities/${entry.game_slug}`),
    onSuccess: () => {
      setError(null);
      queryClient.invalidateQueries({ queryKey: ['game-identities'] });
    },
  });

  return (
    <div>
      <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
        <strong style={{ minWidth: 100 }}>{entry.game_slug}</strong>
        {entry.linked ? (
          <>
            <span
              style={{
                fontSize: '0.8rem',
                padding: '2px 8px',
                borderRadius: 4,
                background: 'var(--success-bg, #dcfce7)',
                color: 'var(--success, #166534)',
              }}
            >
              Linked
            </span>
            <button type="button" onClick={() => unlink.mutate()} disabled={unlink.isPending}>
              {unlink.isPending ? 'Unlinking…' : 'Unlink'}
            </button>
          </>
        ) : (
          <form
            onSubmit={(e) => {
              e.preventDefault();
              if (playerId.trim()) link.mutate();
            }}
            style={{ display: 'flex', alignItems: 'center', gap: 6, flex: 1 }}
          >
            <input
              type="password"
              value={playerId}
              onChange={(e) => {
                setPlayerId(e.target.value);
                setError(null);
              }}
              placeholder={entry.label}
              autoComplete="off"
              style={{ flex: 1, minWidth: 120 }}
            />
            <button type="submit" disabled={link.isPending || !playerId.trim()}>
              {link.isPending ? 'Linking…' : 'Link'}
            </button>
          </form>
        )}
      </div>
      {error && (
        <small style={{ color: 'var(--danger, #b3261e)', display: 'block', marginTop: 4 }}>{error}</small>
      )}
    </div>
  );
}
