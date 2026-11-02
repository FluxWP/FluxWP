#!/usr/bin/env node
// Sol menü sabit kutucukları (accordion) görüntüleri. Kullanım: node tools/fluxapp/screens-menu.mjs <çıktı> [port]
import { createRequire } from 'node:module';
import { mkdirSync } from 'node:fs';
const require = createRequire(import.meta.url);
const { chromium } = require('playwright');
const out = process.argv[2] || 'screens';
const port = process.argv[3] || '4173';
mkdirSync(out, { recursive: true });
const browser = await chromium.launch();
const errors = [];
for (const scheme of ['light', 'dark']) {
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 860 }, deviceScaleFactor: 2, colorScheme: scheme, locale: 'tr-TR' });
  const page = await ctx.newPage();
  page.on('pageerror', (e) => errors.push(scheme + ': ' + e.message));
  await page.goto(`http://localhost:${port}/`);
  await page.locator('.demo-link').click();
  await page.waitForSelector('.card-value');
  await page.waitForTimeout(500);
  const side = () => page.locator('.sidebar').screenshot;
  const shot = (n) => page.locator('.sidebar').screenshot({ path: `${out}/${n}-${scheme}.png` });
  await shot('30-menu-baslangic');
  await page.click('.nav-pin:has-text("Görevler")');
  await page.waitForTimeout(140);
  await shot('31-menu-gecis');
  await page.waitForTimeout(700);
  await shot('32-menu-gorevler');
  await page.click('.nav-pin:has-text("Çağrı Merkezi")');
  await page.waitForTimeout(900);
  await shot('33-menu-cagri');
  if (scheme === 'light') await page.screenshot({ path: `${out}/34-menu-tam-${scheme}.png` });
  await ctx.close();
}
await browser.close();
console.log(errors.length ? errors.join('\n') : 'hata yok');
