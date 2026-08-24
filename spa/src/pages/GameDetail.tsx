import { useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { useApi } from '../providers/ApiClientProvider';
import { useAuth } from '../providers/AuthProvider';
import { useThemeScope } from '../providers/ThemeProvider';
import { ServerCard } from '../components/ServerCard';
import { PageLayoutEditor } from '../components/PageLayoutEditor';
import type { Game, Server } from '../api/types';

export function GameDetail() {
  const { slug } = useParams<{ slug: string }>();
  const api = useApi();
  const { user } = useAuth();

  const { data: game, isLoading: gameLoading } = useQuery({
    queryKey: ['game', slug],
    queryFn: () => api.get<Game>(`/api/v1/games/${slug}`),
    enabled: !!slug,
  });

  const { data: servers, isLoading: serversLoading } = useQuery({
    queryKey: ['game', slug, 'servers'],
    queryFn: () => api.get<Server[]>(`/api/v1/games/${slug}/servers`),
    enabled: !!slug && !!game?.has_servers,
  });

  useThemeScope({ gameId: game?.id });

  if (gameLoading) return <p>Loading…</p>;
  if (!game) return <p>Game not found.</p>;

  return (
    <div>
      <PageLayoutEditor
        layoutUrl={`/api/v1/games/${slug}/layout`}
        queryKey={['page-layout', 'game', slug]}
        context={{ subjectType: 'game', game }}
        isAdmin={user?.is_admin ?? false}
      />

      <h1>{game.name}</h1>
      {game.description && <p>{game.description}</p>}

      {game.has_servers && (
        <section>
          <h2>Servers</h2>
          {serversLoading ? (
            <p>Loading servers…</p>
          ) : servers && servers.length > 0 ? (
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(220px, 1fr))', gap: 16 }}>
              {servers.map((server) => (
                <ServerCard key={server.id} server={server} gameSlug={game.slug} />
              ))}
            </div>
          ) : (
            <p>No servers yet.</p>
          )}
        </section>
      )}
    </div>
  );
}
