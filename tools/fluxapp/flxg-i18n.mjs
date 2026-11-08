// Refresh canonical English task IDs without discarding non-PHP task labels.
// Usage: bun tools/fluxapp/flxg-i18n.mjs
import { readFileSync, readdirSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
const root = new URL('../../', import.meta.url).pathname;
const dir = join(root, 'packages/modules/src/tasks');
const current = JSON.parse(readFileSync(join(dir, 'en.json'), 'utf8'));
const native = JSON.parse(readFileSync(join(root, 'plugins/flx-tasks/languages/messages.en.json'), 'utf8')).messages;
const used = new Set();
for (const file of readdirSync(dir).filter(f => /\.(tsx?|ts)$/.test(f))) {
 const source = readFileSync(join(dir,file),'utf8');
 for (const match of source.matchAll(/\btt\(\s*("(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*')/g)) {
  const value = match[1].startsWith('"') ? JSON.parse(match[1]) : match[1].slice(1,-1).replace(/\\'/g,"'");
  used.add(value);
 }
}
// Keep dynamic engine labels as well as the literals found above.
const out = {...current};
for (const key of used) out[key] = native[key] || current[key] || key;
writeFileSync(join(dir,'en.json'),JSON.stringify(out,null,2)+'\n');
console.log(`Canonical English task strings: ${Object.keys(out).length}`);
