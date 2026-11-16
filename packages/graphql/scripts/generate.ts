// Operations → typed operations + persisted query manifests.
//
//   schema parts (scripts/parts.ts)                core.graphql + plugins/<slug>/graphql/schema.graphql
//   plugins/<slug>/graphql/operations/*.graphql    operations a plugin ships (queries + mutations)
//   →  plugins/<slug>/graphql/persisted-queries.json   { sha256: document } read by Flux GraphQL (committed)
//   →  packages/graphql/src/generated/types.ts          schema + operation types (graphql-codegen)
//   →  packages/graphql/src/generated/operations.ts     FlxOperation constants
//   →  packages/graphql/schema/full.graphql             the assembled schema (for editors and diffs)
//
// The TypeScript files and full.graphql are not committed (one file for every package would make parallel
// pull requests collide); `bun install` runs this script (root postinstall), CI too.
// `--check` writes nothing and fails when a committed manifest is out of date (CI).
import { existsSync, readdirSync, readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { dirname, join, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { buildSchema, parse } from 'graphql';
import { codegen } from '@graphql-codegen/core';
import * as operations from '@graphql-codegen/typescript-operations';
import { collectOperations, GenerateError, HEADER, manifests, operationsModule, type SourceFile } from './lib';
import { readParts } from './io';
import { assembleSdl } from './parts';

const pkg = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const root = resolve(pkg, '../..');
const check = process.argv.includes('--check');
const quiet = process.argv.includes('--quiet');

function graphqlFiles(dir: string): string[] {
  if (!existsSync(dir)) return [];
  return readdirSync(dir, { withFileTypes: true }).flatMap((e) => {
    const p = join(dir, e.name);
    if (e.isDirectory()) return graphqlFiles(p);
    return e.isFile() && e.name.endsWith('.graphql') ? [p] : [];
  });
}

let schemaSdl: string;
try {
  schemaSdl = assembleSdl(readParts(root));
} catch (e) {
  console.error(`schema parts: ${(e as Error).message}`);
  process.exit(1);
}
const schema = buildSchema(schemaSdl);

const sources: SourceFile[] = [];
for (const slug of readdirSync(join(root, 'plugins')).sort()) {
  for (const path of graphqlFiles(join(root, 'plugins', slug, 'graphql/operations')).sort()) {
    sources.push({ plugin: slug, path: relative(root, path), body: readFileSync(path, 'utf8') });
  }
}

let ops;
try {
  ops = collectOperations(schema, sources);
} catch (e) {
  if (e instanceof GenerateError) {
    console.error(e.message);
    process.exit(1);
  }
  throw e;
}

const files = new Map<string, string>();
for (const [plugin, json] of manifests(ops)) files.set(join(root, 'plugins', plugin, 'graphql/persisted-queries.json'), json);

const types = await codegen({
  filename: 'types.ts',
  schema: parse(schemaSdl),
  documents: ops.map((op) => ({ location: op.path, document: parse(op.document) })),
  // typescript-operations 7 emits the enums, inputs and scalars the operations use; adding the
  // `typescript` plugin as well declares them twice (TS2300).
  plugins: [{ typescriptOperations: {} }],
  pluginMap: { typescriptOperations: operations },
  config: {
    onlyOperationTypes: true,
    enumsAsTypes: true,
    useTypeImports: true,
    dedupeOperationSuffix: true,
    avoidOptionals: { field: true, inputValue: false, object: true, defaultValue: false },
    maybeValue: 'T | null',
    inputMaybeValue: 'T | null | undefined',
    strictScalars: true,
    scalars: { ID: 'string', FlxJSON: 'unknown' },
    defaultScalarType: 'unknown',
    skipTypename: false,
    nonOptionalTypename: false,
  },
});
// Not committed: written in both modes, never reported as stale.
const local = new Map<string, string>([
  [join(pkg, 'src/generated/types.ts'), HEADER + '/* eslint-disable */\n' + types],
  [join(pkg, 'src/generated/operations.ts'), operationsModule(ops)],
  [join(pkg, 'schema/full.graphql'), '# Assembled from the schema parts by scripts/generate.ts. Not committed.\n' + schemaSdl],
]);
for (const [path, body] of local) {
  if (existsSync(path) && readFileSync(path, 'utf8') === body) continue;
  mkdirSync(dirname(path), { recursive: true });
  writeFileSync(path, body);
  if (!quiet) console.log(`wrote ${relative(root, path)}`);
}

let stale = 0;
for (const [path, body] of files) {
  const current = existsSync(path) ? readFileSync(path, 'utf8') : null;
  if (current === body) continue;
  if (check) {
    console.error(`out of date: ${relative(root, path)}`);
    stale++;
  } else {
    mkdirSync(dirname(path), { recursive: true });
    writeFileSync(path, body);
    console.log(`wrote ${relative(root, path)}`);
  }
}
// A plugin that no longer ships operations must not keep an old manifest.
for (const slug of readdirSync(join(root, 'plugins'))) {
  const path = join(root, 'plugins', slug, 'graphql/persisted-queries.json');
  if (existsSync(path) && !files.has(path)) {
    console.error(`${check ? 'stale' : 'remove'}: ${relative(root, path)} (no operations left)`);
    stale++;
  }
}
if (!quiet) console.log(`${ops.length} operations from ${new Set(ops.map((o) => o.plugin)).size} plugins`);
if (stale && check) {
  console.error('Run `bun run --filter @fluxwp/graphql generate` and commit the result.');
  process.exit(1);
}
