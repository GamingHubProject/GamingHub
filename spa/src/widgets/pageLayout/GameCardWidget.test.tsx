import { describe, expect, it } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ApiClientProvider } from '../../providers/ApiClientProvider';
import { GameCardWidget, gameCardWidgetDefaultConfig } from './GameCardWidget';
import type { Game } from '../../api/types';

const palworld: Game = { id: 1, name: 'Palworld', slug: 'palworld', description: null, icon_url: null, status: 'enabled', has_servers: true, metadata: null };
const ark: Game = { id: 2, name: 'Ark', slug: 'ark', description: null, icon_url: null, status: 'enabled', has_servers: true, metadata: null };

function renderWidget(config: typeof gameCardWidgetDefaultConfig, client: { get: (path: string) => Promise<unknown> }) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });

  return render(
    <QueryClientProvider client={queryClient}>
      <ApiClientProvider client={client as any}>
        <MemoryRouter>
          <GameCardWidget config={config} />
        </MemoryRouter>
      </ApiClientProvider>
    </QueryClientProvider>
  );
}

describe('GameCardWidget', () => {
  it("'all' mode renders every game as a grid, same as the old hardcoded markup", async () => {
    renderWidget(
      { ...gameCardWidgetDefaultConfig, mode: 'all' },
      { get: async () => [palworld, ark] }
    );

    await waitFor(() => expect(screen.getByText('Palworld')).toBeInTheDocument());
    expect(screen.getByText('Ark')).toBeInTheDocument();
  });

  it("'single' mode with no game selected shows a placeholder instead of fetching", async () => {
    renderWidget(
      { ...gameCardWidgetDefaultConfig, mode: 'single' },
      { get: async () => { throw new Error('should not fetch'); } }
    );

    expect(screen.getByText('No game selected yet.')).toBeInTheDocument();
  });

  it("'single' mode renders just the configured game", async () => {
    renderWidget(
      { mode: 'single', game_id: 1, game_slug: 'palworld', show_icon: true, icon_asset_id: null, icon_url: null },
      { get: async (path: string) => (path === '/api/v1/games/palworld' ? palworld : null) }
    );

    await waitFor(() => expect(screen.getByText('Palworld')).toBeInTheDocument());
    expect(screen.queryByText('Ark')).not.toBeInTheDocument();
  });

  it("'single' mode uses the configured icon_url override instead of the game's own icon_url", async () => {
    const { container } = renderWidget(
      { mode: 'single', game_id: 1, game_slug: 'palworld', show_icon: true, icon_asset_id: 7, icon_url: 'http://localhost/storage/override.png' },
      { get: async (path: string) => (path === '/api/v1/games/palworld' ? palworld : null) }
    );

    // alt="" gives the <img> role "presentation", not "img" (per ARIA) —
    // querying by tag rather than role here, unlike other image assertions
    // in this codebase that have real alt text to query by role with.
    await waitFor(() => expect(container.querySelector('img')).toHaveAttribute('src', 'http://localhost/storage/override.png'));
  });
});
