import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';

export default defineConfig(({ command }) => ({
  plugins: [react()],
  // Dev runs on its own port/origin ("/" is correct); a production build
  // is served same-origin from the Laravel app under /ui/ (see
  // routes/web.php's catch-all + Dockerfile.prod), so asset URLs and the
  // router's basename (read from import.meta.env.BASE_URL) both need the
  // /ui/ prefix baked in at build time.
  //
  // outDir is deliberately OUTSIDE public/, not public/ui — confirmed
  // (repeatedly, across several different directory/prefix names) that
  // when this build output lives directly under public/ with a name
  // matching the route prefix, "php artisan serve" + PHP's built-in dev
  // server intercepts every sub-path request before it ever reaches
  // Laravel's router (route closure provably never runs — verified via a
  // direct filesystem write from inside it), while the exact same route
  // under a non-colliding prefix works fine, and isolated minimal
  // reproductions of the same server.php logic did NOT show the issue.
  // Root cause not fully isolated despite extensive investigation; routes
  // /web.php's own "/ui/{path?}" now serves files explicitly from this
  // resources/ directory instead of relying on the webserver's own
  // static-file detection, which sidesteps the problem entirely.
  base: command === 'build' ? '/ui/' : '/',
  build: {
    outDir: '../resources/spa-dist',
    emptyOutDir: true,
  },
  server: {
    port: 5173,
  },
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: ['./src/test/setup.ts'],
  },
}));
