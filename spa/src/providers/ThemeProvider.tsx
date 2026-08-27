import { createContext, useContext, useEffect, useRef, useState, type ReactNode } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useApi } from './ApiClientProvider';
import type { PageLayoutSubjectType, ThemeTokens } from '../api/types';

interface ThemeScope {
  gameId?: number;
  serverId?: number;
  // A separate axis from gameId/serverId — see ThemeController's docblock.
  // Only font resolution reads these; the color cascade above is
  // untouched by them.
  subjectType?: PageLayoutSubjectType;
  subjectId?: number;
}

interface ThemeFont {
  family: string;
  url: string;
}

interface ThemeResponse {
  tokens: ThemeTokens;
  font: ThemeFont | null;
}

interface ThemeContextValue {
  setScope: (scope: ThemeScope) => void;
}

const ThemeContext = createContext<ThemeContextValue>({ setScope: () => {} });

/**
 * One theme is ever "live" at a time, applied as CSS custom properties on
 * :root. Pages narrow the scope via useThemeScope() rather than nesting a
 * second ThemeProvider — the API already does platform+game+server token
 * merging server-side (ThemeResolver), so there's nothing to merge here,
 * just one fetch per scope change.
 */
export function ThemeProvider({ children }: { children: ReactNode }) {
  const api = useApi();
  const [scope, setScope] = useState<ThemeScope>({});
  // Which font families have already been loaded via the Font Loading API
  // — avoids re-fetching/re-adding the same @font-face on every scope
  // change (e.g. navigating between two pages that both sync to the same
  // global font).
  const loadedFamilies = useRef<Set<string>>(new Set());

  const { data: theme } = useQuery({
    queryKey: ['theme', scope.gameId ?? null, scope.serverId ?? null, scope.subjectType ?? null, scope.subjectId ?? null],
    queryFn: () => {
      const params = new URLSearchParams();
      if (scope.gameId) params.set('game_id', String(scope.gameId));
      if (scope.serverId) params.set('server_id', String(scope.serverId));
      if (scope.subjectType) params.set('subject_type', scope.subjectType);
      if (scope.subjectId !== undefined) params.set('subject_id', String(scope.subjectId));
      const qs = params.toString();
      return api.get<ThemeResponse>(`/api/v1/theme${qs ? `?${qs}` : ''}`);
    },
  });

  useEffect(() => {
    if (!theme) return;
    const root = document.documentElement;
    for (const [key, value] of Object.entries(theme.tokens)) {
      root.style.setProperty(`--${key}`, value);
    }
  }, [theme]);

  useEffect(() => {
    if (!theme) return;

    if (!theme.font) {
      document.documentElement.style.fontFamily = '';
      return;
    }

    const { family, url } = theme.font;
    document.documentElement.style.fontFamily = `"${family}", sans-serif`;

    if (loadedFamilies.current.has(family)) return;

    const face = new FontFace(family, `url(${url})`);
    face
      .load()
      .then((loaded) => {
        document.fonts.add(loaded);
        loadedFamilies.current.add(family);
      })
      .catch(() => {
        // A bad/unreachable font file shouldn't break the page — it just
        // falls back to the sans-serif stack already set above.
      });
  }, [theme]);

  return <ThemeContext.Provider value={{ setScope }}>{children}</ThemeContext.Provider>;
}

/**
 * Call from a page to narrow the active theme to a game/server/page scope
 * while mounted, reverting to platform-only on unmount.
 */
export function useThemeScope(scope: ThemeScope): void {
  const { setScope } = useContext(ThemeContext);
  const gameId = scope.gameId;
  const serverId = scope.serverId;
  const subjectType = scope.subjectType;
  const subjectId = scope.subjectId;

  useEffect(() => {
    setScope({ gameId, serverId, subjectType, subjectId });
    return () => setScope({});
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [gameId, serverId, subjectType, subjectId]);
}
