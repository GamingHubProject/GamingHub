import { describe, expect, it } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ApiClientProvider } from '../providers/ApiClientProvider';
import { AuthProvider } from '../providers/AuthProvider';
import { ThemeProvider } from '../providers/ThemeProvider';
import { Header } from './Header';
import type { User } from '../api/types';

function renderHeader(user: User | null) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  const client = {
    get: async (path: string) => {
      if (path.includes('/user')) {
        if (!user) throw Object.assign(new Error('Unauthenticated.'), { status: 401 });
        return user;
      }
      return null;
    },
  };

  return render(
    <QueryClientProvider client={queryClient}>
      <ApiClientProvider client={client as any}>
        <AuthProvider>
          <MemoryRouter>
            <Header />
          </MemoryRouter>
        </AuthProvider>
      </ApiClientProvider>
    </QueryClientProvider>
  );
}

const admin: User = { id: 1, name: 'Rose', email: 'rose@example.com', avatar: null, bio: null, preferences: null, is_admin: true };
const player: User = { id: 2, name: 'Player', email: 'player@example.com', avatar: null, bio: null, preferences: null, is_admin: false };

describe('Header', () => {
  it('does not show Assets as a top-level nav link for an admin', async () => {
    renderHeader(admin);

    await waitFor(() => expect(screen.getByText('Rose ▾')).toBeInTheDocument());
    expect(screen.queryByRole('link', { name: 'Assets' })).not.toBeInTheDocument();
  });

  it('shows Assets inside the user dropdown for an admin once opened', async () => {
    renderHeader(admin);

    await waitFor(() => expect(screen.getByText('Rose ▾')).toBeInTheDocument());
    screen.getByText('Rose ▾').click();

    await waitFor(() => expect(screen.getByRole('link', { name: 'Assets' })).toHaveAttribute('href', '/admin/assets'));
  });

  it('does not show Assets in the dropdown for a non-admin', async () => {
    renderHeader(player);

    await waitFor(() => expect(screen.getByText('Player ▾')).toBeInTheDocument());
    screen.getByText('Player ▾').click();

    await waitFor(() => expect(screen.getByRole('button', { name: 'Logout' })).toBeInTheDocument());
    expect(screen.queryByRole('link', { name: 'Assets' })).not.toBeInTheDocument();
  });

  // --- Site chrome: header transparency ---

  function renderThemedHeader(site: { header_transparent: boolean; favicon_url: string | null }) {
    const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
    const client = {
      get: async (path: string) => {
        if (path.includes('/user')) throw Object.assign(new Error('Unauthenticated.'), { status: 401 });
        if (path.startsWith('/api/v1/theme')) return { tokens: {}, font: null, widgetStyle: {}, site };
        return null;
      },
    };

    return render(
      <QueryClientProvider client={queryClient}>
        <ApiClientProvider client={client as any}>
          <ThemeProvider>
            <AuthProvider>
              <MemoryRouter>
                <Header />
              </MemoryRouter>
            </AuthProvider>
          </ThemeProvider>
        </ApiClientProvider>
      </QueryClientProvider>
    );
  }

  it('keeps its background token and bottom border by default, matching the pre-existing look', async () => {
    const { container } = renderThemedHeader({ header_transparent: false, favicon_url: null });

    await waitFor(() => expect(screen.getByRole('link', { name: 'Log in' })).toBeInTheDocument());
    // --surface is unset on a fresh install, so this resolves to
    // transparent today — the point is that it's the *token*, which a
    // theme can make opaque, not a hardcoded transparent.
    expect(container.querySelector('header')).toHaveStyle({ borderBottom: '1px solid var(--border, #ddd)' });
  });

  it('drops both the background and the bottom border when header_transparent is on', async () => {
    const { container } = renderThemedHeader({ header_transparent: true, favicon_url: null });

    await waitFor(() => expect(container.querySelector('header')).toHaveStyle({ background: 'transparent' }));
    // The divider goes too — a line floating over a background image is
    // the seam this setting exists to remove.
    expect(container.querySelector('header')).toHaveStyle({ borderBottom: 'none' });
  });
});
