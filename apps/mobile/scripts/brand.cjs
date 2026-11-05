// Mobile output of the brand pipeline: brands/<slug>/brand.json (shared with the desktop build) →
// src/brand/current.json + assets/brand/*. Same rules as tools/fluxapp/brand-lib.cjs.
// CommonJS on purpose: app.config.ts loads it and Expo's config loader does not transpile imports.
const { copyFileSync, existsSync, mkdirSync, readFileSync, readdirSync, writeFileSync } = require('node:fs');
const { dirname, join, resolve } = require('node:path');
const { toMobileBrand } = require('../../../tools/fluxapp/brand-lib.cjs');

const app = resolve(__dirname, '..');
const repo = resolve(app, '../..');

const fail = (slug, msg) => {
  throw new Error(`[brand] ${slug}: ${msg}`);
};

/** brand.json → the object the app reads, after the shared checks. */
function loadBrand(slugInput) {
  const slug = slugInput || process.env.FLUX_BRAND || 'flux';
  const dir = join(repo, 'brands', slug);
  const file = join(dir, 'brand.json');
  if (!existsSync(file)) fail(slug, `brands/${slug}/brand.json not found`);
  const b = JSON.parse(readFileSync(file, 'utf8'));
  const brand = toMobileBrand(slug, b);
  return { brand, dir, raw: b };
}

const writeIfChanged = (p, data) => {
  mkdirSync(dirname(p), { recursive: true });
  if (existsSync(p) && readFileSync(p, 'utf8') === data) return;
  writeFileSync(p, data);
};

const ASSETS = ['icon.png', 'adaptive-icon.png', 'adaptive-monochrome.png', 'splash.png', 'favicon.png', 'notification.png'];

/** Writes the generated files; returns the brand for app.config.ts. */
function applyBrand(slugInput) {
  const { brand, dir } = loadBrand(slugInput);
  const json = JSON.stringify(brand, null, 2) + '\n';
  writeIfChanged(join(app, 'src/brand/current.json'), json);
  const svg = (f) => (f && existsSync(join(dir, f)) ? readFileSync(join(dir, f), 'utf8').replace(/<metadata>[\s\S]*?<\/metadata>/g, '').replace(/\s+xmlns:c2pa="[^"]*"/g, '') : null);
  const logo = svg(brand.logoFile) || '';
  writeIfChanged(join(app, 'src/brand/logo.json'), JSON.stringify({ light: logo, dark: svg(brand.logoDarkFile) || logo }) + '\n');
  const src = join(dir, 'mobile');
  const out = join(app, 'assets/brand');
  mkdirSync(out, { recursive: true });
  for (const f of ASSETS) {
    if (!existsSync(join(src, f))) fail(brand.slug, `brands/${brand.slug}/mobile/${f} missing (run: FLUX_BRAND=${brand.slug} bun run mobile:icons)`);
    copyFileSync(join(src, f), join(out, f));
  }
  for (const f of readdirSync(dir).filter((x) => x.endsWith('.svg'))) copyFileSync(join(dir, f), join(out, f));
  return brand;
}

module.exports = { loadBrand, applyBrand };

if (require.main === module) {
  const b = applyBrand(process.env.FLUX_BRAND);
  console.log(`[brand] ${b.name} (${b.slug}) → apps/mobile`);
}
