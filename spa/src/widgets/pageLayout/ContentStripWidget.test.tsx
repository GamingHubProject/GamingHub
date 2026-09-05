import { describe, expect, it } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ApiClientProvider } from '../../providers/ApiClientProvider';
import { ContentStripWidget, contentStripWidgetDefaultConfig } from './ContentStripWidget';
import type { ContentStripWidgetConfig } from './ContentStripWidget';

const GAMES = [
  { id: 1, name: 'Ark', slug: 'ark', description: null, icon_url: null, has_servers: true, servers_count: 2 },
  { id: 2, name: 'Rust', slug: 'rust', description: null, icon_url: null, has_servers: true, servers_count: 1 },
];

const SERVERS = [{ id: 9, name: 'Ragnarok', status: 'running', max_players: 40, current_players: 3 }];

function renderStrip(overrides: Partial<ContentStripWidgetConfig> = {}) {
  const config = { ...contentStripWidgetDefaultConfig, ...overrides };
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  const client = {
    get: async (path: string) => {
      if (path.includes('/servers')) return SERVERS;
      if (path.includes('/games')) return GAMES;
      return [];
    },
  };

  return render(
    <QueryClientProvider client={queryClient}>
      <ApiClientProvider client={client as any}>
        <MemoryRouter>
          <ContentStripWidget config={config} />
        </MemoryRouter>
      </ApiClientProvider>
    </QueryClientProvider>
  );
}

describe('ContentStripWidget', () => {
  it('shows every game, reusing the same card the grids use', async () => {
    renderStrip({ source: 'games' });

    await waitFor(() => expect(screen.getByText('Ark')).toBeInTheDocument());
    expect(screen.getByText('Rust')).toBeInTheDocument();
  });

  it("shows one game's servers when pointed at it", async () => {
    renderStrip({ source: 'servers', game_slug: 'ark' });

    await waitFor(() => expect(screen.getByText('Ragnarok')).toBeInTheDocument());
  });

  it('says so rather than rendering an empty row when there is nothing', async () => {
    renderStrip({ source: 'custom', items: [] });

    await waitFor(() => expect(screen.getByText(/nothing to show/i)).toBeInTheDocument());
  });

  it('renders chosen items with their links', async () => {
    renderStrip({
      source: 'custom',
      items: [{ title: 'Rules', image_asset_id: null, image_url: null, url: '/rules' }],
    });

    await waitFor(() => expect(screen.getByRole('link', { name: /Rules/ })).toHaveAttribute('href', '/rules'));
  });

  it('opens an off-site item in a new tab', async () => {
    renderStrip({
      source: 'custom',
      items: [{ title: 'Discord', image_asset_id: null, image_url: null, url: 'https://discord.gg/x' }],
    });

    await waitFor(() => expect(screen.getByRole('link', { name: /Discord/ })).toHaveAttribute('target', '_blank'));
  });

  it('renders an item with no link as plain content rather than a dead anchor', async () => {
    renderStrip({
      source: 'custom',
      items: [{ title: 'Coming soon', image_asset_id: null, image_url: null, url: '' }],
    });

    await waitFor(() => expect(screen.getByText('Coming soon')).toBeInTheDocument());
    expect(screen.queryByRole('link')).not.toBeInTheDocument();
  });

  it('scrolls sideways rather than squashing its cards', async () => {
    // Letting ten cards shrink to fit turns a strip into ten slivers,
    // which is the one thing a strip exists to avoid.
    const { container } = renderStrip({ source: 'games', card_width: 240 });

    await waitFor(() => expect(screen.getByText('Ark')).toBeInTheDocument());
    const row = container.querySelector('div[style*="overflow-x"]') as HTMLElement;
    expect(row.style.overflowX).toBe('auto');
    const card = row.firstElementChild as HTMLElement;
    expect(card.style.flex).toContain('240px');
  });

  it('shows an optional heading above the row', async () => {
    renderStrip({ source: 'games', heading: 'Featured' });

    await waitFor(() => expect(screen.getByRole('heading', { name: 'Featured' })).toBeInTheDocument());
  });

  it('survives a config missing its fields rather than taking the page down', async () => {
    // Widget configs are opaque blobs an older build, a hand edit or an
    // import can write. A missing `items` used to throw inside render,
    // which the error boundary turned into a blank page.
    const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
    const client = { get: async () => GAMES };

    render(
      <QueryClientProvider client={queryClient}>
        <ApiClientProvider client={client as any}>
          <MemoryRouter>
            <ContentStripWidget config={{ heading: 'Partial' } as ContentStripWidgetConfig} />
          </MemoryRouter>
        </ApiClientProvider>
      </QueryClientProvider>
    );

    // Falls back to the default source rather than erroring.
    await waitFor(() => expect(screen.getByText('Ark')).toBeInTheDocument());
  });
});
