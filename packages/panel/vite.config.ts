import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import brand from '../core/src/brand/current.json';

export default defineConfig({
  plugins: [
    react(),
    {
      name: 'flux-brand-html',
      transformIndexHtml: (html) => html.replaceAll('%BRAND_NAME%', brand.name).replaceAll('%BRAND_PRIMARY%', brand.colors.primary),
    },
  ],
  base: './',
  resolve: { dedupe: ['react', 'react-dom', '@tanstack/react-query', 'react-i18next', 'i18next'] },
  server: { port: 5173, strictPort: true },
});
