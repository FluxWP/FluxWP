// Value exports of every module the shared runtime (apps/wp-embed/src/runtime.tsx) hands to plugin bundles,
// read with the TypeScript checker → runtime-exports.json. The Vite preset (src/vite.ts) turns a plugin's
// `import { useState } from 'react'` into a read of globalThis.FluxRuntime.modules.react.useState and needs
// the names up front (ES modules have no dynamic named exports).
//
//   bun scripts/runtime-exports.ts           write runtime-exports.json
//   bun scripts/runtime-exports.ts --check   fail when it is out of date (CI)
import { readFileSync, writeFileSync, existsSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import ts from 'typescript';
import { RUNTIME_MODULES } from '../src/modules';

const pkg = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const embed = resolve(pkg, '../../apps/wp-embed');
const out = join(pkg, 'runtime-exports.json');

// The runtime's own list must be the same (runtime.tsx `modules` keys).
const runtimeSrc = readFileSync(join(embed, 'src/runtime.tsx'), 'utf8');
const block = /export const modules = \{([\s\S]*?)\} as const;/.exec(runtimeSrc)?.[1] ?? '';
const declared = [...block.matchAll(/^\s*(?:'([^']+)'|([A-Za-z0-9_]+)):/gm)].map((m) => m[1] ?? m[2]!);
if (declared.join('\n') !== [...RUNTIME_MODULES].join('\n')) {
  console.error(`runtime.tsx modules (${declared.join(', ')}) differ from src/modules.ts (${RUNTIME_MODULES.join(', ')})`);
  process.exit(1);
}

const probe = join(embed, 'src/__runtime-exports-probe.ts');
const text = RUNTIME_MODULES.map((m, i) => `import * as m${i} from '${m === '@fluxwp/host' ? '@fluxwp/modules/src/host' : m}';\nexport { m${i} };`).join('\n');
const cfg = ts.getParsedCommandLineOfConfigFile(join(embed, 'tsconfig.json'), {}, { ...ts.sys, onUnRecoverableConfigFileDiagnostic: () => {} })!;
const host = ts.createCompilerHost(cfg.options);
const readFile = host.readFile.bind(host);
host.readFile = (f) => (resolve(f) === probe ? text : readFile(f));
const fileExists = host.fileExists.bind(host);
host.fileExists = (f) => resolve(f) === probe || fileExists(f);
const program = ts.createProgram({ rootNames: [probe], options: { ...cfg.options, noEmit: true }, host });
const checker = program.getTypeChecker();
const sf = program.getSourceFile(probe)!;

const result: Record<string, string[]> = {};
let n = 0;
for (const stmt of sf.statements) {
  if (!ts.isImportDeclaration(stmt)) continue;
  const spec = RUNTIME_MODULES[n++]!;
  const sym = checker.getSymbolAtLocation(stmt.moduleSpecifier);
  if (!sym) throw new Error(`cannot resolve ${spec}`);
  const names = checker
    .getExportsOfModule(sym)
    .filter((s) => {
      const t = s.flags & ts.SymbolFlags.Alias ? checker.getAliasedSymbol(s) : s;
      return (t.flags & ts.SymbolFlags.Value) !== 0;
    })
    .map((s) => s.getName())
    .filter((name) => name !== 'default' && /^[A-Za-z_$][\w$]*$/.test(name))
    .sort();
  result[spec] = names;
}
const json = JSON.stringify(result, null, 2) + '\n';
if (process.argv.includes('--check')) {
  const cur = existsSync(out) ? readFileSync(out, 'utf8') : '';
  if (cur !== json) {
    console.error('runtime-exports.json is out of date: run `bun run --filter @fluxwp/plugin-kit exports`.');
    process.exit(1);
  }
} else {
  writeFileSync(out, json);
  console.log(`runtime-exports.json: ${Object.values(result).reduce((a, b) => a + b.length, 0)} names in ${RUNTIME_MODULES.length} modules`);
}
