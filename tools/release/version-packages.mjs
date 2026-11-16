// Release-time versioning for @fluxwp/modules and @fluxwp/graphql (changesets-like, no deps).
//   node tools/release/version-packages.mjs            apply: bump package.json, prepend CHANGELOG, delete notes
//   node tools/release/version-packages.mjs --dry-run  print the plan only
//   node tools/release/version-packages.mjs --check [base]  PR guard: notes valid, version/CHANGELOG untouched vs base
import { readFileSync, writeFileSync, readdirSync, unlinkSync, existsSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { execFileSync } from 'node:child_process';

export const PACKAGES = {
  '@fluxwp/modules': { dir: 'packages/modules', tag: 'modules' },
  '@fluxwp/graphql': { dir: 'packages/graphql', tag: 'graphql' },
};
const BUMPS = ['patch', 'minor', 'major'];

export function parseNote(file, text) {
  const m = /^---\r?\n([\s\S]*?)\r?\n---\r?\n?([\s\S]*)$/.exec(text);
  if (!m) throw new Error(`${file}: front matter (--- ... ---) missing`);
  const bumps = {};
  for (const line of m[1].split(/\r?\n/).filter((l) => l.trim())) {
    const kv = /^"?(@fluxwp\/[a-z-]+)"?\s*:\s*(\w+)\s*$/.exec(line.trim());
    if (!kv) throw new Error(`${file}: cannot parse "${line}"`);
    if (!PACKAGES[kv[1]]) throw new Error(`${file}: unknown package ${kv[1]}`);
    if (!BUMPS.includes(kv[2])) throw new Error(`${file}: bump must be ${BUMPS.join('|')}, got ${kv[2]}`);
    bumps[kv[1]] = kv[2];
  }
  if (!Object.keys(bumps).length) throw new Error(`${file}: no package listed`);
  const body = m[2].trim();
  if (!body) throw new Error(`${file}: empty change text`);
  return { file, bumps, body };
}

export function bump(version, level) {
  const [a, b, c] = version.split('.').map(Number);
  if ([a, b, c].some(Number.isNaN)) throw new Error(`bad version ${version}`);
  return level === 'major' ? `${a + 1}.0.0` : level === 'minor' ? `${a}.${b + 1}.0` : `${a}.${b}.${c + 1}`;
}

export function plan(notes, versions) {
  const out = {};
  for (const name of Object.keys(PACKAGES)) {
    const mine = notes.filter((n) => n.bumps[name]);
    if (!mine.length) continue;
    const level = BUMPS[Math.max(...mine.map((n) => BUMPS.indexOf(n.bumps[name])))];
    out[name] = { from: versions[name], to: bump(versions[name], level), level, entries: mine.map((n) => n.body) };
  }
  return out;
}

export function changelogSection(version, entries) {
  const bullets = entries.map((e) => (e.startsWith('- ') ? e : `- ${e.replace(/\n/g, '\n  ')}`));
  return `## ${version}\n${bullets.join('\n')}\n\n`;
}

export function prependChangelog(text, section) {
  const i = text.search(/^## /m);
  return i < 0 ? `${text.trimEnd()}\n\n${section}`.trimEnd() + '\n' : text.slice(0, i) + section + text.slice(i);
}

const root = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const readNotes = () => {
  const dir = join(root, '.changes');
  if (!existsSync(dir)) return [];
  return readdirSync(dir)
    .filter((f) => f.endsWith('.md') && f.toLowerCase() !== 'readme.md')
    .sort()
    .map((f) => parseNote(`.changes/${f}`, readFileSync(join(dir, f), 'utf8')));
};
const pkgPath = (name) => join(root, PACKAGES[name].dir, 'package.json');
const readVersions = () =>
  Object.fromEntries(Object.keys(PACKAGES).map((n) => [n, JSON.parse(readFileSync(pkgPath(n), 'utf8')).version]));

function check(base) {
  const notes = readNotes();
  const bad = [];
  if (base) {
    for (const [name, p] of Object.entries(PACKAGES)) {
      const old = JSON.parse(execFileSync('git', ['show', `${base}:${p.dir}/package.json`], { cwd: root, encoding: 'utf8' }));
      if (old.version !== readVersions()[name]) bad.push(`${p.dir}/package.json: version changed in a PR (use a .changes note)`);
      const diff = execFileSync('git', ['diff', '-U0', `${base}`, '--', `${p.dir}/CHANGELOG.md`], { cwd: root, encoding: 'utf8' });
      if (/^[+-]## /m.test(diff)) bad.push(`${p.dir}/CHANGELOG.md: version heading changed in a PR (use a .changes note)`);
    }
  }
  if (bad.length) { console.error(bad.join('\n')); process.exit(1); }
  console.log(`ok, ${notes.length} pending note(s)`);
}

function main() {
  const args = process.argv.slice(2);
  if (args[0] === '--check') return check(args[1]);
  const dry = args.includes('--dry-run');
  const notes = readNotes();
  const result = plan(notes, readVersions());
  if (!Object.keys(result).length) { console.log('No pending change notes.'); return; }
  for (const [name, r] of Object.entries(result)) console.log(`${name} ${r.from} -> ${r.to} (${r.level}, ${r.entries.length} note(s))`);
  if (dry) return;
  for (const [name, r] of Object.entries(result)) {
    const pj = pkgPath(name);
    writeFileSync(pj, readFileSync(pj, 'utf8').replace(/("version"\s*:\s*")[^"]+(")/, `$1${r.to}$2`));
    const cl = join(root, PACKAGES[name].dir, 'CHANGELOG.md');
    writeFileSync(cl, prependChangelog(readFileSync(cl, 'utf8'), changelogSection(r.to, r.entries)));
  }
  for (const n of notes) unlinkSync(join(root, n.file));
  const summary = Object.entries(result).map(([n, r]) => `${n} ${r.to}`).join(', ');
  if (process.env.GITHUB_OUTPUT) {
    writeFileSync(process.env.GITHUB_OUTPUT, `summary=${summary}\n`, { flag: 'a' });
  }
}

if (process.argv[1] && fileURLToPath(import.meta.url) === process.argv[1]) main();
