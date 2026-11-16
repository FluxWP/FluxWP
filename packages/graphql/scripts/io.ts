// Reads the schema parts of a checkout (scripts/parts.ts).
import { existsSync, readdirSync, readFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { CORE_PART, LEGACY_SNAPSHOT, type Part } from './parts';

export const repoRoot = resolve(dirname(fileURLToPath(import.meta.url)), '../../..');

/** Part files under a checkout: core + plugins/<slug> + themes/<slug>; the legacy snapshot when none exists. */
export function readParts(root: string = repoRoot): Part[] {
  const paths: string[] = [];
  if (existsSync(join(root, CORE_PART))) paths.push(CORE_PART);
  for (const dir of ['plugins', 'themes']) {
    if (!existsSync(join(root, dir))) continue;
    for (const slug of readdirSync(join(root, dir)).sort()) {
      const p = `${dir}/${slug}/graphql/schema.graphql`;
      if (existsSync(join(root, p))) paths.push(p);
    }
  }
  if (!paths.length && existsSync(join(root, LEGACY_SNAPSHOT))) paths.push(LEGACY_SNAPSHOT);
  return paths.map((path) => ({ path, body: readFileSync(join(root, path), 'utf8') }));
}

