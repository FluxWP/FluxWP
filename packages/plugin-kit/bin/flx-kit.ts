#!/usr/bin/env node
// flx-kit: tools for a WordPress plugin with wp-admin screens on the Flux runtime (docs: README.md).
//
//   flx-kit codegen <plugin dir> [--out <dir>] [--schema <file>] [--check]
//   flx-kit schema-split <schema.graphql> <owners.json> <plugin dir>
//       the plugin's own part from `wp flx-gql schema --output=schema.graphql --owners=owners.json`
//       (run on a site with the plugin active) → <plugin>/graphql/schema.graphql
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { basename, join, resolve, relative } from 'node:path';
import { codegen } from '../src/codegen';
import { splitSchema, type Owners } from '../../graphql/scripts/parts';

function opt(args: string[], name: string): string | undefined {
  const i = args.indexOf(name);
  return i >= 0 ? args[i + 1] : undefined;
}

const [cmd, ...args] = process.argv.slice(2);
try {
  if (cmd === 'codegen' && args[0] && !args[0].startsWith('--')) {
    const check = args.includes('--check');
    const out = opt(args, '--out');
    const schema = opt(args, '--schema');
    const r = await codegen({ plugin: args[0], check, ...(out ? { out } : {}), ...(schema ? { schema } : {}) });
    for (const p of r.written) console.log(`wrote ${relative(process.cwd(), p)}`);
    for (const p of r.stale) console.error(`out of date: ${relative(process.cwd(), p)}`);
    console.log(`${r.operations} operations`);
    if (check && r.stale.length) {
      console.error('Run `flx-kit codegen` and commit the result.');
      process.exit(1);
    }
  } else if (cmd === 'schema-split' && args.length >= 3) {
    const [schemaFile, ownersFile, pluginDir] = args as [string, string, string];
    const owner = `plugins/${basename(resolve(pluginDir))}`;
    const owners = JSON.parse(readFileSync(ownersFile, 'utf8')) as Owners;
    const part = splitSchema(readFileSync(schemaFile, 'utf8'), owners).find((p) => p.path === `${owner}/graphql/schema.graphql`);
    if (!part) {
      console.error(`${owner} registers nothing in this schema (is the plugin active on that site?).`);
      process.exit(1);
    }
    const dest = join(resolve(pluginDir), 'graphql/schema.graphql');
    mkdirSync(join(resolve(pluginDir), 'graphql'), { recursive: true });
    writeFileSync(dest, part.body);
    console.log(`wrote ${relative(process.cwd(), dest)}`);
  } else {
    console.error('usage: flx-kit codegen <plugin dir> [--out <dir>] [--schema <file>] [--check]\n       flx-kit schema-split <schema.graphql> <owners.json> <plugin dir>');
    process.exit(2);
  }
} catch (e) {
  console.error((e as Error).message);
  process.exit(1);
}
