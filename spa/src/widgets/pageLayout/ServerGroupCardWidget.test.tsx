import { describe, expect, it } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ApiClientProvider } from '../../providers/ApiClientProvider';
import { ServerGroupCardWidget, serverGroupCardWidgetDefaultConfig } from './ServerGroupCardWidget';
import type { ServerGroup } from '../../api/types';

const group: ServerGroup = {
  id: 3,
  game_id: 1,
  game_slug: 'ark',
  name: 'Official Servers',
  description: null,
  servers_count: 5,
  running_count: 3,
};

function renderWidget(config: typeof serverGroupCardWidgetDefaultConfig, client: { get: (path: string) => Promise<unknown> }) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });

  return render(
    <QueryClientProvider client={queryClient}>
      <ApiClientProvider client={client as any}>
        <MemoryRouter>
          <ServerGroupCardWidget config={config} />
        </MemoryRouter>
      </ApiClientProvider>
    </QueryClientProvider>
  );
}

describe('ServerGroupCardWidget', () => {
  it('shows a placeholder when no group is configured yet', () => {
    renderWidget(serverGroupCardWidgetDefaultConfig, { get: async () => { throw new Error('should not fetch'); } });

    expect(screen.getByText('No server group selected yet.')).toBeInTheDocument();
  });

  it('renders the group name and running/total fraction', async () => {
    renderWidget({ server_group_id: 3 }, { get: async () => group });

    await waitFor(() => expect(screen.getByText('Official Servers')).toBeInTheDocument());
    expect(screen.getByText('3/5 running')).toBeInTheDocument();
  });

  it('links to the parent Game Detail page using game_slug', async () => {
    renderWidget({ server_group_id: 3 }, { get: async () => group });

    await waitFor(() => expect(screen.getByRole('link')).toHaveAttribute('href', '/games/ark'));
  });
});
