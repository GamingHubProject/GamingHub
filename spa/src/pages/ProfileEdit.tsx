import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useMutation } from '@tanstack/react-query';
import { useApi } from '../providers/ApiClientProvider';
import { useAuth } from '../providers/AuthProvider';
import { ApiError } from '../api/client';
import { AvatarPicker } from '../components/AvatarPicker';
import { MarkdownField } from '../components/RichText';
import { useAccountPlacementPreference } from '../layout/useAccountPlacement';
import type { User } from '../api/types';

/**
 * The signed-in visitor's own profile settings. Everything here writes to
 * PATCH /user/profile, whose only reachable row is the requester's own —
 * an admin editing somebody else does it in Filament, not through a
 * second "edit anyone" surface built before anything needed it.
 *
 * The account-menu placement preference from v0.1.022.00 lives here too:
 * it was only reachable from inside the menu itself, which is a fine
 * shortcut and a poor home for a setting.
 */
export function ProfileEdit() {
  const api = useApi();
  const navigate = useNavigate();
  const { user, isLoading, refetch } = useAuth();

  const [displayName, setDisplayName] = useState('');
  const [bio, setBio] = useState('');
  const [isPublic, setIsPublic] = useState(true);
  const [avatarAssetId, setAvatarAssetId] = useState<number | null>(null);
  const [avatarUrl, setAvatarUrl] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);

  const placement = useAccountPlacementPreference();

  // Seeded once the signed-in user actually arrives — this page can render
  // before AuthProvider's query resolves, and initialising state from a
  // user that isn't there yet would leave every field permanently blank.
  useEffect(() => {
    if (!user) return;
    setDisplayName(user.display_name ?? '');
    setBio(user.bio ?? '');
    setIsPublic(user.profile_public);
    setAvatarAssetId(user.avatar_asset_id);
    setAvatarUrl(user.avatar_url);
  }, [user]);

  const save = useMutation({
    mutationFn: () =>
      api.patch<User>('/api/v1/user/profile', {
        display_name: displayName.trim() === '' ? null : displayName.trim(),
        bio: bio.trim() === '' ? null : bio,
        profile_public: isPublic,
        avatar_asset_id: avatarAssetId,
      }),
    onSuccess: async () => {
      setError(null);
      setSaved(true);
      await refetch();
    },
    onError: (caught) => {
      setSaved(false);
      if (caught instanceof ApiError && caught.status === 422) {
        const body = caught.body as { message?: string; errors?: Record<string, string[]> } | null;
        const first = body?.errors ? Object.values(body.errors)[0]?.[0] : undefined;
        setError(first ?? body?.message ?? 'Some of that could not be saved.');
        return;
      }
      setError('Something went wrong saving your profile.');
    },
  });

  if (isLoading) return <p>Loading…</p>;
  if (!user) return <p>You need to be signed in to edit your profile.</p>;

  return (
    <div style={{ maxWidth: 720 }}>
      <h1>Your profile</h1>

      <form
        onSubmit={(event) => {
          event.preventDefault();
          save.mutate();
        }}
        style={{ display: 'flex', flexDirection: 'column', gap: 20 }}
      >
        <label>
          <div>Picture</div>
          <div style={{ marginTop: 6 }}>
            <AvatarPicker
              userId={user.id}
              currentUrl={avatarUrl}
              onChange={(assetId, url) => {
                setAvatarAssetId(assetId);
                setAvatarUrl(url);
                setSaved(false);
              }}
            />
          </div>
        </label>

        <label>
          <div>Display name</div>
          <input
            type="text"
            value={displayName}
            maxLength={50}
            placeholder={user.name}
            onChange={(event) => {
              setDisplayName(event.target.value);
              setSaved(false);
            }}
            style={{ display: 'block', width: '100%', marginTop: 6 }}
          />
          <small style={{ color: 'var(--muted, #888)' }}>
            What people see instead of your account name, and your link:{' '}
            <code>/@{displayName.trim() || user.name}</code>. Leave it blank to use your account name.
          </small>
        </label>

        <label>
          <div>About you</div>
          <div style={{ marginTop: 6 }}>
            <MarkdownField
              value={bio}
              onChange={(html) => {
                setBio(html);
                setSaved(false);
              }}
              placeholder="Say something about yourself…"
            />
          </div>
        </label>

        <label style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
          <input
            type="checkbox"
            checked={isPublic}
            onChange={(event) => {
              setIsPublic(event.target.checked);
              setSaved(false);
            }}
          />
          Anyone can see my profile
        </label>

        <fieldset style={{ border: '1px solid var(--border, #ddd)', borderRadius: 6, padding: 12 }}>
          <legend>Account menu</legend>
          <label style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
            <input
              type="checkbox"
              checked={placement.value === 'sidebar'}
              disabled={placement.isSaving}
              onChange={(event) => placement.set(event.target.checked ? 'sidebar' : 'header')}
            />
            Put my account controls in the sidebar rather than the top bar
          </label>
          <small style={{ color: 'var(--muted, #888)' }}>
            Saved on your account, so it follows you between devices. Ignored while the sidebar is hidden or
            collapsed, where there is no room for it.
          </small>
        </fieldset>

        <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
          <button type="submit" disabled={save.isPending}>
            {save.isPending ? 'Saving…' : 'Save'}
          </button>
          <button type="button" onClick={() => navigate(`/users/${user.id}`)}>
            View my profile
          </button>
          {saved && <span style={{ color: 'var(--muted, #888)' }}>Saved.</span>}
          {error && <span style={{ color: 'var(--danger, #b3261e)' }}>{error}</span>}
        </div>
      </form>
    </div>
  );
}
