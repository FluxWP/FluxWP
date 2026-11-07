#!/usr/bin/env bun
// bun run brand:new <slug> [--force]
// Creates brands/<slug>/ (brand.json + placeholder SVG). Does not run a build and writes no secrets.
// Store PNGs: FLUX_BRAND=<slug> bun run mobile:icons
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const require = createRequire(import.meta.url);
const { scaffoldBrand, withBrandProfiles, ICON_SVG, logoSvg, fail } = require('./brand-lib.cjs');

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');

export function createBrand(slug, { rootDir = root, force = false } = {}) {
  let brand;
  try {
    brand = scaffoldBrand(slug);
  } catch (e) {
    fail(slug, e.message.replace(/^\[brand\] [^:]+: /, ''));
  }
  const dir = join(rootDir, 'brands', slug);
  if (existsSync(dir) && !force) fail(slug, `brands/${slug} already exists (pass --force to replace the json and logos)`);
  mkdirSync(dir, { recursive: true });
  writeFileSync(join(dir, 'brand.json'), JSON.stringify(brand, null, 2) + '\n');
  writeFileSync(join(dir, 'icon.svg'), ICON_SVG);
  writeFileSync(join(dir, 'logo.svg'), logoSvg('#134E4A'));
  writeFileSync(join(dir, 'logo-dark.svg'), logoSvg('#E7F6F3'));
  const easPath = join(rootDir, 'apps/mobile/eas.json');
  if (existsSync(easPath)) {
    const eas = withBrandProfiles(JSON.parse(readFileSync(easPath, 'utf8')), slug);
    writeFileSync(easPath, JSON.stringify(eas, null, 2) + '\n');
  }
  return { dir, brand };
}

const invoked = process.argv[1] && process.argv[1].endsWith('brand-new.mjs');
if (invoked) {
  const args = process.argv.slice(2);
  const slug = args.find((a) => !a.startsWith('--'));
  if (!slug) {
    console.error('Usage: bun run brand:new <slug> [--force]');
    process.exit(1);
  }
  try {
    const { dir } = createBrand(slug, { force: args.includes('--force') });
    console.log(`[brand] scaffolded ${dir}`);
    console.log(`[brand] edit brand.json (name, allowedSites, colors), then: FLUX_BRAND=${slug} bun run mobile:icons`);
  } catch (e) {
    console.error(e.message);
    process.exit(1);
  }
}
