// Modül ekranlarını WordPress admin sayfasına gömen bundle (IIFE: window.FluxModules).
// Varsayılan tek dosya: dist/flux-modules.js + dist/flux-modules.css. Eklenti bunları assets/flux/
// altına kopyalar (tools/fluxapp/embed-sync.sh) ve klasik <script> ile FluxModules.mount(...) çağırır.
// FLUX_EMBED_RUNTIME=1: eklenti ekranları için ortak runtime (runtimeBuild).
// FLUX_EMBED_CHUNKS=1: ES parçaları, adları flux-<modül>-<hash>.js, import adresi betiğin klasörüne göredir.
// CSS, WP admin stillerine karışmasın diye .flux-root önekiyle derlenir.
import { defineConfig, type UserConfig } from 'vite';
import react from '@vitejs/plugin-react';
import prefixer from 'postcss-prefix-selector';

const runtime = process.env.FLUX_EMBED_RUNTIME === '1';
const split = runtime || process.env.FLUX_EMBED_CHUNKS === '1';

export default defineConfig(({ command }) => ({
  resolve: { dedupe: ['react', 'react-dom', '@tanstack/react-query', 'react-i18next', 'i18next'] },
  plugins: [react()],
  define: command === 'build' ? { 'process.env.NODE_ENV': '"production"' } : {},
  css: {
    postcss: {
      plugins: [
        prefixer({
          prefix: '.flux-root',
          ignoreFiles: [/index\.html/],
          transform: (prefix: string, selector: string, prefixed: string) => {
            // html.durum / body.durum: sayfa sınıfı koşulu korunur (yoksa kural hep geçerli olur), öneki ardından gelir.
            const state = /^((?:html|body)(?:\.[\w-]+)+)(?=$|[\s>+~,*])(.*)$/.exec(selector);
            if (state) return state[2]!.trim() ? `${state[1]} ${prefix} ${state[2]!.trim()}` : `${state[1]} ${prefix}`;
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
  build: runtime ? runtimeBuild() : split ? splitBuild() : singleBuild(),
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

/**
 * FLUX_EMBED_RUNTIME=1: the shared plugin runtime (src/runtime.tsx) → dist-runtime/flux-runtime.js + .css.
 * tools/fluxapp/runtime-sync.sh copies it to plugins/flx-graphql/assets/runtime/.
 */
function runtimeBuild(): UserConfig['build'] {
  return {
    outDir: 'dist-runtime',
    emptyOutDir: true,
    cssCodeSplit: false,
    modulePreload: false,
    assetsInlineLimit: 100_000,
    target: 'es2020',
    rollupOptions: {
      input: 'src/runtime.tsx',
      // Exports are the runtime API; keep them even though nothing in the bundle imports them.
      preserveEntrySignatures: 'strict',
      output: {
        format: 'es',
        entryFileNames: 'flux-runtime.js',
        chunkFileNames: 'flux-runtime-[name]-[hash].js',
        assetFileNames: 'flux-runtime[extname]',
      },
    },
  };
}
