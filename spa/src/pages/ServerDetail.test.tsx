import { describe, expect, it } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter, Routes, Route } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ApiClientProvider } from '../providers/ApiClientProvider';
import { AuthProvider } from '../providers/AuthProvider';
// Registers the real page-layout widget types (side effect).
import '../widgets/pageLayout';
import { ServerDetail } from './ServerDetail';
import type { PageLayout, Server, User } from '../api/types';

const playerUser: User = { id: 2, name: 'Player', email: 'player@example.com', avatar: null, bio: null, preferences: null, is_admin: false };

const server: Server = {
  id: 2,
  game_id: 1,
  server_group_id: null,
  name: 'ad',
  slug: 'ad',
  description: null,
  status: 'running',
  max_players: null,
  current_players: null,
  cpu_current: null,
  cpu_limit: null,
  cpu_percent: 15,
  memory_current: null,
  memory_limit: null,
  memory_percent: 9,
  disk_current: null,
  disk_limit: null,
  disk_percent: null,
  network_rx: null,
  network_tx: null,
  node_name: null,
  supported_features: null,
  game_version: null,
  last_polled_at: null,
  allocations: [],
};

function renderServerDetail(client: { get: (path: string) => Promise<unknown> }) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  const fullClient = {
    get: client.get,
    post: async () => { throw new Error('post not expected'); },
    patch: async () => { throw new Error('patch not expected'); },
    delete: async () => { throw new Error('delete not expected'); },
  };

  return render(
    <QueryClientProvider client={queryClient}>
      <ApiClientProvider client={fullClient as any}>
        <AuthProvider>
          <MemoryRouter initialEntries={['/games/ark/servers/2']}>
            <Routes>
              <Route path="/games/:slug/servers/:id" element={<ServerDetail />} />
            </Routes>
          </MemoryRouter>
        </AuthProvider>
      </ApiClientProvider>
    </QueryClientProvider>
  );
}

function get(user: User | null, layout: PageLayout) {
  return async (path: string) => {
    if (path.includes('/user')) {
      if (!user) throw Object.assign(new Error('Unauthenticated.'), { status: 401 });
      return user;
    }
    if (path === `/api/v1/servers/2/layout`) return layout;
    if (path.includes('/servers/')) return server;
    return null;
  };
}

describe('ServerDetail', () => {
  it('shows Server not found when the server fetch comes back empty', async () => {
    renderServerDetail({
      get: async (path) => {
        if (path.includes('/user')) throw Object.assign(new Error('Unauthenticated.'), { status: 401 });
        if (path.includes('/servers/')) return null;
        return null;
      },
    });

    await waitFor(() => expect(screen.getByText('Server not found.')).toBeInTheDocument());
  });

  it('fetches this server\'s layout from the correct endpoint and renders its widgets', async () => {
    const layout: PageLayout = {
      id: 1,
      subject_type: 'server',
      subject_id: 2,
      widgets: [
        { id: 1, page_layout_id: 1, widget_type: 'server-name', config: null, position_x: 0, position_y: 0, width: 4, height: 1 },
      ],
    };

    renderServerDetail({ get: get(playerUser, layout) });

    await waitFor(() => expect(screen.getByText('ad')).toBeInTheDocument());
  });
});
