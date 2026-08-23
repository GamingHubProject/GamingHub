import { describe, expect, it } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ApiClientProvider } from '../providers/ApiClientProvider';
import { AuthProvider } from '../providers/AuthProvider';
import { Dashboard } from './Dashboard';
import type { DashboardPage, User } from '../api/types';

const user: User = { id: 1, name: 'Rose', email: 'rose@example.com', avatar: null, bio: null, preferences: null, is_admin: false };

function renderDashboard(client: { get: (path: string) => Promise<unknown>; post: (path: string, body?: unknown) => Promise<unknown> }) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });

  return render(
    <QueryClientProvider client={queryClient}>
      <ApiClientProvider client={client as any}>
        <AuthProvider>
          <Dashboard />
        </AuthProvider>
      </ApiClientProvider>
    </QueryClientProvider>
  );
}

describe('Dashboard empty state', () => {
  it('shows a Create dashboard button when the user has zero pages', async () => {
    renderDashboard({
      get: async (path: string) => (path.includes('/user') ? user : []),
      post: async () => {
        throw new Error('should not be called yet');
      },
    });

    await waitFor(() => expect(screen.getByText(/no dashboard pages yet/i)).toBeInTheDocument());
    expect(screen.getByRole('button', { name: /create dashboard/i })).toBeInTheDocument();
  });

  it('clicking Create dashboard posts a new page and shows the widget-adding UI', async () => {
    const newPage: DashboardPage = { id: 1, title: 'My Dashboard', order: 0, widgets: [] };
    let posted = false;

    renderDashboard({
      get: async (path: string) => (path.includes('/user') ? user : []),
      post: async (path: string, body?: unknown) => {
        expect(path).toBe('/api/v1/dashboard/pages');
        expect(body).toEqual({ title: 'My Dashboard' });
        posted = true;
        return newPage;
      },
    });

    await waitFor(() => expect(screen.getByRole('button', { name: /create dashboard/i })).toBeInTheDocument());
    screen.getByRole('button', { name: /create dashboard/i }).click();

    await waitFor(() => expect(screen.getByText('+ Add widget')).toBeInTheDocument());
    expect(posted).toBe(true);
  });
});
