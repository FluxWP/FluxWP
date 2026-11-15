/**
 * Strict TS lint: no explicit `any` in the packages listed in strict.json (plan §0.12).
 * `unknown` + narrowing instead. Generated files are skipped. Run: bun tools/ts-strict/no-any.ts
 */
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join, relative } from 'node:path';
import ts from 'typescript';

const root = join(import.meta.dir, '../..');
const { packages } = JSON.parse(readFileSync(join(import.meta.dir, 'strict.json'), 'utf8')) as { packages: string[] };

function* files(dir: string): Generator<string> {
  for (const name of readdirSync(dir)) {
    if (name === 'node_modules' || name === 'generated' || name === 'dist') continue;
    const p = join(dir, name);
    if (statSync(p).isDirectory()) yield* files(p);
    else if (/\.(ts|tsx|mts)$/.test(name) && !name.endsWith('.d.ts')) yield p;
  }
}

const hits: string[] = [];
for (const pkg of packages) {
  for (const file of files(join(root, pkg))) {
    const src = ts.createSourceFile(file, readFileSync(file, 'utf8'), ts.ScriptTarget.Latest, true);
    const visit = (n: ts.Node): void => {
      if (n.kind === ts.SyntaxKind.AnyKeyword) {
        const { line, character } = src.getLineAndCharacterOfPosition(n.getStart());
        hits.push(`${relative(root, file)}:${line + 1}:${character + 1} explicit any`);
      }
      ts.forEachChild(n, visit);
    };
    visit(src);
  }
}

if (hits.length) {
  console.error(hits.join('\n'));
  console.error(`\n${hits.length} explicit any. Use unknown and narrow, or a precise type.`);
  process.exit(1);
}
console.log(`no explicit any in ${packages.length} strict packages`);
