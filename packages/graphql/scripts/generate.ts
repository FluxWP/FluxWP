// Operations → typed operations + persisted query manifests.
//
//   schema/schema.graphql                          snapshot of the server schema (scripts/schema.ts)
//   plugins/<slug>/graphql/operations/*.graphql    operations a plugin ships (queries + mutations)
//   →  plugins/<slug>/graphql/persisted-queries.json   { sha256: document } read by Flux GraphQL
//   →  packages/graphql/src/generated/types.ts          schema + operation types (graphql-codegen)
//   →  packages/graphql/src/generated/operations.ts     FlxOperation constants
//
// `--check` writes nothing and fails when a generated file is out of date (CI).
import { existsSync, readdirSync, readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { dirname, join, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { buildSchema, parse } from 'graphql';
import { codegen } from '@graphql-codegen/core';
import * as typescript from '@graphql-codegen/typescript';
import * as operations from '@graphql-codegen/typescript-operations';
import { collectOperations, GenerateError, HEADER, manifests, operationsModule, type SourceFile } from './lib';

const pkg = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const root = resolve(pkg, '../..');
const check = process.argv.includes('--check');

function graphqlFiles(dir: string): string[] {
  if (!existsSync(dir)) return [];
  return readdirSync(dir, { withFileTypes: true }).flatMap((e) => {
    const p = join(dir, e.name);
    if (e.isDirectory()) return graphqlFiles(p);
    return e.isFile() && e.name.endsWith('.graphql') ? [p] : [];
  });
}

const schemaSdl = readFileSync(join(pkg, 'schema/schema.graphql'), 'utf8');
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
  plugins: [{ typescript: {} }, { typescriptOperations: {} }],
  pluginMap: { typescript, typescriptOperations: operations },
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
files.set(join(pkg, 'src/generated/types.ts'), HEADER + '/* eslint-disable */\n' + types);
files.set(join(pkg, 'src/generated/operations.ts'), operationsModule(ops));

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
console.log(`${ops.length} operations from ${new Set(ops.map((o) => o.plugin)).size} plugins`);
if (stale && check) {
  console.error('Run `bun run --filter @fluxwp/graphql generate` and commit the result.');
  process.exit(1);
}
