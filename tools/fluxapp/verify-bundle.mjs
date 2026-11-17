#!/usr/bin/env node
// Static link check of embedded React bundles (plugins/<slug>/assets/flux, chunks mode).
// A browser refuses a module when it imports a name the target file does not export ("Importing binding name
// '_t' is not found") and the screen stays blank. This catches that before release:
//   - every static import of a local file points to an existing file and only names that file exports
//   - the entry imports from chunks of its own folder only; chunks import the entry with ?v=<hash of the entry>
//   - chunks.json lists exactly the flux-*.js / flux-modules.css files of the folder
// Usage: node tools/fluxapp/verify-bundle.mjs [--strict] [dir ...]   (default: every plugins/*/assets/flux with chunks.json)
// Without --strict a chunk that still imports the entry without ?v=<hash> is only a warning (older bundles get the
// hash on their next tools/fluxapp/embed-sync.sh run); with --strict it fails.
import { readFileSync, readdirSync, existsSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { join, resolve } from 'node:path';

const root = resolve(new URL('../..', import.meta.url).pathname);
const strict = process.argv.includes('--strict');
let dirs = process.argv.slice(2).filter((a) => a !== '--strict');
if (dirs.length === 0) {
  dirs = readdirSync(join(root, 'plugins'))
    .map((s) => join(root, 'plugins', s, 'assets', 'flux'))
    .filter((d) => existsSync(join(d, 'chunks.json')));
}

const names = (list) =>
  list
    .split(',')
    .map((x) => x.trim())
    .filter(Boolean)
    .map((x) => x.split(/\s+as\s+/).map((p) => p.trim()));

let bad = 0;
for (const dir of dirs) {
  const errs = [];
  const warns = [];
  const files = readdirSync(dir).filter((f) => /^flux-.*\.js$/.test(f));
  const src = new Map(files.map((f) => [f, readFileSync(join(dir, f), 'utf8')]));
  const exportsOf = new Map();
  for (const [f, code] of src) {
    const ex = new Set();
    for (const m of code.matchAll(/export\s*\{([^}]*)\}/g)) for (const p of names(m[1])) ex.add(p[p.length - 1]);
    if (/export\s+default\b/.test(code)) ex.add('default');
    for (const m of code.matchAll(/export\s+(?:const|let|var|function\*?|class)\s+([A-Za-z_$][\w$]*)/g)) ex.add(m[1]);
    exportsOf.set(f, ex);
  }
  const entry = 'flux-modules.js';
  if (!src.has(entry)) errs.push(`${entry} missing`);
  const entryHash = src.has(entry) ? createHash('md5').update(src.get(entry)).digest('hex').slice(0, 10) : '';
  for (const [f, code] of src) {
    for (const m of code.matchAll(/import\s*(?:([\w$]+)\s*,?\s*)?(?:\{([^}]*)\})?\s*from\s*["'](\.\/[^"']+)["']/g)) {
      const [, def, list, spec] = m;
      const [file, query = ''] = spec.slice(2).split('?');
      if (!src.has(file)) {
        errs.push(`${f}: imports ${file}, which is not in the folder`);
        continue;
      }
      if (file === entry && f !== entry && query !== `v=${entryHash}`) (strict ? errs : warns).push(`${f}: imports ${spec} (expected ./${entry}?v=${entryHash}; run tools/fluxapp/version-imports.sh)`);
      const ex = exportsOf.get(file);
      if (def && !ex.has('default')) errs.push(`${f}: default import from ${file}, which has no default export`);
      for (const [name] of names(list ?? '')) if (!ex.has(name)) errs.push(`${f}: imports "${name}" from ${file}, which does not export it`);
    }
  }
  const listed = JSON.parse(readFileSync(join(dir, 'chunks.json'), 'utf8')).files ?? [];
  const actual = readdirSync(dir).filter((f) => /^flux-.*\.js$/.test(f) || f === 'flux-modules.css');
  for (const f of actual) if (!listed.includes(f)) errs.push(`${f} is not listed in chunks.json`);
  for (const f of listed) if (!actual.includes(f)) errs.push(`chunks.json lists ${f}, which is missing`);
  const rel = dir.startsWith(root) ? dir.slice(root.length + 1) : dir;
  if (errs.length) {
    bad++;
    console.log(`::error title=Embedded bundle is broken::${rel}`);
    for (const e of errs.slice(0, 15)) console.log(`  ${rel}: ${e}`);
    if (errs.length > 15) console.log(`  ... ${errs.length - 15} more`);
  } else console.log(`ok  ${rel} (${files.length} files)${warns.length ? ` — warning: ${warns.length} chunks import the entry without ?v (stale-cache risk until the next embed-sync)` : ''}`);
}
process.exit(bad ? 1 : 0);
