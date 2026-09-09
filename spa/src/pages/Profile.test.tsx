import { describe, expect, it } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter, Routes, Route } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ApiClientProvider } from '../providers/ApiClientProvider';
import { AuthProvider } from '../providers/AuthProvider';
import { ApiError } from '../api/client';
// Registers the real page-layout widget types (side effect).
import '../widgets/pageLayout';
import { CatchAll } from './CatchAll';
import { Profile } from './Profile';
import type { PageLayout, Profile as ProfileData, User } from '../api/types';

const owner: User = {
  id: 7,
  name: 'Rose Account',
  email: 'rose@example.com',
  display_name: 'Rose',
  avatar: null,
  avatar_asset_id: null,
  avatar_url: null,
  bio: null,
  profile_public: true,
  profile_theme: null,
  profile_themes_enabled: true,
  preferences: null,
  is_admin: false,
};

const profile: ProfileData = {
  id: 7,
  display_name: 'Rose',
  avatar_url: null,
  bio: 'Hello there',
  profile_public: true,
  can_edit: false,
  allowed_widget_types: ['profile-avatar', 'profile-bio'],
  stats: [],
  achievements: [],
};

const layout: PageLayout = {
  id: 1,
  subject_type: 'user_profile',
  subject_id: 7,
  font_asset_id: null,
  font_url: null,
  widgets: [
    { id: 1, page_layout_id: 1, group_widget_id: null, widget_type: 'profile-bio', config: {}, position_x: 0, position_y: 0, width: 6, height: 3 },
  ],
} as unknown as PageLayout;

function renderAt(
  entry: string,
  handlers: Record<string, unknown | (() => never)>,
  { user = null as User | null } = {}
) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });

  const client = {
    get: async (path: string) => {
      if (path === '/api/v1/user') {
        if (!user) throw new ApiError('Unauthenticated.', 401, null);
        return user;
      }
      const handler = handlers[path];
      if (handler === undefined) throw new ApiError('Not Found', 404, null);
      if (typeof handler === 'function') return (handler as () => never)();
      return handler;
    },
    post: async () => { throw new Error('post not expected'); },
    patch: async () => { throw new Error('patch not expected'); },
    delete: async () => { throw new Error('delete not expected'); },
  };

  return render(
    <QueryClientProvider client={queryClient}>
      <ApiClientProvider client={client as any}>
        <AuthProvider>
          <MemoryRouter initialEntries={[entry]}>
            <Routes>
              <Route path="/users/:id" element={<Profile />} />
              <Route path="*" element={<CatchAll />} />
            </Routes>
          </MemoryRouter>
        </AuthProvider>
      </ApiClientProvider>
    </QueryClientProvider>
  );
}

describe('Profile', () => {
  it('renders somebody profile at its canonical display-name url', async () => {
    renderAt('/users/Rose', {
      '/api/v1/profiles/by-name/Rose': profile,
      '/api/v1/users/7/layout': layout,
    });

    expect(await screen.findByRole('heading', { name: 'Rose' })).toBeInTheDocument();
    expect(await screen.findByText('Hello there')).toBeInTheDocument();
  });

  it('redirects a numeric /users/{id} to the display-name url', async () => {
    renderAt('/users/7', {
      '/api/v1/users/7/profile': profile,
      '/api/v1/profiles/by-name/Rose': profile,
      '/api/v1/users/7/layout': layout,
    });

    expect(await screen.findByRole('heading', { name: 'Rose' })).toBeInTheDocument();
  });

  /**
   * The '@' door is served from the catch-all, which is what keeps Web
   * Tree pages working: a bare ':name' route would rank above the splat
   * and swallow every single-segment page on the site.
   */
  it('serves the same profile from the /@name door', async () => {
    renderAt('/@Rose', {
      '/api/v1/profiles/by-name/Rose': profile,
      '/api/v1/users/7/layout': layout,
    });

    expect(await screen.findByRole('heading', { name: 'Rose' })).toBeInTheDocument();
    expect(await screen.findByText('Hello there')).toBeInTheDocument();
  });

  it('still hands anything without an @ to the Web Tree', async () => {
    renderAt('/rules', {
      '/api/v1/pages/rules': { id: 3, title: 'The rules', path: 'rules', content: 'Be nice.' },
    });

    expect(await screen.findByRole('heading', { name: 'The rules' })).toBeInTheDocument();
  });

  it('explains a private profile rather than pretending it does not exist', async () => {
    renderAt('/users/7', {
      '/api/v1/users/7/profile': () => {
        throw new ApiError('This profile is private.', 403, null);
      },
    });

    expect(await screen.findByRole('heading', { name: /private/i })).toBeInTheDocument();
  });

  it('says plainly when there is no such profile', async () => {
    renderAt('/users/404', {});

    expect(await screen.findByText('No such profile.')).toBeInTheDocument();
  });

  it('offers the edit link only to the person whose profile it is', async () => {
    renderAt('/users/Rose', {
      '/api/v1/profiles/by-name/Rose': { ...profile, can_edit: true },
      '/api/v1/users/7/layout': layout,
    }, { user: owner });

    expect(await screen.findByRole('link', { name: 'Edit profile' })).toBeInTheDocument();
  });

  it('shows no edit link to a passer-by', async () => {
    renderAt('/users/Rose', {
      '/api/v1/profiles/by-name/Rose': profile,
      '/api/v1/users/7/layout': layout,
    });

    await screen.findByRole('heading', { name: 'Rose' });
    expect(screen.queryByRole('link', { name: 'Edit profile' })).not.toBeInTheDocument();
  });

  /**
   * The one page whose subject may edit it — so the editor's controls
   * follow the profile's own can_edit, not the viewer's admin role.
   */
  it('gives the owner the layout editor without making them an admin', async () => {
    renderAt('/users/Rose', {
      '/api/v1/profiles/by-name/Rose': { ...profile, can_edit: true },
      '/api/v1/users/7/layout': layout,
    }, { user: owner });

    expect(await screen.findByRole('button', { name: 'Edit layout' })).toBeInTheDocument();
  });

  it('gives a passer-by no way into the editor', async () => {
    renderAt('/users/Rose', {
      '/api/v1/profiles/by-name/Rose': profile,
      '/api/v1/users/7/layout': layout,
    });

    await screen.findByRole('heading', { name: 'Rose' });
    await waitFor(() => expect(screen.queryByRole('button', { name: 'Edit layout' })).not.toBeInTheDocument());
  });

  /**
   * Policy applies to what is already there, not just to what can be
   * added next — an admin withdrawing a widget type means "not on
   * profiles". Nothing is deleted; re-enabling the type brings it back.
   */
  it('stops rendering a placed widget whose type the admin has withdrawn', async () => {
    renderAt('/users/Rose', {
      '/api/v1/profiles/by-name/Rose': { ...profile, allowed_widget_types: ['profile-avatar'] },
      '/api/v1/users/7/layout': layout,
    });

    await screen.findByRole('heading', { name: 'Rose' });
    await waitFor(() => expect(screen.queryByText('Hello there')).not.toBeInTheDocument());
  });

  it('keeps rendering it while the allowlist still names it', async () => {
    renderAt('/users/Rose', {
      '/api/v1/profiles/by-name/Rose': profile,
      '/api/v1/users/7/layout': layout,
    });

    expect(await screen.findByText('Hello there')).toBeInTheDocument();
  });

  it('tells the owner of a closed profile that it is closed', async () => {
    renderAt('/users/Rose', {
      '/api/v1/profiles/by-name/Rose': { ...profile, profile_public: false, can_edit: true },
      '/api/v1/users/7/layout': layout,
    }, { user: owner });

    expect(await screen.findByText(/only you and admins can see it/i)).toBeInTheDocument();
  });
});
