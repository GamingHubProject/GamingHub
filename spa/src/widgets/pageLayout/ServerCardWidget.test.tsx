import { describe, expect, it } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ApiClientProvider } from '../../providers/ApiClientProvider';
import { ServerCardWidget, serverCardWidgetDefaultConfig } from './ServerCardWidget';
import type { Server } from '../../api/types';

const server: Server = {
  id: 2,
  game_id: 1,
  game_slug: 'ark',
  server_group_id: null,
  name: 'ad',
  slug: 'ad',
  description: null,
  status: 'running',
  max_players: 32,
  current_players: 12,
  cpu_current: null,
  cpu_limit: null,
  cpu_percent: 41,
  memory_current: null,
  memory_limit: null,
  memory_percent: 55,
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

function renderWidget(config: typeof serverCardWidgetDefaultConfig, client: { get: (path: string) => Promise<unknown> }) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });

  return render(
    <QueryClientProvider client={queryClient}>
      <ApiClientProvider client={client as any}>
        <MemoryRouter>
          <ServerCardWidget config={config} />
        </MemoryRouter>
      </ApiClientProvider>
    </QueryClientProvider>
  );
}

describe('ServerCardWidget', () => {
  it('shows a placeholder when no server is configured yet', () => {
    renderWidget({ ...serverCardWidgetDefaultConfig, server_id: null }, { get: async () => { throw new Error('should not fetch'); } });

    expect(screen.getByText('No server selected yet.')).toBeInTheDocument();
  });

  it('renders the name, status, and player count by default', async () => {
    renderWidget({ ...serverCardWidgetDefaultConfig, server_id: 2 }, { get: async () => server });

    await waitFor(() => expect(screen.getByText('ad')).toBeInTheDocument());
    expect(screen.getByText('Running')).toBeInTheDocument();
    expect(screen.getByText('12/32 players')).toBeInTheDocument();
    expect(screen.queryByText('CPU')).not.toBeInTheDocument();
  });

  it('hides status and player count when their toggles are off', async () => {
    renderWidget(
      { server_id: 2, show_status: false, show_player_count: false, show_resources: false, show_icon: false, icon_asset_id: null, icon_url: null },
      { get: async () => server }
    );

    await waitFor(() => expect(screen.getByText('ad')).toBeInTheDocument());
    expect(screen.queryByText('Running')).not.toBeInTheDocument();
    expect(screen.queryByText('12/32 players')).not.toBeInTheDocument();
  });

  it('shows CPU/RAM bars when show_resources is on', async () => {
    renderWidget({ ...serverCardWidgetDefaultConfig, server_id: 2, show_resources: true }, { get: async () => server });

    await waitFor(() => expect(screen.getByText('CPU')).toBeInTheDocument());
    expect(screen.getByText('RAM')).toBeInTheDocument();
  });

  it('links to the server using the game_slug from the response', async () => {
    renderWidget({ ...serverCardWidgetDefaultConfig, server_id: 2 }, { get: async () => server });

    await waitFor(() => expect(screen.getByRole('link')).toHaveAttribute('href', '/games/ark/servers/2'));
  });
});
