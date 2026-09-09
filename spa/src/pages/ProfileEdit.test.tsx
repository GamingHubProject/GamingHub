import { describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ApiClientProvider } from '../providers/ApiClientProvider';
import { AuthProvider } from '../providers/AuthProvider';
import { ApiError } from '../api/client';
import { ProfileEdit } from './ProfileEdit';
import type { User } from '../api/types';

/**
 * The editor is a lazily-loaded textarea+preview component; none of what
 * this file is testing is about it — the form's own behaviour is. Stubbing
 * it keeps these tests about the page (and fast); the editor's own
 * behaviour is exercised separately via the browser.
 */
vi.mock('../components/RichText', () => ({
  MarkdownField: ({ value, onChange }: { value: string; onChange: (markdown: string) => void }) => (
    <textarea aria-label="About you" value={value} onChange={(event) => onChange(event.target.value)} />
  ),
  Markdown: ({ markdown }: { markdown: string | null }) => <div>{markdown}</div>,
}));

const user: User = {
  id: 7,
  name: 'Rose Account',
  email: 'rose@example.com',
  display_name: null,
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

function renderEditor({
  patch = vi.fn().mockResolvedValue(user),
  currentUser = user,
}: { patch?: ReturnType<typeof vi.fn>; currentUser?: User | null } = {}) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });

  const client = {
    get: async (path: string) => {
      if (path === '/api/v1/user') {
        if (!currentUser) throw new ApiError('Unauthenticated.', 401, null);
        return currentUser;
      }
      throw new ApiError('Not Found', 404, null);
    },
    post: async () => { throw new Error('post not expected'); },
    patch,
    delete: async () => { throw new Error('delete not expected'); },
  };

  render(
    <QueryClientProvider client={queryClient}>
      <ApiClientProvider client={client as any}>
        <AuthProvider>
          <MemoryRouter>
            <ProfileEdit />
          </MemoryRouter>
        </AuthProvider>
      </ApiClientProvider>
    </QueryClientProvider>
  );

  return { patch };
}

describe('ProfileEdit', () => {
  it('fills itself from the signed-in account', async () => {
    renderEditor({ currentUser: { ...user, display_name: 'Rose', bio: 'Hi **there**', profile_public: false } });

    expect(await screen.findByDisplayValue('Rose')).toBeInTheDocument();
    expect(screen.getByLabelText('About you')).toHaveValue('Hi **there**');
    expect(screen.getByRole('checkbox', { name: /anyone can see my profile/i })).not.toBeChecked();
  });

  it('saves what was changed', async () => {
    const { patch } = renderEditor();

    const nameField = await screen.findByPlaceholderText('Rose Account');
    await userEvent.type(nameField, 'Rose');
    await userEvent.click(screen.getByRole('checkbox', { name: /anyone can see my profile/i }));
    await userEvent.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() =>
      expect(patch).toHaveBeenCalledWith('/api/v1/user/profile', {
        display_name: 'Rose',
        bio: null,
        profile_public: false,
        avatar_asset_id: null,
        profile_theme: null,
      })
    );
  });

  /** Blank means "use my account name" rather than "store an empty
   *  string", which would take the name out of circulation for everyone
   *  else while displaying as nothing. */
  it('sends a blank display name as null', async () => {
    const { patch } = renderEditor({ currentUser: { ...user, display_name: 'Rose' } });

    const nameField = await screen.findByDisplayValue('Rose');
    await userEvent.clear(nameField);
    await userEvent.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => expect(patch).toHaveBeenCalledWith('/api/v1/user/profile', expect.objectContaining({ display_name: null })));
  });

  it('shows the reason the server refused rather than a generic failure', async () => {
    const patch = vi.fn().mockRejectedValue(
      new ApiError('Unprocessable', 422, { errors: { display_name: ['That display name is already taken.'] } })
    );
    renderEditor({ patch });

    await userEvent.type(await screen.findByPlaceholderText('Rose Account'), 'Rose');
    await userEvent.click(screen.getByRole('button', { name: 'Save' }));

    expect(await screen.findByText('That display name is already taken.')).toBeInTheDocument();
  });

  it('previews the @name link as it is typed', async () => {
    renderEditor();

    await userEvent.type(await screen.findByPlaceholderText('Rose Account'), 'Rose');

    expect(screen.getByText('/@Rose')).toBeInTheDocument();
  });

  it('tells a signed-out visitor to sign in instead of rendering an empty form', async () => {
    renderEditor({ currentUser: null });

    expect(await screen.findByText(/need to be signed in/i)).toBeInTheDocument();
  });
});
