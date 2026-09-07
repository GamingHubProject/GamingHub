import { Link, useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { useApi } from '../providers/ApiClientProvider';
import { useAuth } from '../providers/AuthProvider';
import { ApiError } from '../api/client';
import { PageLayoutEditor } from '../components/PageLayoutEditor';
import type { Profile as ProfileData } from '../api/types';

/**
 * Somebody's profile, reached two ways: /users/{id} (canonical, survives
 * every rename) and /@{name} (pretty). Both render this — the @ route
 * resolves the name server-side and returns the same payload rather than
 * redirecting, so a link someone shared keeps showing the URL they shared.
 *
 * The layout below is the same widget grid every other page uses, with one
 * difference that matters: `canEdit` comes from the profile's own
 * `can_edit`, not from `is_admin`, because this is the first page type
 * whose subject may edit it.
 */
export function Profile({ handle }: { handle?: string }) {
  const params = useParams<{ id: string }>();
  const api = useApi();
  const { user } = useAuth();

  const byName = handle !== undefined;
  const path = byName
    ? `/api/v1/profiles/by-name/${encodeURIComponent(handle)}`
    : `/api/v1/users/${params.id}/profile`;

  const { data: profile, isLoading, error } = useQuery({
    queryKey: ['profile', byName ? `@${handle}` : params.id],
    queryFn: () => api.get<ProfileData>(path),
    retry: false,
  });

  if (isLoading) return <p>Loading…</p>;

  if (error) {
    if (error instanceof ApiError && error.status === 403) {
      return (
        <div>
          <h1>This profile is private</h1>
          <p>Only its owner can see it.</p>
        </div>
      );
    }
    if (error instanceof ApiError && error.status === 404) {
      return <p>No such profile.</p>;
    }
    return <p>Something went wrong loading this profile.</p>;
  }

  if (!profile) return null;

  const isOwner = user?.id === profile.id;

  return (
    <div>
      <div style={{ display: 'flex', alignItems: 'baseline', justifyContent: 'space-between', gap: 16, flexWrap: 'wrap' }}>
        <h1 style={{ marginBottom: 8 }}>{profile.display_name}</h1>
        {isOwner && <Link to="/profile/edit">Edit profile</Link>}
      </div>

      {!profile.profile_public && (
        <p style={{ color: 'var(--muted, #888)' }}>
          This profile is private — only you and admins can see it.
        </p>
      )}

      <PageLayoutEditor
        layoutUrl={`/api/v1/users/${profile.id}/layout`}
        queryKey={['page-layout', 'user_profile', profile.id]}
        context={{ subjectType: 'user_profile', profile }}
        canEdit={profile.can_edit}
        isAdmin={user?.is_admin ?? false}
        allowedWidgetTypes={profile.allowed_widget_types}
      />
    </div>
  );
}
