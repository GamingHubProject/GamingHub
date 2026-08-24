import { useQuery } from '@tanstack/react-query';
import { useApi } from '../providers/ApiClientProvider';
import { useAuth } from '../providers/AuthProvider';
import { GameCard } from '../components/GameCard';
import { PageLayoutEditor } from '../components/PageLayoutEditor';
import type { Game } from '../api/types';

export function Portal() {
  const api = useApi();
  const { user } = useAuth();
  const { data: games, isLoading } = useQuery({
    queryKey: ['games'],
    queryFn: () => api.get<Game[]>('/api/v1/games'),
  });

  return (
    <div>
      <PageLayoutEditor
        layoutUrl="/api/v1/home/layout"
        queryKey={['page-layout', 'home']}
        context={{ subjectType: 'home' }}
        isAdmin={user?.is_admin ?? false}
      />

      <h1>Welcome to Gaming Hub</h1>
      {isLoading ? (
        <p>Loading games…</p>
      ) : (
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(220px, 1fr))', gap: 16 }}>
          {games?.map((game) => (
            <GameCard key={game.id} game={game} />
          ))}
        </div>
      )}
    </div>
  );
}
