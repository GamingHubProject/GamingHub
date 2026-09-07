import { useState } from 'react';
import { useAuth } from '../providers/AuthProvider';
import { useApi } from '../providers/ApiClientProvider';
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

/**
 * The stored preference itself, and how to change it — as opposed to
 * useAccountPlacement above, which answers "where do the controls go
 * *right now*" after the theme default and the sidebar's availability
 * have had their say.
 *
 * Two surfaces set this: the account menu's own "move me" shortcut, and
 * the profile editor, where it belongs as an actual setting. Sharing the
 * hook is what keeps them writing the same key with the same refetch —
 * the placement is derived from the user, so re-reading the user is the
 * whole update.
 */
export function useAccountPlacementPreference() {
  const api = useApi();
  const { user, refetch } = useAuth();
  const [isSaving, setIsSaving] = useState(false);

  const stored = user?.preferences?.account_placement;
  const value: AccountPlacement | null = stored === 'header' || stored === 'sidebar' ? stored : null;

  async function set(next: AccountPlacement) {
    setIsSaving(true);
    try {
      await api.patch('/api/v1/user/preferences', { account_placement: next });
      await refetch();
    } finally {
      setIsSaving(false);
    }
  }

  return { value, set, isSaving };
}
