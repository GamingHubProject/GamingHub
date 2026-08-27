import { useAuth } from '../providers/AuthProvider';
import { useThemeScope } from '../providers/ThemeProvider';
import { PageLayoutEditor } from '../components/PageLayoutEditor';

// The games grid used to be hardcoded here; it's now the seeded default
// game-card widget on Home's layout (see PageLayoutController's
// DEFAULT_WIDGETS) — a fresh install still looks the same, but from here
// on it's just a widget an admin can rearrange, add to, or remove. The
// heading stays static page chrome, not a widget itself.
export function Portal() {
  const { user } = useAuth();

  // 0 = PageLayout::SINGLETON_SUBJECT_ID — Home has no real model id of
  // its own to key a per-page font override off of.
  useThemeScope({ subjectType: 'home', subjectId: 0 });

  return (
    <div>
      <h1>Welcome to Gaming Hub</h1>

      <PageLayoutEditor
        layoutUrl="/api/v1/home/layout"
        queryKey={['page-layout', 'home']}
        context={{ subjectType: 'home' }}
        isAdmin={user?.is_admin ?? false}
      />
    </div>
  );
}
