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
}

export const NPM_PACKAGES: NpmPackage[] = [
  { name: '@fluxwp/tokens', dir: 'packages/tokens' },
  { name: '@fluxwp/core', dir: 'packages/core' },
  { name: '@fluxwp/ui', dir: 'packages/ui' },
  { name: '@fluxwp/graphql', dir: 'packages/graphql', extra: ['schema/full.graphql'] },
  { name: '@fluxwp/host', dir: 'packages/modules' },
  {
    name: '@fluxwp/plugin-kit',
    dir: 'packages/plugin-kit',
    extra: ['runtime-exports.json'],
    node: { vite: 'src/vite.ts', codegen: 'src/codegen.ts', modules: 'src/modules.ts', 'flx-kit': 'bin/flx-kit.ts' },
  },
];
