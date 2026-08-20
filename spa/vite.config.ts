import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';

export default defineConfig(() => ({
  plugins: [react()],
  // Both dev (own port/origin) and a production build (served same-origin
  // from the Laravel app's root — see routes/web.php's catch-all +
  // Dockerfile.prod) want "/" here.
  //
  // outDir is deliberately OUTSIDE public/ — confirmed (repeatedly, across
  // several different directory/prefix names) that when this build output
  // lives directly under public/, "php artisan serve" + PHP's built-in dev
  // server intercepts sub-path requests before they ever reach Laravel's
  // router (route closure provably never runs — verified via a direct
  // filesystem write from inside it), while a non-colliding prefix works
  // fine, and isolated minimal reproductions of the same server.php logic
  // did NOT show the issue. Root cause not fully isolated despite extensive
  // investigation; routes/web.php's catch-all instead serves files
  // explicitly from this resources/ directory, which sidesteps the problem
  // entirely regardless of mount point.
  base: '/',
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
