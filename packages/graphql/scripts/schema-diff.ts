// Breaking schema changes between two snapshots (graphql-inspector).
//   bun scripts/schema-diff.ts <old.graphql> [new.graphql]   (new defaults to schema/schema.graphql)
// Exit 1 on a breaking change unless FLX_GQL_ALLOW_BREAKING=1 (PR label "graphql-breaking").
import { readFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { buildSchema } from 'graphql';
import { diff, CriticalityLevel } from '@graphql-inspector/core';

const pkg = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const [oldPath, newPath = join(pkg, 'schema/schema.graphql')] = process.argv.slice(2);
if (!oldPath) {
  console.error('usage: schema-diff.ts <old.graphql> [new.graphql]');
  process.exit(2);
}
const changes = await diff(buildSchema(readFileSync(oldPath, 'utf8')), buildSchema(readFileSync(newPath, 'utf8')));
const icon = { [CriticalityLevel.Breaking]: '✖', [CriticalityLevel.Dangerous]: '⚠', [CriticalityLevel.NonBreaking]: '✔' } as const;
for (const c of changes) console.log(`${icon[c.criticality.level]} ${c.message}`);
const breaking = changes.filter((c) => c.criticality.level === CriticalityLevel.Breaking);
console.log(`${changes.length} changes, ${breaking.length} breaking`);
if (breaking.length && process.env.FLX_GQL_ALLOW_BREAKING !== '1') {
  for (const c of breaking) console.log(`::error title=Breaking GraphQL change::${c.message}`);
  process.exit(1);
}
