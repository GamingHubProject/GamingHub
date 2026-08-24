import { useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { useApi } from '../providers/ApiClientProvider';
import { useAuth } from '../providers/AuthProvider';
import { useThemeScope } from '../providers/ThemeProvider';
import { PageLayoutEditor } from '../components/PageLayoutEditor';
import type { Server } from '../api/types';

export function ServerDetail() {
  const { id } = useParams<{ id: string }>();
  const api = useApi();
  const { user } = useAuth();

  const { data: server, isLoading: serverLoading } = useQuery({
    queryKey: ['server', id],
    queryFn: () => api.get<Server>(`/api/v1/servers/${id}`),
    enabled: !!id,
    refetchInterval: 30_000,
  });

  useThemeScope({ gameId: server?.game_id, serverId: server?.id });

  const isAdmin = user?.is_admin ?? false;

  if (serverLoading) return <p>Loading…</p>;
  if (!server) return <p>Server not found.</p>;

  return (
    <PageLayoutEditor
      layoutUrl={`/api/v1/servers/${id}/layout`}
      queryKey={['page-layout', 'server', id]}
      context={{ subjectType: 'server', server }}
      isAdmin={isAdmin}
    />
  );
}
