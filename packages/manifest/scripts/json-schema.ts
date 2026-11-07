#!/usr/bin/env bun
// valibot schemas → JSON Schema (draft-07) in packages/manifest/schema/. flx-app (PHP) and plugin CI validate
// against these. Regenerate after changing src/schema.ts: bun run --filter @fluxwp/manifest schema
// `--check` exits 1 when the committed files are stale (CI).
import { toJsonSchema } from '@valibot/to-json-schema';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { BootstrapSchema, BrandSchema, ConditionSchema, ConfigSchema, ManifestBundleSchema, ManifestSchema } from '../src/schema';

const out = resolve(import.meta.dir, '../schema');
const check = process.argv.includes('--check');
const base = 'https://fluxwp.com/schema/';

const files: [string, string, Parameters<typeof toJsonSchema>[0]][] = [
  ['manifest.schema.json', 'FluxWP app manifest (flx-app-manifest/1)', ManifestSchema],
  ['manifest-bundle.schema.json', 'GET flxapp/v1/manifest', ManifestBundleSchema],
  ['config.schema.json', 'FluxWP app config (flx-app-config/1), GET flxapp/v1/config', ConfigSchema],
  ['bootstrap.schema.json', 'GET flxapp/v1/app/bootstrap', BootstrapSchema],
  ['brand.schema.json', 'brands/<slug>/brand.json', BrandSchema],
];

mkdirSync(out, { recursive: true });
let stale = 0;
for (const [name, title, schema] of files) {
  const json = toJsonSchema(schema, { errorMode: 'ignore', definitions: { Condition: ConditionSchema } });
  const doc = { $id: base + name, title, ...json };
  const text = JSON.stringify(doc, null, 2) + '\n';
  const file = join(out, name);
  if (check) {
    if (!existsSync(file) || readFileSync(file, 'utf8') !== text) {
      console.error(`[schema] stale: packages/manifest/schema/${name}`);
      stale++;
    }
  } else {
    writeFileSync(file, text);
    console.log(`[schema] packages/manifest/schema/${name}`);
  }
}
if (stale) process.exit(1);
