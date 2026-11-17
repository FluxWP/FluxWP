#!/usr/bin/env node
// Adds "preload" to an assets/flux/chunks.json: the chunks the entry imports, and per screen id (flxapp_modules
// id, from packages/modules/src/<folder>/screens.ts) the chunks that screen loads. Flux GraphQL prints them as
// <link rel="modulepreload">, so a screen's code downloads in parallel with the entry, not after it.
//   node tools/fluxapp/embed-preload.mjs <apps/wp-embed/dist> <assets/flux/chunks.json>
import fs from 'node:fs';
import path from 'node:path';

const [dist, out] = process.argv.slice(2);
const root = path.resolve(path.dirname(new URL(import.meta.url).pathname), '../..');
const manifest = JSON.parse(fs.readFileSync(path.join(dist, '.vite/manifest.json'), 'utf8'));
const byFile = new Map(Object.entries(manifest).map(([k, v]) => [k, v]));

/** A manifest entry's file and every chunk it imports statically (not dynamic imports). */
function closure(key, seen = new Set()) {
  const e = byFile.get(key);
  if (!e || seen.has(e.file)) return seen;
  seen.add(e.file);
  for (const i of e.imports ?? []) closure(i, seen);
  return seen;
}

const entryKey = Object.keys(manifest).find((k) => manifest[k].isEntry);
const entry = closure(entryKey);
const screens = {};
const src = path.join(root, 'packages/modules/src');
for (const folder of fs.readdirSync(src).sort()) {
  const file = path.join(src, folder, 'screens.ts');
  if (!fs.existsSync(file)) continue;
  const text = fs.readFileSync(file, 'utf8');
  for (const m of text.matchAll(/['"]?([\w-]+)['"]?\s*:\s*\(\)\s*=>\s*import\(\s*['"]\.\/([^'"]+)['"]\s*\)/g)) {
    const rel = `../../packages/modules/src/${folder}/${m[2]}`;
    const key = Object.keys(manifest).find((k) => k === `${rel}.tsx` || k === `${rel}.ts` || k === rel);
    if (!key) continue;
    screens[m[1]] = [...closure(key)].filter((f) => !entry.has(f) && f.endsWith('.js'));
  }
}
const cfg = JSON.parse(fs.readFileSync(out, 'utf8'));
cfg.main = byFile.get(entryKey).file;
cfg.preload = { entry: [...entry].filter((f) => f.endsWith('.js')), screens };
fs.writeFileSync(out, JSON.stringify(cfg, null, 2) + '\n');
console.log(`preload: ${cfg.preload.entry.length} entry chunks, ${Object.keys(screens).length} screens`);
