// Modül ekranlarını WordPress admin sayfasına gömen bundle (IIFE: window.FluxModules).
// Varsayılan tek dosya: dist/flux-modules.js + dist/flux-modules.css. Eklenti bunları assets/flux/
// altına kopyalar (tools/fluxapp/embed-sync.sh) ve klasik <script> ile FluxModules.mount(...) çağırır.
// FLUX_EMBED_CHUNKS=1: ES parçaları, adları flux-<modül>-<hash>.js, import adresi betiğin klasörüne göredir.
// CSS, WP admin stillerine karışmasın diye .flux-root önekiyle derlenir.
import { defineConfig, type UserConfig } from 'vite';
import react from '@vitejs/plugin-react';
import prefixer from 'postcss-prefix-selector';

const split = process.env.FLUX_EMBED_CHUNKS === '1';

export default defineConfig(({ command }) => ({
  resolve: { dedupe: ['react', 'react-dom', '@tanstack/react-query'] },
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
  base: split ? './' : '/',
  build: split ? splitBuild() : singleBuild(),
}));

/** Bugünkü kuyruk: tek IIFE. Lib çıktısı kodu bölmez; dinamik import'lar dosyanın içinde kalır. */
function singleBuild(): UserConfig['build'] {
  return {
    lib: { entry: 'src/index.tsx', name: 'FluxModules', formats: ['iife'], fileName: () => 'flux-modules.js' },
    cssCodeSplit: false,
    rollupOptions: { output: { assetFileNames: 'flux-modules[extname]' } },
    target: 'es2020',
  };
}

/** Parçalar betiğin yanına düşer (./flux-tasks-<hash>.js). PHP type=module ve kopya adımı ister. */
function splitBuild(): UserConfig['build'] {
  return {
    cssCodeSplit: false,
    modulePreload: false,
    assetsInlineLimit: 100_000,
    target: 'es2020',
    rollupOptions: {
      input: 'src/index.tsx',
      output: {
        format: 'es',
        entryFileNames: 'flux-modules.js',
        chunkFileNames: (info) => {
          const id = info.facadeModuleId ?? '';
          const mod = /\/modules\/src\/([^/]+)\//.exec(id)?.[1];
          return `flux-${mod || info.name}-[hash].js`;
        },
        assetFileNames: 'flux-modules[extname]',
      },
    },
  };
}
