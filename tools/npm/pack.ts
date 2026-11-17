// Stages the open @fluxwp packages for npm: dist-npm/<name>/ with package.json (workspace:* → versions,
// public), README, src/ (TypeScript source for bundlers), types/ (.d.ts) and, for plugin-kit, Node bundles.
//
//   bun tools/npm/pack.ts            stage every package (after `bun install`, which generates @fluxwp/graphql)
//   bun tools/npm/pack.ts --list     name@version per line
//
// Publishing is .github/workflows/npm-publish.yml (only versions not on npm yet).
import { cpSync, existsSync, mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { spawnSync } from 'node:child_process';
import { NPM_PACKAGES, type NpmPackage } from './packages';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const outRoot = join(root, 'dist-npm');
type Pkg = Record<string, unknown> & { name: string; version: string; dependencies?: Record<string, string>; peerDependencies?: Record<string, string> };
const read = (p: string) => JSON.parse(readFileSync(p, 'utf8')) as Pkg;

/** @fluxwp/host is modules/src/host.tsx: same version as @fluxwp/modules. */
function source(p: NpmPackage): Pkg {
  const pkg = read(join(root, p.dir, 'package.json'));
  if (p.name !== '@fluxwp/host') return pkg;
  return {
    name: '@fluxwp/host',
    version: pkg.version,
    description: 'Host context of FluxWP screens: useHost(), useModuleApi(), ModuleHost.',
    dependencies: { '@fluxwp/core': 'workspace:*' },
    peerDependencies: { react: pkg.dependencies?.react ?? '19.3.0', '@tanstack/react-query': pkg.dependencies?.['@tanstack/react-query'] ?? '^5' },
  };
}

const versions = new Map(NPM_PACKAGES.map((p) => [p.name, source(p).version]));
if (process.argv.includes('--list')) {
  for (const [n, v] of versions) console.log(`${n}@${v}`);
  process.exit(0);
}

function deps(d: Record<string, string> | undefined): Record<string, string> | undefined {
  if (!d) return undefined;
  const out: Record<string, string> = {};
  for (const [k, v] of Object.entries(d)) {
    if (!v.startsWith('workspace:')) {
      out[k] = v;
      continue;
    }
    const ver = versions.get(k);
    if (!ver) throw new Error(`${k} is a workspace dependency but not published (tools/npm/packages.ts)`);
    out[k] = `^${ver}`;
  }
  return out;
}

function run(cmd: string, args: string[], cwd: string): void {
  const r = spawnSync(cmd, args, { cwd, stdio: 'inherit' });
  if (r.status !== 0) throw new Error(`${cmd} ${args.join(' ')} failed in ${cwd}`);
}

rmSync(outRoot, { recursive: true, force: true });
for (const p of NPM_PACKAGES) {
  const src = source(p);
  const out = join(outRoot, p.name.replace('/', '__'));
  const dir = join(root, p.dir);
  mkdirSync(out, { recursive: true });

  // Sources (bundlers compile them) and declarations (TypeScript reads these; tests excluded).
  const entryRel = p.name === '@fluxwp/host' ? 'src/index.tsx' : null;
  if (entryRel) {
    mkdirSync(join(out, 'src'), { recursive: true });
    cpSync(join(dir, 'src/host.tsx'), join(out, entryRel));
  } else {
    cpSync(join(dir, 'src'), join(out, 'src'), { recursive: true, filter: (f) => !/\.test\.tsx?$/.test(f) });
  }
  for (const f of p.extra ?? []) {
    if (!existsSync(join(dir, f))) throw new Error(`${p.name}: ${f} missing (run bun install first)`);
    mkdirSync(dirname(join(out, f)), { recursive: true });
    cpSync(join(dir, f), join(out, f));
  }
  // Declarations from the package's own tsconfig (same resolution as its typecheck), tests left out.
  const tsconfig = join(dir, 'tsconfig.npm.json');
  writeFileSync(
    tsconfig,
    JSON.stringify({
      extends: './tsconfig.json',
      compilerOptions: { noEmit: false, declaration: true, emitDeclarationOnly: true, outDir: join(out, 'types-all'), rootDir: '..', composite: false, incremental: false },
      include: p.name === '@fluxwp/host' ? ['src/host.tsx'] : p.node ? ['src'] : ['src'],
      exclude: ['**/*.test.ts', '**/*.test.tsx', 'example'],
    }),
  );
  try {
    run(join(root, 'node_modules/.bin/tsc'), ['-p', tsconfig], dir);
  } finally {
    rmSync(tsconfig);
  }
  // rootDir is packages/ (plugin-kit reads packages/graphql/scripts): keep this package's src/ as types/.
  const emitted = join(out, 'types-all', dir.split('/').pop()!, 'src');
  cpSync(emitted, join(out, 'types'), { recursive: true });
  rmSync(join(out, 'types-all'), { recursive: true });
  if (p.name === '@fluxwp/host') cpSync(join(out, 'types/host.d.ts'), join(out, 'types/index.d.ts'));

  // Node entry points (plugin-kit): one ES module each, dependencies left to npm.
  const exports: Record<string, unknown> = {};
  if (p.node) {
    const external = Object.keys({ ...src.dependencies, ...src.peerDependencies }).concat(['node:*']);
    for (const [name, entry] of Object.entries(p.node)) {
      const r = await Bun.build({ entrypoints: [join(dir, entry)], outdir: join(out, 'dist'), target: 'node', format: 'esm', naming: `${name}.js`, external });
      if (!r.success) throw new Error(`${p.name}: ${entry}: ${r.logs.join('\n')}`);
    }
    // The .ts sources and their declarations are for reading; the package runs from dist/.
    for (const name of Object.keys(p.node)) {
      if (name === 'flx-kit') continue;
      exports[`./${name}`] = { types: `./types/${p.node[name]!.replace(/^src\//, '').replace(/\.ts$/, '.d.ts')}`, default: `./dist/${name}.js` };
    }
    for (const f of p.extra ?? []) exports[`./${f}`] = `./${f}`;
    const bin = join(out, 'dist/flx-kit.js');
    if (existsSync(bin)) writeFileSync(bin, '#!/usr/bin/env node\n' + readFileSync(bin, 'utf8').replace(/^#!.*\n/, ''));
  } else {
    const main = entryRel ?? (existsSync(join(out, 'src/index.ts')) ? 'src/index.ts' : 'src/index.tsx');
    const sub = (src.exports && typeof src.exports === 'object' ? (src.exports as Record<string, string>) : { '.': `./${main}` });
    for (const [k, v] of Object.entries(sub)) {
      const rel = v.replace(/^\.\//, '');
      if (!rel.startsWith('src/')) continue;
      exports[k] = { types: './types/' + rel.slice(4).replace(/\.tsx?$/, '.d.ts'), default: `./${rel}` };
    }
    if (!sub['.'] && !exports['.']) exports['.'] = { types: './types/' + main.slice(4).replace(/\.tsx?$/, '.d.ts'), default: `./${main}` };
    for (const f of p.extra ?? []) exports[`./${f}`] = `./${f}`;
    if (p.name === '@fluxwp/ui') exports['./styles.css'] = './src/styles.css';
  }

  // A plugin project installs only @fluxwp/plugin-kit: the type packages its screens import come with it.
  const runtimeDeps = p.name === '@fluxwp/plugin-kit' ? { '@fluxwp/host': `^${versions.get('@fluxwp/host')}` } : {};
  const pkg: Record<string, unknown> = {
    name: p.name,
    version: src.version,
    description: src.description ?? `${p.name} (FluxWP)`,
    type: 'module',
    license: 'GPL-2.0-or-later',
    homepage: 'https://fluxwp.com',
    repository: { type: 'git', url: 'git+https://github.com/FluxWP/FluxWP.git', directory: p.dir },
    bugs: { email: 'info@fluxwp.com' },
    exports,
    ...(p.node ? { bin: { 'flx-kit': './dist/flx-kit.js' } } : { types: (exports['.'] as { types?: string } | undefined)?.types }),
    files: ['src', 'types', 'dist', 'schema', 'runtime-exports.json', 'README.md'],
    sideEffects: p.name === '@fluxwp/ui' ? ['*.css'] : false,
    dependencies: { ...deps(src.dependencies), ...runtimeDeps },
    peerDependencies: deps(src.peerDependencies),
    peerDependenciesMeta: src.peerDependenciesMeta,
    publishConfig: { access: 'public', provenance: true },
  };
  writeFileSync(join(out, 'package.json'), JSON.stringify(pkg, null, 2) + '\n');
  const readme = join(dir, 'README.md');
  writeFileSync(
    join(out, 'README.md'),
    existsSync(readme) && p.name !== '@fluxwp/host'
      ? readFileSync(readme, 'utf8')
      : `# ${p.name}\n\n${String(pkg.description)}\n\nPart of [FluxWP](https://fluxwp.com). Plugin screens: [@fluxwp/plugin-kit](https://www.npmjs.com/package/@fluxwp/plugin-kit).\n`,
  );
  console.log(`staged ${p.name}@${src.version}`);
}
