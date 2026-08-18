import { createContext, useContext, useEffect, useState, type ReactNode } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useApi } from './ApiClientProvider';
import type { ThemeTokens } from '../api/types';

interface ThemeScope {
  gameId?: number;
  serverId?: number;
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

  const { data: tokens } = useQuery({
    queryKey: ['theme', scope.gameId ?? null, scope.serverId ?? null],
    queryFn: () => {
      const params = new URLSearchParams();
      if (scope.gameId) params.set('game_id', String(scope.gameId));
      if (scope.serverId) params.set('server_id', String(scope.serverId));
      const qs = params.toString();
      return api.get<ThemeTokens>(`/api/v1/theme${qs ? `?${qs}` : ''}`);
    },
  });

  useEffect(() => {
    if (!tokens) return;
    const root = document.documentElement;
    for (const [key, value] of Object.entries(tokens)) {
      root.style.setProperty(`--${key}`, value);
    }
  }, [tokens]);

  return <ThemeContext.Provider value={{ setScope }}>{children}</ThemeContext.Provider>;
}

/**
 * Call from a page to narrow the active theme to a game/server scope while
 * mounted, reverting to platform-only on unmount.
 */
export function useThemeScope(scope: ThemeScope): void {
  const { setScope } = useContext(ThemeContext);
  const gameId = scope.gameId;
  const serverId = scope.serverId;

  useEffect(() => {
    setScope({ gameId, serverId });
    return () => setScope({});
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [gameId, serverId]);
}
