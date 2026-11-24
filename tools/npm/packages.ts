// Open @fluxwp packages published to npm (public). Order = dependency order.
//   dir     source package folder (its package.json gives name, version, dependencies)
//   from    for @fluxwp/host: a single file of another package, published on its own
export interface NpmPackage {
  name: string;
  dir: string;
  /** Files copied besides src/ (relative to dir). */
  extra?: string[];
  /** Bundled for Node (plugin-kit): entry → dist/<name>.js. */
  node?: Record<string, string>;
  /** npm search line. The README is tools/npm/readme/<name without scope>.md + _footer.md. */
  description: string;
  keywords: string[];
}

const COMMON = ['fluxwp', 'wordpress', 'typescript'];


export const NPM_PACKAGES: NpmPackage[] = [
  {
    name: '@fluxwp/tokens',
    dir: 'packages/tokens',
    description: 'Design tokens of FluxWP: colours (light/dark), brand, spacing, radius and type as TS values, CSS variables and React Native themes.',
    keywords: [...COMMON, 'design-tokens', 'theme', 'dark-mode', 'css-variables', 'react-native'],
  },
  {
    name: '@fluxwp/core',
    dir: 'packages/core',
    description: 'WordPress REST API client and sign-in toolkit (OAuth PKCE, application passwords, multi-site accounts, i18n) for browser, desktop and React Native.',
    keywords: [...COMMON, 'wordpress-api', 'wp-json', 'rest-api', 'rest-client', 'oauth', 'pkce', 'application-passwords', 'i18n'],
  },
  {
    name: '@fluxwp/ui',
    dir: 'packages/ui',
    description: 'React UI kit of FluxWP wp-admin screens: checkbox/switch, date picker, modal, icons, interactive charts (Chart.js); light and dark.',
    keywords: [...COMMON, 'react', 'ui', 'components', 'wp-admin', 'date-picker', 'checkbox', 'switch', 'dark-mode', 'charts'],
  },
  {
    name: '@fluxwp/graphql',
    dir: 'packages/graphql',
    extra: ['schema/full.graphql'],
    description: 'Typed GraphQL client and React hooks for WordPress (WPGraphQL / Flux GraphQL) with persisted queries, batching and preload.',
    keywords: [...COMMON, 'graphql', 'wpgraphql', 'react', 'react-query', 'persisted-queries', 'headless', 'headless-wordpress', 'graphql-client'],
  },
  {
    name: '@fluxwp/host',
    dir: 'packages/modules',
    description: 'Host context of FluxWP screens (wp-admin, desktop, mobile): useHost(), useModuleApi(), ModuleHost.',
    keywords: [...COMMON, 'react', 'wp-admin', 'context', 'hooks'],
  },
  {
    name: '@fluxwp/plugin-kit',
    dir: 'packages/plugin-kit',
    extra: ['runtime-exports.json'],
    node: { vite: 'src/vite.ts', codegen: 'src/codegen.ts', modules: 'src/modules.ts', 'flx-kit': 'bin/flx-kit.ts' },
    description: 'Build wp-admin React screens for your WordPress plugin: Vite preset, typed GraphQL codegen, persisted queries, shared runtime.',
    keywords: [...COMMON, 'wordpress-plugin', 'wp-admin', 'react', 'vite', 'vite-plugin', 'graphql', 'wpgraphql', 'codegen', 'cli', 'admin-ui'],
  },
];
