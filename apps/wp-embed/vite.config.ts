// Modül ekranlarını WordPress admin sayfasına gömen tek dosyalık bundle (IIFE: window.FluxModules).
// Çıktı: dist/flux-modules.js + dist/flux-modules.css. Eklenti bunları assets/flux/ altına kopyalar
// (tools/fluxapp/embed-sync.sh) ve admin sayfasında FluxModules.mount(...) çağırır.
// CSS, WP admin stillerine karışmasın diye .flux-root önekiyle derlenir.
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import prefixer from 'postcss-prefix-selector';

export default defineConfig(({ command }) => ({
  plugins: [react()],
  define: command === 'build' ? { 'process.env.NODE_ENV': '"production"' } : {},
  css: {
    postcss: {
      plugins: [
        prefixer({
          prefix: '.flux-root',
          ignoreFiles: [/index\.html/],
          transform: (prefix: string, selector: string, prefixed: string) => {
            if (/^(html|body|:root|#root)\b/.test(selector)) return selector.replace(/^(html|body|:root|#root)(\.[\w-]+)*/, prefix);
            if (selector.startsWith('.flux-root')) return selector;
            if (selector === '*' || selector.startsWith('*')) return `${prefix} ${selector}, ${prefix}`;
            return prefixed;
          },
        }),
      ],
    },
  },
  build: {
    lib: { entry: 'src/index.tsx', name: 'FluxModules', formats: ['iife'], fileName: () => 'flux-modules.js' },
    cssCodeSplit: false,
    rollupOptions: { output: { assetFileNames: 'flux-modules[extname]' } },
    target: 'es2020',
  },
}));
