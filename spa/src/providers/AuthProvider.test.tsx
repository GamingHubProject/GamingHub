import { describe, expect, it } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ApiClientProvider } from './ApiClientProvider';
import { AuthProvider, useAuth } from './AuthProvider';
import { ApiError } from '../api/client';

function renderWithAuth(client: { get: (path: string) => Promise<unknown> }) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });

  function Probe() {
    const { user, isLoading } = useAuth();
    if (isLoading) return <span>loading</span>;
    return <span>{user ? `logged in as ${user.name}` : 'guest'}</span>;
  }

  return render(
    <QueryClientProvider client={queryClient}>
      <ApiClientProvider client={client as any}>
        <AuthProvider>
          <Probe />
        </AuthProvider>
      </ApiClientProvider>
    </QueryClientProvider>
  );
}

describe('AuthProvider', () => {
  it('exposes the user once /api/v1/user resolves', async () => {
    renderWithAuth({
      get: async () => ({ id: 1, name: 'Rose', email: 'rose@example.com', avatar: null, bio: null, preferences: null }),
    });

    await waitFor(() => expect(screen.getByText('logged in as Rose')).toBeInTheDocument());
  });

  it('treats a 401 as a guest, not an error', async () => {
    renderWithAuth({
      get: async () => {
        throw new ApiError('Unauthenticated.', 401, { message: 'Unauthenticated.' });
      },
    });

    await waitFor(() => expect(screen.getByText('guest')).toBeInTheDocument());
  });
});
