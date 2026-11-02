// Masaüstü kabuğu: panelin aynısını derler, yalnız platform adaptörü farklı.
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { resolve } from 'node:path';
import brand from '../../packages/core/src/brand/current.json';

export default defineConfig({
  plugins: [
    react(),
    { name: 'flux-brand-html', transformIndexHtml: (h) => h.replaceAll('%BRAND_NAME%', brand.name) },
  ],
  publicDir: resolve(__dirname, '../../packages/panel/public'),
  base: './',
  clearScreen: false,
  server: { port: 1420, strictPort: true },
  envPrefix: ['VITE_', 'TAURI_ENV_'],
  build: { target: 'safari15', outDir: 'dist' },
});
