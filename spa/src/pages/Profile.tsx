import { Link, Navigate, useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { useApi } from '../providers/ApiClientProvider';
import { useAuth } from '../providers/AuthProvider';
import { useThemeScope } from '../providers/ThemeProvider';
import { ApiError } from '../api/client';
import { PageLayoutEditor } from '../components/PageLayoutEditor';
import type { Profile as ProfileData } from '../api/types';

/**
 * Somebody's profile, reached three ways:
 *   /users/{displayName}  — canonical, display-name-based
 *   /users/{id}           — legacy, redirects to the display-name URL
 *   /@{name}              — pretty shortcut via CatchAll
 *
 * The layout below is the same widget grid every other page uses, with one
 * difference that matters: `canEdit` comes from the profile's own
 * `can_edit`, not from `is_admin`, because this is the first page type
 * whose subject may edit it.
 */
export function Profile({ handle }: { handle?: string }) {
  const params = useParams<{ id: string }>();
  const api = useApi();

  const byName = handle !== undefined;
  const param = params.id ?? '';
  const isNumericId = !byName && /^\d+$/.test(param);

  const path = byName
    ? `/api/v1/profiles/by-name/${encodeURIComponent(handle)}`
    : isNumericId
      ? `/api/v1/users/${param}/profile`
      : `/api/v1/profiles/by-name/${encodeURIComponent(param)}`;

  const { data: profile, isLoading, error } = useQuery({
    queryKey: ['profile', byName ? `@${handle}` : param],
    queryFn: () => api.get<ProfileData>(path),
    retry: false,
  });

  if (isLoading) return <p>Loading…</p>;

  if (error) {
    if (error instanceof ApiError && error.status === 403) {
      return (
        <div style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', minHeight: '40vh' }}>
          <div
            style={{
              textAlign: 'center',
              padding: 'var(--space-section, 24px) var(--space-wide, 32px)',
              border: '1px solid var(--border, #ddd)',
              borderRadius: 'var(--radius, 8px)',
              background: 'var(--surface, #f5f5f5)',
              maxWidth: 400,
            }}
          >
            <h1 style={{ marginBottom: 8 }}>This profile is private</h1>
            <p style={{ margin: 0, color: 'var(--muted, #888)' }}>Only its owner can see it.</p>
          </div>
        </div>
      );
    }
    if (error instanceof ApiError && error.status === 404) {
      return <p>No such profile.</p>;
    }
    return <p>Something went wrong loading this profile.</p>;
  }

  if (!profile) return null;

  if (isNumericId) {
    return <Navigate to={`/users/${encodeURIComponent(profile.display_name)}`} replace />;
  }

  return <ProfileContent profile={profile} />;
}

function ProfileContent({ profile }: { profile: ProfileData }) {
  const { user } = useAuth();

  useThemeScope({ subjectType: 'user_profile', subjectId: profile.id });

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
