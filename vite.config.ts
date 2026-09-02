import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import path from 'path';
import { defineConfig } from 'vite';

// Deliberately NOT using vite-plugin-singlefile here (unlike the calendar app):
// this rewrite exists specifically to stop re-downloading ~200MB of images and
// content on every visit, so real hashed asset files + normal browser caching
// are the point, not a single inlined bundle.
export default defineConfig(() => {
  return {
    base: '/resources/',
    plugins: [react(), tailwindcss()],
    resolve: {
      alias: {
        '@': path.resolve(__dirname, 'src'),
      },
    },
    build: {
      outDir: 'dist',
      assetsDir: 'assets/build',
    },
    server: {
      port: 3001,
      host: '0.0.0.0',
      // `vite dev` has no PHP interpreter of its own, so anything hitting a .php
      // endpoint (checkAuth.php, etc.) or the /new mission tools needs forwarding
      // to the real backend — same "localhost" convention base-commander's
      // LEGACY_URL uses in dev. Without this, those requests fall through to
      // Vite's SPA fallback (index.html), which breaks AuthGate silently.
      proxy: {
        '^/resources/[^/]+\\.php$': { target: 'http://localhost', changeOrigin: true },
        '/new': { target: 'http://localhost', changeOrigin: true },
        '/mobile': { target: 'http://localhost', changeOrigin: true },
      },
    },
  };
});
