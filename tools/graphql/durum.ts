// GraphQL migration status, one file per package: docs/graphql/durum/<slug>.md (docs/graphql/DURUM.md).
// A package pull request edits only its own file, so parallel PRs do not collide on one table.
//
//   bun tools/graphql/durum.ts               status table (markdown), grouped by phase
//   bun tools/graphql/durum.ts --istisnalar  package exceptions (the "## İstisnalar" tables)
//   bun tools/graphql/durum.ts --check       fails on a malformed file (CI)
//
// File format:
//   # flx-crm 0.10.0
//
//   - Faz: 1
//   - Durum: ✓            (○ başlamadı · ✱ sürüyor · 👀 PR açık · ✓ merge edildi)
//   - PR: #313
//
//   Free text: what the package moved, tests, open debts.
//
//   ## İstisnalar         (optional; same columns as docs/graphql/ISTISNALAR.md)
//   | Uç / akış | Paket | Neden | GraphQL tarafı |
//   |---|---|---|---|
//   | … |
import { existsSync, readdirSync, readFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const dir = join(root, 'docs/graphql/durum');
const STATES = ['○', '✱', '👀', '✓'];

interface Entry {
  file: string;
  title: string;
  phase: string;
  state: string;
  pr: string;
  note: string;
  exceptions: string[];
  problems: string[];
}

function parse(file: string, body: string): Entry {
  const e: Entry = { file, title: '', phase: '', state: '', pr: '', note: '', exceptions: [], problems: [] };
  const [main = '', exc = ''] = body.split(/^## İstisnalar\s*$/m);
  const lines = main.split('\n');
  const note: string[] = [];
  for (const line of lines) {
    const h = /^# (.+)$/.exec(line);
    const f = /^- (Faz|Durum|PR):\s*(.*)$/.exec(line);
    if (h && !e.title) e.title = h[1]!.trim();
    else if (f) {
      if (f[1] === 'Faz') e.phase = f[2]!.trim();
      if (f[1] === 'Durum') e.state = f[2]!.trim();
      if (f[1] === 'PR') e.pr = f[2]!.trim();
    } else note.push(line);
  }
  e.note = note.join(' ').replace(/\s+/g, ' ').trim();
  e.exceptions = exc
    .split('\n')
    .filter((l) => l.startsWith('|') && !/^\|\s*-/.test(l) && !/^\|\s*Uç \/ akış/.test(l));
  if (!e.title) e.problems.push('no "# <package> <version>" heading');
  if (!e.phase) e.problems.push('no "- Faz:" line');
  if (!STATES.includes(e.state)) e.problems.push(`"- Durum:" must be one of ${STATES.join(' ')}`);
  return e;
}

const entries = existsSync(dir)
  ? readdirSync(dir)
      .filter((f) => f.endsWith('.md'))
      .sort()
      .map((f) => parse(`docs/graphql/durum/${f}`, readFileSync(join(dir, f), 'utf8')))
  : [];

const cell = (s: string) => s.replace(/\|/g, '\\|');

if (process.argv.includes('--check')) {
  let bad = 0;
  for (const e of entries) for (const p of e.problems) (bad++, console.error(`${e.file}: ${p}`));
  console.log(`${entries.length} package status files, ${bad} problems`);
  process.exit(bad ? 1 : 0);
} else if (process.argv.includes('--istisnalar')) {
  console.log('| Uç / akış | Paket | Neden | GraphQL tarafı |\n|---|---|---|---|');
  for (const e of entries) for (const row of e.exceptions) console.log(row);
} else {
  const phases = [...new Set(entries.map((e) => e.phase))].sort((a, b) => a.localeCompare(b, 'tr', { numeric: true }));
  for (const phase of phases) {
    console.log(`\n## Faz ${phase}\n\n| Paket | Durum | PR | Not |\n|---|---|---|---|`);
    for (const e of entries.filter((x) => x.phase === phase)) console.log(`| ${cell(e.title)} | ${e.state} | ${cell(e.pr)} | ${cell(e.note)} |`);
  }
}
