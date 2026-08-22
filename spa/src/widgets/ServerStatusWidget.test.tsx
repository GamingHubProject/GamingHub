import { describe, expect, it } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ApiClientProvider } from '../providers/ApiClientProvider';
import { ServerStatusWidget } from './ServerStatusWidget';
import type { Server } from '../api/types';

function renderWidget(client: { get: (path: string) => Promise<unknown> }, serverId: number | null) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });

  return render(
    <QueryClientProvider client={queryClient}>
      <ApiClientProvider client={client as any}>
        <ServerStatusWidget widgetId={1} config={{ server_id: serverId }} />
      </ApiClientProvider>
    </QueryClientProvider>
  );
}

const baseServer: Server = {
  id: 1,
  game_id: 1,
  server_group_id: null,
  name: 'ARK: Ragnarok',
  slug: 'ark-ragnarok',
  description: null,
  status: 'running',
  max_players: 20,
  current_players: 7,
  cpu_current: null,
  cpu_limit: null,
  cpu_percent: 42,
  memory_current: null,
  memory_limit: null,
  memory_percent: 81,
} as Server;

describe('ServerStatusWidget', () => {
  it('prompts to pick a server when unconfigured', () => {
    renderWidget({ get: async () => baseServer }, null);

    expect(screen.getByText(/no server selected/i)).toBeInTheDocument();
  });

  it('shows name, status, players, and resource usage once the server loads', async () => {
    renderWidget({ get: async () => baseServer }, 1);

    await waitFor(() => expect(screen.getByText('ARK: Ragnarok')).toBeInTheDocument());
    expect(screen.getByText('Running')).toBeInTheDocument();
    expect(screen.getByText(/players: 7\/20/i)).toBeInTheDocument();
    expect(screen.getByText('42%')).toBeInTheDocument();
    expect(screen.getByText('81%')).toBeInTheDocument();
  });

  it('colors the badge red for an offline server', async () => {
    renderWidget({ get: async () => ({ ...baseServer, status: 'offline' }) }, 1);

    await waitFor(() => expect(screen.getByText('Offline')).toBeInTheDocument());
    expect(screen.getByText('Offline')).toHaveStyle({ background: '#dc2626' });
  });
});
