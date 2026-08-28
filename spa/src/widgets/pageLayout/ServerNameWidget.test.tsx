import { describe, expect, it } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ApiClientProvider } from '../../providers/ApiClientProvider';
import { ServerNameWidget, serverNameWidgetDefaultConfig } from './ServerNameWidget';
import type { PageLayoutWidgetContext } from './registry';
import type { Server } from '../../api/types';

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
  cpu_percent: null,
  memory_current: null,
  memory_limit: null,
  memory_percent: null,
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

const context: PageLayoutWidgetContext = { subjectType: 'server', server };

// ServerNameWidget now falls back to fetching config.server_id when
// context.server isn't set (see the component's docblock) — every render
// needs a QueryClient in scope even when that fetch is disabled, since
// useQuery itself requires one regardless of its `enabled` value.
function renderWidget(props: Omit<Parameters<typeof ServerNameWidget>[0], 'context'> & { context?: PageLayoutWidgetContext }, apiClient: { get: (path: string) => Promise<unknown> } = { get: async () => { throw new Error('should not fetch'); } }) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });

  return render(
    <QueryClientProvider client={queryClient}>
      <ApiClientProvider client={apiClient as any}>
        <ServerNameWidget context={context} {...props} />
      </ApiClientProvider>
    </QueryClientProvider>
  );
}

describe('ServerNameWidget', () => {
  it('renders the server name at the configured font size', () => {
    renderWidget({ config: serverNameWidgetDefaultConfig });

    expect(screen.getByText('ad')).toHaveStyle({ fontSize: '24' });
  });

  it('does not apply the configured color or a text-shadow when not layered', () => {
    const config = { ...serverNameWidgetDefaultConfig, text_color: '#ff0000' };
    renderWidget({ config });

    const heading = screen.getByText('ad');
    expect(heading).not.toHaveStyle({ color: '#ff0000' });
    expect(heading.style.textShadow).toBe('');
  });

  it('applies the configured color and a text-shadow when layered', () => {
    const config = { ...serverNameWidgetDefaultConfig, text_color: '#ff0000' };
    renderWidget({ config, layered: true });

    const heading = screen.getByText('ad');
    expect(heading).toHaveStyle({ color: '#ff0000', textShadow: '0 1px 3px rgba(0, 0, 0, 0.8)' });
  });

  it('shows a placeholder when there is no context.server and no server_id configured (e.g. dropped on Home)', () => {
    const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
    render(
      <QueryClientProvider client={queryClient}>
        <ApiClientProvider client={{ get: async () => { throw new Error('should not fetch'); } } as any}>
          <ServerNameWidget context={{ subjectType: 'home' }} config={serverNameWidgetDefaultConfig} />
        </ApiClientProvider>
      </QueryClientProvider>
    );

    expect(screen.getByText('No server selected yet.')).toBeInTheDocument();
  });

  it('fetches and renders the configured server_id when there is no context.server', async () => {
    const config = { ...serverNameWidgetDefaultConfig, server_id: 2 };
    const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
    render(
      <QueryClientProvider client={queryClient}>
        <ApiClientProvider client={{ get: async () => server } as any}>
          <ServerNameWidget context={{ subjectType: 'home' }} config={config} />
        </ApiClientProvider>
      </QueryClientProvider>
    );

    await waitFor(() => expect(screen.getByText('ad')).toBeInTheDocument());
  });

  it('prefers context.server over a configured server_id when both are present', () => {
    const config = { ...serverNameWidgetDefaultConfig, server_id: 999 };
    renderWidget({ config });

    expect(screen.getByText('ad')).toBeInTheDocument();
  });
});
