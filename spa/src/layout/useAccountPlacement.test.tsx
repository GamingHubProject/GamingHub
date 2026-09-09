import { describe, expect, it, vi } from 'vitest';
import { renderHook } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ApiClientProvider } from '../providers/ApiClientProvider';
import { AuthProvider } from '../providers/AuthProvider';
import { ThemeProvider } from '../providers/ThemeProvider';
import { useAccountPlacement } from './useAccountPlacement';
import type { User } from '../api/types';

function wrapper(user: User | null, site: Record<string, unknown>) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  const client = {
    get: async (path: string) => {
      if (path.includes('/user')) {
        if (!user) throw Object.assign(new Error('Unauthenticated.'), { status: 401 });
        return user;
      }
      if (path.startsWith('/api/v1/theme')) {
        return { tokens: {}, font: null, widgetStyle: {}, site, branding: { name: 'Hub', tagline: null, logo_url: null } };
      }
      if (path.startsWith('/api/v1/navigation')) return [];
      return null;
    },
  };

  return ({ children }: { children: React.ReactNode }) => (
    <QueryClientProvider client={queryClient}>
      <ApiClientProvider client={client as any}>
        <ThemeProvider>
          <AuthProvider>
            <MemoryRouter>{children}</MemoryRouter>
          </AuthProvider>
        </ThemeProvider>
      </ApiClientProvider>
    </QueryClientProvider>
  );
}

const base: User = { id: 1, name: 'Rose', email: 'r@example.com', display_name: null,
  avatar: null,
  avatar_asset_id: null,
  avatar_url: null, bio: null,
  profile_public: true, preferences: null, profile_theme: null, profile_themes_enabled: true, is_admin: false };

describe('useAccountPlacement', () => {
  it('falls back to the header when nothing says otherwise', () => {
    const { result } = renderHook(() => useAccountPlacement(true), { wrapper: wrapper(base, { favicon_url: null }) });

    expect(result.current).toBe('header');
  });

  it('follows the theme when the user has expressed no preference', async () => {
    const { result } = renderHook(() => useAccountPlacement(true), {
      wrapper: wrapper(base, { favicon_url: null, account_placement: 'sidebar' }),
    });

    await vi.waitFor(() => expect(result.current).toBe('sidebar'));
  });

  it("lets a person's own preference beat the theme's default", async () => {
    const { result } = renderHook(() => useAccountPlacement(true), {
      wrapper: wrapper(
        { ...base, preferences: { account_placement: 'header' } },
        { favicon_url: null, account_placement: 'sidebar' }
      ),
    });

    // Given time for both the theme and the user to load, the user wins.
    await vi.waitFor(() => expect(result.current).toBe('header'));
  });

  it('refuses the sidebar when there is no sidebar able to host it', async () => {
    // Otherwise a preference set under one theme would hide the account
    // controls entirely under the next one.
    const { result } = renderHook(() => useAccountPlacement(false), {
      wrapper: wrapper(
        { ...base, preferences: { account_placement: 'sidebar' } },
        { favicon_url: null, account_placement: 'sidebar' }
      ),
    });

    await vi.waitFor(() => expect(result.current).toBe('header'));
  });

  it('ignores a stored value that is not a placement', async () => {
    const { result } = renderHook(() => useAccountPlacement(true), {
      wrapper: wrapper({ ...base, preferences: { account_placement: 'nonsense' } }, { favicon_url: null }),
    });

    await vi.waitFor(() => expect(result.current).toBe('header'));
  });
});
