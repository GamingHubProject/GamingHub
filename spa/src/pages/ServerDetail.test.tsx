import { describe, expect, it } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter, Routes, Route } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ApiClientProvider } from '../providers/ApiClientProvider';
import { AuthProvider } from '../providers/AuthProvider';
// Registers the real 5 server-layout widget types (side effect).
import '../widgets/serverLayout';
import { ServerDetail } from './ServerDetail';
import type { Server, ServerLayout, User } from '../api/types';

const adminUser: User = { id: 1, name: 'Rose', email: 'rose@example.com', avatar: null, bio: null, preferences: null, is_admin: true };
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

function renderServerDetail(
  client: {
    get: (path: string) => Promise<unknown>;
    post?: (path: string, body?: unknown) => Promise<unknown>;
    delete?: (path: string) => Promise<unknown>;
    patch?: (path: string, body?: unknown) => Promise<unknown>;
  }
) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  const fullClient = {
    get: client.get,
    post: client.post ?? (async () => { throw new Error('post not expected'); }),
    patch: client.patch ?? (async () => { throw new Error('patch not expected'); }),
    delete: client.delete ?? (async () => { throw new Error('delete not expected'); }),
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

function get(user: User | null, layout: ServerLayout) {
  return async (path: string) => {
    if (path.includes('/user')) {
      if (!user) throw Object.assign(new Error('Unauthenticated.'), { status: 401 });
      return user;
    }
    if (path.includes('/layout')) return layout;
    if (path.includes('/servers/')) return server;
    return null;
  };
}

describe('ServerDetail', () => {
  it('renders the 6 cards read-only for a non-admin, with no edit controls', async () => {
    const layout: ServerLayout = {
      id: 1,
      server_id: 2,
      widgets: [
        { id: 1, server_layout_id: 1, widget_type: 'server-banner', config: null, position_x: 0, position_y: 0, width: 12, height: 2 },
        { id: 6, server_layout_id: 1, widget_type: 'server-name', config: null, position_x: 0, position_y: 0, width: 4, height: 1 },
        { id: 2, server_layout_id: 1, widget_type: 'server-status', config: null, position_x: 0, position_y: 2, width: 3, height: 2 },
        { id: 3, server_layout_id: 1, widget_type: 'server-metrics', config: null, position_x: 3, position_y: 2, width: 4, height: 3 },
        { id: 4, server_layout_id: 1, widget_type: 'server-player-count', config: null, position_x: 7, position_y: 2, width: 3, height: 2 },
        { id: 5, server_layout_id: 1, widget_type: 'server-allocations', config: null, position_x: 0, position_y: 5, width: 4, height: 3 },
      ],
    };

    renderServerDetail({ get: get(playerUser, layout) });

    await waitFor(() => expect(screen.getByText('ad')).toBeInTheDocument());
    expect(screen.getByText('Running')).toBeInTheDocument();
    expect(screen.getByText('CPU')).toBeInTheDocument();
    expect(screen.queryByText('Edit layout')).not.toBeInTheDocument();
    expect(screen.queryByLabelText('Remove widget')).not.toBeInTheDocument();
  });

  it('shows the Edit layout toggle for an admin, hidden for a non-admin', async () => {
    const emptyLayout: ServerLayout = { id: 1, server_id: 2, widgets: [] };

    const { unmount } = renderServerDetail({ get: get(adminUser, emptyLayout) });
    await waitFor(() => expect(screen.getByText('Edit layout')).toBeInTheDocument());
    unmount();

    renderServerDetail({ get: get(playerUser, emptyLayout) });
    await waitFor(() => expect(document.querySelector('.react-grid-layout')).toBeInTheDocument());
    expect(screen.queryByText('Edit layout')).not.toBeInTheDocument();
  });

  it('lets an admin add a card, which posts to the layout widgets endpoint', async () => {
    const emptyLayout: ServerLayout = { id: 1, server_id: 2, widgets: [] };
    let posted: { path: string; body: unknown } | null = null;

    renderServerDetail({
      get: get(adminUser, emptyLayout),
      post: async (path: string, body?: unknown) => {
        posted = { path, body };
        return { id: 99, server_layout_id: 1, widget_type: 'server-banner', config: null, position_x: 0, position_y: 0, width: 12, height: 2 };
      },
    });

    await waitFor(() => expect(screen.getByText('Edit layout')).toBeInTheDocument());
    screen.getByText('Edit layout').click();

    await waitFor(() => expect(screen.getByText('+ Add card')).toBeInTheDocument());
    screen.getByText('+ Add card').click();

    await waitFor(() => expect(screen.getByRole('button', { name: 'Add card' })).toBeInTheDocument());
    screen.getByRole('button', { name: 'Add card' }).click();

    await waitFor(() => expect(posted).not.toBeNull());
    expect(posted!.path).toBe('/api/v1/servers/2/layout/widgets');
    expect(posted!.body).toMatchObject({ widget_type: 'server-banner', width: 12, height: 2 });
  });

  it('lets an admin remove a card, which deletes it from the layout', async () => {
    const layout: ServerLayout = {
      id: 1,
      server_id: 2,
      widgets: [
        { id: 1, server_layout_id: 1, widget_type: 'server-banner', config: null, position_x: 0, position_y: 0, width: 12, height: 2 },
      ],
    };
    let deletedPath: string | null = null;

    renderServerDetail({
      get: get(adminUser, layout),
      delete: async (path: string) => {
        deletedPath = path;
      },
    });

    await waitFor(() => expect(screen.getByText('Edit layout')).toBeInTheDocument());
    screen.getByText('Edit layout').click();

    await waitFor(() => expect(screen.getByLabelText('Remove widget')).toBeInTheDocument());
    screen.getByLabelText('Remove widget').click();

    await waitFor(() => expect(deletedPath).toBe('/api/v1/server-layout-widgets/1'));
    await waitFor(() => expect(screen.queryByLabelText('Remove widget')).not.toBeInTheDocument());
  });
});
