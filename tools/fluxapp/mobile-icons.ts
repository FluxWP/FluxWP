#!/usr/bin/env bun
// brands/<slug>/icon.svg → brands/<slug>/mobile/*.png (app icon, Android adaptive + monochrome, splash,
// favicon, notification). Renders with a local Chrome via playwright-core; run once per brand, commit the PNGs.
// Usage: FLUX_BRAND=flux bun run icons   (CHROME_PATH overrides the browser)
import { chromium } from 'playwright-core';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';

const root = resolve(import.meta.dir, '../..');
const slug = process.env.FLUX_BRAND || 'flux';
const dir = join(root, 'brands', slug);
const brand = JSON.parse(readFileSync(join(dir, 'brand.json'), 'utf8'));
const svg = readFileSync(join(dir, brand.icon), 'utf8').replace(/<metadata>[\s\S]*?<\/metadata>/g, '');
const inner = svg.replace(/^[\s\S]*?<svg[^>]*>/, '').replace(/<\/svg>\s*$/, '');
const vb = svg.match(/viewBox="([^"]+)"/)?.[1] ?? '0 0 256 256';
const bg: string | null = brand.appIcon?.background ?? null;

const [, , vbW, vbH] = vb.split(/\s+/);
// Full-canvas rects are the icon's own background; adaptive/monochrome layers need the glyph alone.
const glyph = inner.replace(new RegExp(`<rect[^>]*width="${vbW}"[^>]*height="${vbH}"[^>]*/>`, 'g'), '');
const nest = (x: number, size: number, body = inner) => `<svg x="${x}" y="${x}" width="${size}" height="${size}" viewBox="${vb}">${body}</svg>`;
const page = (w: number, body: string) => `<svg xmlns="http://www.w3.org/2000/svg" width="${w}" height="${w}" viewBox="0 0 ${w} ${w}">${body}</svg>`;

// Store icons are square; the OS applies its own mask.
const icon = page(1024, bg ? `<rect width="1024" height="1024" fill="${bg}"/>${nest(192, 640)}` : nest(0, 1024));
// Android adaptive foreground: 108 dp canvas, 66 dp safe zone → logo at ~61% centred.
const adaptive = page(1024, nest(200, 624, glyph));
const mono = page(1024, `<defs><filter id="w"><feColorMatrix type="matrix" values="0 0 0 0 1  0 0 0 0 1  0 0 0 0 1  0 0 0 1 0"/></filter></defs><g filter="url(#w)">${nest(200, 624, glyph)}</g>`);
const splash = page(512, `<clipPath id="r"><rect width="512" height="512" rx="112"/></clipPath><g clip-path="url(#r)">${nest(0, 512)}</g>`);
const favicon = page(196, `<clipPath id="r"><rect width="196" height="196" rx="44"/></clipPath><g clip-path="url(#r)">${nest(0, 196)}</g>`);
const notification = mono.replace('width="1024" height="1024"', 'width="96" height="96"');

const out = join(dir, 'mobile');
mkdirSync(out, { recursive: true });
const executablePath = process.env.CHROME_PATH ?? ['/usr/local/bin/google-chrome', '/usr/bin/google-chrome', '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'].find(existsSync);
const browser = await chromium.launch({ executablePath });
const p = await browser.newPage();
for (const [name, doc, size] of [
  ['icon.png', icon, 1024],
  ['adaptive-icon.png', adaptive, 1024],
  ['adaptive-monochrome.png', mono, 1024],
  ['splash.png', splash, 512],
  ['favicon.png', favicon, 196],
  ['notification.png', notification, 96],
] as const) {
  await p.setViewportSize({ width: size, height: size });
  await p.setContent(`<html><body style="margin:0;background:transparent">${doc}</body></html>`);
  writeFileSync(join(out, name), await p.locator('svg').first().screenshot({ omitBackground: true }));
  console.log(`[icons] brands/${slug}/mobile/${name}`);
}
await browser.close();
