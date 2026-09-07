import { useAuth } from '../providers/AuthProvider';
import { useSiteChrome } from '../providers/ThemeProvider';

export type AccountPlacement = 'header' | 'sidebar';

/**
 * Where this visitor's account controls belong.
 *
 * Two sources, in order: the person's own saved preference, then the
 * theme's default. Null or absent on the user means "follow the theme",
 * the same idiom the per-page font override already uses — not a separate
 * boolean, because a third state ("overriding, with the same value the
 * theme happens to have") is indistinguishable from the second and only
 * ever gets out of sync.
 *
 * `sidebarAvailable` is the floor under both. A theme with no sidebar that
 * nonetheless says 'sidebar' — or a preference set while a sidebar existed
 * and kept after the theme changed — must not put the account controls
 * somewhere the page doesn't render, which would sign the visitor out of
 * their own account menu.
 */
export function useAccountPlacement(sidebarAvailable: boolean): AccountPlacement {
  const chrome = useSiteChrome();
  const { user } = useAuth();

  const preferred = user?.preferences?.account_placement;
  const wanted =
    preferred === 'header' || preferred === 'sidebar'
      ? preferred
      : chrome.account_placement === 'sidebar'
        ? 'sidebar'
        : 'header';

  return wanted === 'sidebar' && sidebarAvailable ? 'sidebar' : 'header';
}
