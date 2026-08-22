import { createBrowserRouter } from 'react-router-dom';
import { Layout } from '../layout/Layout';
import { Portal } from '../pages/Portal';
import { GamesList } from '../pages/GamesList';
import { GameDetail } from '../pages/GameDetail';
import { ServerDetail } from '../pages/ServerDetail';
import { Dashboard } from '../pages/Dashboard';
import { Login } from '../pages/Login';
import { AdminPlaceholder } from '../pages/AdminPlaceholder';
import { WebTreePage } from '../pages/WebTreePage';

export const router = createBrowserRouter(
  [
    {
      path: '/',
      element: <Layout />,
      children: [
        { index: true, element: <Portal /> },
        { path: 'games', element: <GamesList /> },
        { path: 'games/:slug', element: <GameDetail /> },
        { path: 'games/:slug/servers/:id', element: <ServerDetail /> },
        { path: 'dashboard', element: <Dashboard /> },
        { path: 'login', element: <Login /> },
        // The full admin panel is Filament, at /admin/system (a separate
        // app outside the SPA) — this is just a placeholder until React
        // Admin's real content is built.
        { path: 'admin', element: <AdminPlaceholder /> },
        // Bare catch-all, not "pages/*": mirrors the arbitrary top-level
        // paths Web Tree pages have always used (e.g. "games/ark/ragnarok"),
        // matching the old server-side Blade catch-all this route replaced.
        // react-router ranks static routes above a splat automatically, so
        // this only catches what nothing above it matched.
        { path: '*', element: <WebTreePage /> },
      ],
    },
  ],
  // Vite sets BASE_URL from vite.config.ts's "base" — "/" in dev (its own
  // origin/port), "/ui/" in a production build served under the Laravel
  // app's own domain. Reading it here instead of hardcoding keeps this in
  // sync with the build config automatically.
  { basename: import.meta.env.BASE_URL }
);
