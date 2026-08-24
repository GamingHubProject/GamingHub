import { describe, expect, it } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ApiClientProvider } from '../providers/ApiClientProvider';
import { AuthProvider } from '../providers/AuthProvider';
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
});
