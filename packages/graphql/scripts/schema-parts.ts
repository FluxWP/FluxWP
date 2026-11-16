// Schema parts on disk (scripts/parts.ts).
//
//   bun scripts/schema-parts.ts split <schema.graphql> <owners.json> [--only <owner>]
//       writes every part (and removes parts nobody owns any more); --only plugins/<slug> writes that part only
//   bun scripts/schema-parts.ts assemble [--root <dir>] [--out <file>]
//       prints (or writes) the full schema built from the parts under <dir> (default: this repository)
import { existsSync, mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { assembleSdl, LEGACY_SNAPSHOT, partPath, splitSchema, type Owners } from './parts';
import { readParts, repoRoot } from './io';

function arg(name: string): string | undefined {
  const i = process.argv.indexOf(name);
  return i > 0 ? process.argv[i + 1] : undefined;
}

{
  const [cmd, a, b] = process.argv.slice(2);
  if (cmd === 'split' && a && b) {
    const owners = JSON.parse(readFileSync(b, 'utf8')) as Owners;
    const only = arg('--only');
    const parts = splitSchema(readFileSync(a, 'utf8'), owners);
    const keep = new Set(parts.map((p) => p.path));
    for (const p of parts) {
      if (only !== undefined && p.path !== partPath(only)) continue;
      const file = join(repoRoot, p.path);
      if (existsSync(file) && readFileSync(file, 'utf8') === p.body) continue;
      mkdirSync(dirname(file), { recursive: true });
      writeFileSync(file, p.body);
      console.log(`wrote ${p.path} (${p.body.split('\n').length - 1} lines)`);
    }
    if (only === undefined) {
      for (const p of readParts()) {
        if (keep.has(p.path) || p.path === LEGACY_SNAPSHOT) continue;
        rmSync(join(repoRoot, p.path));
        console.log(`removed ${p.path} (owns nothing)`);
      }
      if (existsSync(join(repoRoot, LEGACY_SNAPSHOT))) {
        rmSync(join(repoRoot, LEGACY_SNAPSHOT));
        console.log(`removed ${LEGACY_SNAPSHOT} (split into parts)`);
      }
    } else if (!keep.has(partPath(only))) {
      console.error(`${only} owns nothing in this schema`);
      process.exit(1);
    }
  } else if (cmd === 'assemble') {
    const sdl = assembleSdl(readParts(resolve(arg('--root') ?? repoRoot)));
    const out = arg('--out');
    if (out) writeFileSync(out, sdl);
    else process.stdout.write(sdl);
  } else {
    console.error('usage: schema-parts.ts split <schema.graphql> <owners.json> [--only <owner>] | assemble [--root <dir>] [--out <file>]');
    process.exit(2);
  }
}
