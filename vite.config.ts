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
    },
  };
});
