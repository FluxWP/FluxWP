#!/usr/bin/env bun
// bun run brand:pull <site> --slug <slug>
// GET flxapp/v1/app/brand (manage_options) → brands/<slug>/brand.json draft + logo/icon files.
// Auth from the environment, never from the saved file and never printed:
//   FLXAPP_TOKEN            Bearer token
//   FLXAPP_USER + FLXAPP_APP_PASSWORD   WordPress application password
import { mkdirSync, writeFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const require = createRequire(import.meta.url);
const { draftFromBrandResponse } = require('./brand-lib.cjs');

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');

function arg(name) {
  const i = process.argv.indexOf(`--${name}`);
  return i >= 0 ? process.argv[i + 1] : '';
}

async function fetchBrand(site) {
  const base = site.replace(/\/$/, '');
  const url = `${/^https?:\/\//i.test(base) ? base : `https://${base}`}/wp-json/flxapp/v1/app/brand`;
  const headers = { Accept: 'application/json' };
  if (process.env.FLXAPP_TOKEN) headers.Authorization = `Bearer ${process.env.FLXAPP_TOKEN}`;
  else if (process.env.FLXAPP_USER && process.env.FLXAPP_APP_PASSWORD) {
    headers.Authorization = `Basic ${Buffer.from(`${process.env.FLXAPP_USER}:${process.env.FLXAPP_APP_PASSWORD}`).toString('base64')}`;
  } else {
    throw new Error('[brand] set FLXAPP_TOKEN or FLXAPP_USER and FLXAPP_APP_PASSWORD');
  }
  const res = await fetch(url, { headers });
  if (!res.ok) throw new Error(`[brand] GET app/brand failed (${res.status})`);
  return { body: await res.json(), url };
}

async function saveAsset(dir, url, name) {
  if (!url || typeof url !== 'string' || !/^https?:\/\//i.test(url)) return null;
  const res = await fetch(url);
  if (!res.ok) return null;
  const type = res.headers.get('content-type') || '';
  const buf = Buffer.from(await res.arrayBuffer());
  const svg = type.includes('svg') || url.split('?')[0].endsWith('.svg');
  const file = svg ? name : name.replace(/\.svg$/, '.png');
  writeFileSync(join(dir, file), buf);
  return file;
}

export async function pullBrand(site, slug) {
  const { body } = await fetchBrand(site);
  const brand = draftFromBrandResponse(body, slug || body.brand_json?.slug);
  const dir = join(root, 'brands', brand.slug);
  mkdirSync(dir, { recursive: true });
  const assets = body.assets || {};
  const iconFile = await saveAsset(dir, assets.icon, 'icon.svg');
  const logoFile = await saveAsset(dir, assets.logo, 'logo.svg');
  const darkFile = await saveAsset(dir, assets.logo_dark, 'logo-dark.svg');
  if (iconFile && !iconFile.endsWith('.svg')) console.warn(`[brand] ${iconFile} is not SVG. Draw brands/${brand.slug}/icon.svg, then run mobile:icons.`);
  if (!iconFile) console.warn(`[brand] no icon downloaded. Add brands/${brand.slug}/icon.svg`);
  if (logoFile) brand.logo = logoFile.endsWith('.svg') ? 'logo.svg' : logoFile;
  if (darkFile && darkFile.endsWith('.svg')) brand.logoDark = 'logo-dark.svg';
  writeFileSync(join(dir, 'brand.json'), JSON.stringify(brand, null, 2) + '\n');
  for (const w of body.warnings || []) console.warn(`[brand] ${w}`);
  console.log(`[brand] wrote brands/${brand.slug}/brand.json (review it before committing; no token is stored)`);
  return brand;
}

const invoked = process.argv[1] && process.argv[1].endsWith('brand-pull.mjs');
if (invoked) {
  const site = process.argv.slice(2).find((a) => !a.startsWith('--'));
  if (!site) {
    console.error('Usage: bun run brand:pull <site> --slug <slug>');
    process.exit(1);
  }
  pullBrand(site, arg('slug')).catch((e) => {
    console.error(e.message);
    process.exit(1);
  });
}
