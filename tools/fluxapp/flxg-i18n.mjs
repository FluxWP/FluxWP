// Flux Tasks React ekranlarının İngilizce metinleri: WP eklentisinin .po dosyasından, yalnız kullanılan tt('...') metinleri.
// Kullanım: bun tools/fluxapp/flxg-i18n.mjs  (çıktı: packages/modules/src/tasks/en.json)
import { readFileSync, readdirSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

const root = new URL('../../', import.meta.url).pathname;
const po = readFileSync(join(root, 'plugins/flx-tasks/languages/flx-tasks-en_US.po'), 'utf8');
const unq = (s) => JSON.parse(s);
const dict = {};
let id = null, str = null, mode = null, ctx = false;
const flush = () => { if (id && str && !ctx) dict[id] = str; id = str = null; mode = null; ctx = false; };
for (const line of po.split('\n')) {
  if (line.startsWith('msgctxt ')) { flush(); ctx = true; continue; }
  if (line.startsWith('msgid ')) { if (id !== null) flush(); id = unq(line.slice(6)); mode = 'id'; continue; }
  if (line.startsWith('msgstr ')) { str = unq(line.slice(7)); mode = 'str'; continue; }
  if (line.startsWith('"')) { if (mode === 'id') id += unq(line); else if (mode === 'str') str += unq(line); continue; }
  if (!line.trim()) flush();
}
flush();

const dir = join(root, 'packages/modules/src/tasks');
const used = new Set();
for (const f of readdirSync(dir).filter((x) => /\.(tsx?|ts)$/.test(x))) {
  const src = readFileSync(join(dir, f), 'utf8');
  for (const m of src.matchAll(/\btt\(\s*'((?:[^'\\]|\\.)*)'/g)) used.add(m[1].replace(/\\'/g, "'"));
}
// Yalnız uygulamada olan metinler.
Object.assign(dict, {
  'Web panelinde aç (ayarlar, üyeler)': 'Open in web panel (settings, members)',
  '{n} görev kalıcı olarak silinecek.': '{n} tasks will be permanently deleted.',
  'Kopyalandı': 'Copied',
  'Tekrar dene': 'Try again',
});
const out = {};
const missing = [];
for (const s of [...used].sort()) {
  if (dict[s]) out[s] = dict[s];
  else missing.push(s);
}
writeFileSync(join(dir, 'en.json'), JSON.stringify(out, null, 1) + '\n');
console.log(`en.json: ${Object.keys(out).length} metin; .po'da olmayan ${missing.length}:`);
for (const s of missing) console.log('  - ' + s);
