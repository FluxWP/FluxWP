// Vite preset for a plugin's wp-admin screens (docs: packages/plugin-kit/README.md).
//
//   // vite.config.ts in the plugin (or its repository root)
//   import { fluxScreens } from '@fluxwp/plugin-kit/vite';
//   export default fluxScreens({ entry: 'screens/index.ts', outDir: 'plugins/acme-orders/assets/flux' });
//
// The entry exports `screens` (flx_admin_screen module id → lazy loader) and may export `resources`
// (lang → namespace → keys). The build:
//   - leaves React, React Query, i18next and the open @fluxwp packages out: imports of those read
//     globalThis.FluxRuntime.modules (Flux GraphQL's shared runtime, loaded once per page);
//   - writes flux-screens.js (registers the screens), flux-screens-<name>-<hash>.js chunks, flux-screens.css
//     (scoped to .flux-root like the runtime) and chunks.json {"mode":"screens"} that Flux GraphQL reads.
import { readFileSync, readdirSync, writeFileSync, existsSync, rmSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig, type Plugin, type UserConfig } from 'vite';
import react from '@vitejs/plugin-react';
import prefixer from 'postcss-prefix-selector';
import { RUNTIME_MODULES } from './modules';

const kit = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const EXPORTS = JSON.parse(readFileSync(join(kit, 'runtime-exports.json'), 'utf8')) as Record<string, string[]>;
const SHIM = '\0flux-runtime:';
const ENTRY = '\0flux-screens-entry';
/** Runtime API the bundle needs (apps/wp-embed/src/runtime.tsx RUNTIME_API). */
export const RUNTIME_API = 1;

export interface FluxScreensOptions {
  /** Module exporting `screens` (and optionally `resources`). Relative to the Vite root. */
  entry: string;
  /** Where the bundle goes: the plugin's assets/flux/ folder. Emptied of old flux-screens* files. */
  outDir: string;
  /** Extra Vite config merged over the preset. */
  vite?: UserConfig;
}

/** Specifier → runtime module key ('@fluxwp/modules' is the host part, the only part a plugin may use). */
function runtimeKey(id: string): string | null {
  if (id === '@fluxwp/modules' || id === '@fluxwp/modules/src/host') return '@fluxwp/host';
  return (RUNTIME_MODULES as readonly string[]).includes(id) ? id : null;
}

function shim(key: string): string {
  const names = EXPORTS[key] ?? [];
  const lines = [
    `const rt = globalThis.FluxRuntime;`,
    `if (!rt || rt.api < ${RUNTIME_API}) throw new Error('Flux GraphQL runtime (api ${RUNTIME_API}) is not loaded: update Flux GraphQL.');`,
    `const m = rt.modules[${JSON.stringify(key)}];`,
    `export default (m && 'default' in m) ? m.default : m;`,
    ...names.map((n) => `export const ${n} = m.${n};`),
  ];
  return lines.join('\n');
}

/** Resolves runtime modules to shims, refuses other @fluxwp packages (they are not in the runtime). */
function runtimeModules(): Plugin {
  return {
    name: 'flux-runtime-modules',
    enforce: 'pre',
    resolveId(id) {
      const key = runtimeKey(id);
      if (key) return SHIM + key;
      // The runtime already carries @fluxwp/ui's stylesheet.
      if (id.startsWith('@fluxwp/') && id.endsWith('.css')) return SHIM + 'css';
      if (id.startsWith('@fluxwp/') && !id.startsWith('@fluxwp/plugin-kit')) {
        this.error(`${id} is not part of the Flux runtime. Plugins may import: ${RUNTIME_MODULES.join(', ')}.`);
      }
      return null;
    },
    load(id) {
      if (id === SHIM + 'css') return 'export {};';
      if (id.startsWith(SHIM)) return shim(id.slice(SHIM.length));
      return null;
    },
  };
}

/** The bundle entry: register the screens with the runtime. */
function entryModule(entry: string): Plugin {
  return {
    name: 'flux-screens-entry',
    resolveId: (id) => (id === ENTRY ? ENTRY : null),
    load(id) {
      if (id !== ENTRY) return null;
      const target = JSON.stringify(resolve(entry));
      return [
        `import * as mod from ${target};`,
        `const rt = globalThis.FluxRuntime;`,
        `if (!rt || rt.api < ${RUNTIME_API}) throw new Error('Flux GraphQL runtime (api ${RUNTIME_API}) is not loaded: update Flux GraphQL.');`,
        `rt.register(mod.screens, { resources: mod.resources });`,
        `export const screens = mod.screens;`,
      ].join('\n');
    },
  };
}

/** chunks.json beside the bundle: {"mode":"screens","entry":"flux-screens.js","files":[…]}. */
function manifest(outDir: string): Plugin {
  return {
    name: 'flux-screens-manifest',
    apply: 'build',
    closeBundle() {
      const files = readdirSync(outDir).filter((f) => /^flux-screens.*\.(js|css)$/.test(f)).sort();
      const json = { mode: 'screens', api: RUNTIME_API, entry: 'flux-screens.js', files };
      writeFileSync(join(outDir, 'chunks.json'), JSON.stringify(json, null, 2) + '\n');
    },
  };
}

/** Same scoping as apps/wp-embed: every rule under .flux-root, so wp-admin styles do not mix. */
function scopeCss(prefix: string, selector: string, prefixed: string): string {
  const state = /^((?:html|body)(?:\.[\w-]+)+)(?=$|[\s>+~,*])(.*)$/.exec(selector);
  if (state) return state[2]!.trim() ? `${state[1]} ${prefix} ${state[2]!.trim()}` : `${state[1]} ${prefix}`;
  if (/^(html|body|:root|#root)\b/.test(selector)) return selector.replace(/^(html|body|:root|#root)(\.[\w-]+)*/, prefix);
  if (selector.startsWith('.flux-root')) return selector;
  if (selector === '*' || selector.startsWith('*')) return `${prefix} ${selector}, ${prefix}`;
  return prefixed;
}

export function fluxScreens(o: FluxScreensOptions): UserConfig {
  const outDir = resolve(o.outDir);
  if (existsSync(outDir)) {
    for (const f of readdirSync(outDir)) if (/^flux-screens.*\.(js|css)$/.test(f) || f === 'chunks.json') rmSync(join(outDir, f));
  }
  const base = defineConfig({
    plugins: [runtimeModules(), entryModule(o.entry), react(), manifest(outDir)],
    define: { 'process.env.NODE_ENV': '"production"' },
    css: { postcss: { plugins: [prefixer({ prefix: '.flux-root', transform: scopeCss })] } },
    base: './',
    build: {
      outDir,
      emptyOutDir: false,
      cssCodeSplit: false,
      modulePreload: false,
      assetsInlineLimit: 100_000,
      target: 'es2020',
      rollupOptions: {
        input: ENTRY,
        preserveEntrySignatures: 'strict',
        output: {
          format: 'es',
          entryFileNames: 'flux-screens.js',
          chunkFileNames: 'flux-screens-[name]-[hash].js',
          assetFileNames: 'flux-screens[extname]',
        },
      },
    },
  });
  return o.vite ? { ...base, ...o.vite, build: { ...base.build, ...o.vite.build } } : base;
}
