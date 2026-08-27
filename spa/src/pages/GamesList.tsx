import { useAuth } from '../providers/AuthProvider';
import { useThemeScope } from '../providers/ThemeProvider';
import { PageLayoutEditor } from '../components/PageLayoutEditor';

// Same as Portal.tsx — the hardcoded games grid is now the seeded default
// game-card widget on this page's layout (see PageLayoutController's
// DEFAULT_WIDGETS), so a fresh install still looks the same.
export function GamesList() {
  const { user } = useAuth();

  // 0 = PageLayout::SINGLETON_SUBJECT_ID, same as Portal.tsx.
  useThemeScope({ subjectType: 'games-list', subjectId: 0 });

  return (
    <div>
      <h1>Games</h1>

      <PageLayoutEditor
        layoutUrl="/api/v1/games-list/layout"
        queryKey={['page-layout', 'games-list']}
        context={{ subjectType: 'games-list' }}
        isAdmin={user?.is_admin ?? false}
      />
    </div>
  );
}
