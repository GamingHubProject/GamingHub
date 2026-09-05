import { createContext, useContext, useEffect, useRef, useState, type ReactNode } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useApi } from './ApiClientProvider';
import type { PageLayoutSubjectType, ThemeTokens } from '../api/types';
import { backgroundCss } from '../widgets/shared/background';
import type { BackgroundImageFit, BackgroundType, GradientSpec } from '../widgets/shared/background';
import type { WidgetStyleOverride } from '../widgets/shared/widgetStyle';

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

/** Settings for the shell around the pages, not for anything on them —
 *  see ThemeResolver::siteChrome. Global, like widgetStyle. */
export interface SiteChrome {
  header_transparent: boolean;
  favicon_url: string | null;
  /** The page background a theme sets. Same field names as a widget's
   *  background — both are drawn by widgets/shared/background.ts. */
  background: {
    type?: BackgroundType;
    color?: string;
    opacity?: number;
    pattern?: string;
    pattern_color?: string;
    image_url?: string;
    image_fit?: BackgroundImageFit;
    gradient?: GradientSpec;
  };
  /** Which navigation regions exist. Appearance, so it's the theme's —
   *  the links themselves are site data (see /api/v1/navigation). */
  nav_enabled: boolean;
  nav_position: 'top' | 'sidebar' | 'both';
  sidebar_behavior: 'always' | 'auto-hide' | 'toggle';
}

const EMPTY_SITE_CHROME: SiteChrome = {
  header_transparent: false,
  favicon_url: null,
  background: {},
  // Top nav, no sidebar — an install that has never touched this renders
  // exactly as it always did.
  nav_enabled: true,
  nav_position: 'top',
  sidebar_behavior: 'always',
};

interface ThemeResponse {
  tokens: ThemeTokens;
  font: ThemeFont | null;
  // Purely global (see ThemeResolver::widgetStyleDefaults's docblock) —
  // present in every response regardless of scope, unlike tokens/font.
  widgetStyle: Partial<WidgetStyleOverride>;
  site: SiteChrome;
}

interface ThemeContextValue {
  setScope: (scope: ThemeScope) => void;
  widgetStyleDefaults: Partial<WidgetStyleOverride>;
  siteChrome: SiteChrome;
}

const ThemeContext = createContext<ThemeContextValue>({
  setScope: () => {},
  widgetStyleDefaults: {},
  siteChrome: EMPTY_SITE_CHROME,
});

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

    // Tokens on :root do nothing on their own — something has to actually
    // paint with them, and the page ground is the one surface no component
    // owns. Without this a dark theme sets --background and the page stays
    // white, with dark-theme text on it.
    //
    // Applied only when the theme defines them, so an install with no
    // tokens set keeps the browser's own default rather than being forced
    // to black or white by a half-configured theme.
    if (theme.tokens.background) document.body.style.background = 'var(--background)';
    if (theme.tokens.text) document.body.style.color = 'var(--text)';
  }, [theme]);

  // The page background a theme sets — a pattern, gradient or image behind
  // everything, over the --background token. Painted on <body> rather than
  // a wrapper element so it covers the whole viewport including any
  // overscroll area, and so `background-attachment` behaves.
  useEffect(() => {
    const background = theme?.site?.background;
    const style = document.body.style;

    // Cleared explicitly on every run: switching a theme from an image
    // back to a plain colour has to remove the old image, not just stop
    // setting a new one.
    style.backgroundImage = '';
    style.backgroundSize = '';
    style.backgroundRepeat = '';
    style.backgroundPosition = '';
    style.backgroundAttachment = '';

    if (!background?.type) return;

    const css = backgroundCss({
      type: background.type,
      color: background.color,
      opacity: background.opacity ?? 1,
      pattern: background.pattern,
      patternColor: background.pattern_color,
      imageUrl: background.image_url,
      imageFit: background.image_fit ?? 'cover',
      gradient: background.gradient,
    });

    if (css.backgroundColor) style.backgroundColor = css.backgroundColor as string;
    if (css.backgroundImage) style.backgroundImage = css.backgroundImage as string;
    if (css.backgroundSize) style.backgroundSize = css.backgroundSize as string;
    if (css.backgroundRepeat) style.backgroundRepeat = css.backgroundRepeat as string;
    if (css.backgroundPosition) style.backgroundPosition = css.backgroundPosition as string;
    // A page-scale image or gradient that scrolls away from a long page
    // is almost never what's wanted; a repeating pattern must scroll or it
    // visibly slides under the content.
    if (background.type === 'image' || background.type === 'gradient') style.backgroundAttachment = 'fixed';
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

  // SpaController already injects the configured favicon into the served
  // shell (the browser asks for one before any of this runs), so on a
  // normal page load this effect finds the same URL already in place and
  // changes nothing. It exists for the case the shell can't cover:
  // swapping the icon live when the setting changes while the SPA is
  // mounted, without a reload.
  useEffect(() => {
    const url = theme?.site?.favicon_url;
    if (!url) return;

    let link = document.querySelector<HTMLLinkElement>('link[rel="icon"]');
    if (!link) {
      link = document.createElement('link');
      link.rel = 'icon';
      document.head.appendChild(link);
    }
    if (link.href !== url) link.href = url;
  }, [theme]);

  return (
    <ThemeContext.Provider
      value={{
        setScope,
        widgetStyleDefaults: theme?.widgetStyle ?? {},
        siteChrome: theme?.site ?? EMPTY_SITE_CHROME,
      }}
    >
      {children}
    </ThemeContext.Provider>
  );
}

/** The one app-wide layer beneath every widget's own style override — see
 *  widgets/shared/widgetStyle.ts's resolveWidgetStyle. */
export function useWidgetStyleDefaults(): Partial<WidgetStyleOverride> {
  return useContext(ThemeContext).widgetStyleDefaults;
}

/** Header/favicon settings for the shell around the pages — see
 *  ThemeResolver::siteChrome. */
export function useSiteChrome(): SiteChrome {
  return useContext(ThemeContext).siteChrome;
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
