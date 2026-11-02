#!/usr/bin/env node
// Görevler ekranı görüntüleri (demo hesabı). Kullanım: node tools/fluxapp/screens-gorev.mjs <çıktı> [port]
// Önce: (cd packages/panel && bunx vite build && bunx vite preview --port <port>)
import { createRequire } from 'node:module';
import { mkdirSync } from 'node:fs';
const require = createRequire(import.meta.url);
const { chromium } = require('playwright');
const out = process.argv[2] || 'screens';
const port = process.argv[3] || '4173';
mkdirSync(out, { recursive: true });
const browser = await chromium.launch();
const errors = [];

async function run(scheme, locale, full) {
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2, colorScheme: scheme, locale });
  const page = await ctx.newPage();
  page.on('pageerror', (e) => errors.push(scheme + ': ' + e.message));
  page.on('console', (m) => m.type() === 'error' && errors.push(scheme + ' console: ' + m.text()));
  const sfx = `-${scheme}-${locale.slice(0, 2)}`;
  const shot = (n) => page.screenshot({ path: `${out}/${n}${sfx}.png` });
  await page.goto(`http://localhost:${port}/`);
  await page.locator('.demo-link').click();
  await page.waitForSelector('.card-value');
  await page.click(':is(.nav-pin,.nav-item):has-text("Görevler")');
  await page.waitForTimeout(400);
  if (!(await page.locator('.flxg-side').count())) await page.click('.nav-item:has-text("Görevler") >> nth=0');
  await page.waitForSelector('.flxg-side .flxg-tree-row');
  // 11 Kasım listesi
  await page.click('.flxg-tree-row.is-space:has-text("Ağaçlandırma") .flxg-tree-link');
  await page.waitForTimeout(300);
  await page.click('.flxg-tree-link:has-text("11 Kasım")');
  await page.waitForSelector('.flxg-row');
  await page.waitForTimeout(500);
  await shot('20-gorev-liste');
  await page.click('.flxg-row[data-id="102"] .flxg-title');
  await page.waitForSelector('.flxg-drawer.is-open .flxg-cmt');
  await page.waitForTimeout(700);
  await shot('21-gorev-detay');
  await page.keyboard.press('Escape');
  await page.waitForTimeout(300);
  if (full) {
    await page.click('.flxg-tab:has-text("Pano"), .flxg-tab:has-text("Board")');
    await page.waitForSelector('.flxg-card');
    await page.waitForTimeout(600);
    await shot('22-gorev-pano');
    await page.click('.flxg-tab:has-text("Takvim"), .flxg-tab:has-text("Calendar")');
    await page.waitForSelector('.flxg-cal');
    await page.waitForTimeout(400);
    await shot('23-gorev-takvim');
    await page.click('.flxg-tree-row.is-space:has-text("Dernek") .flxg-tree-link');
    await page.waitForTimeout(300);
    await page.click('.flxg-tree-link:has-text("Yönetim kurulu")');
    await page.waitForSelector('.flxg-row');
    await page.click('.flxg-tab >> nth=0');
    await page.waitForTimeout(300);
    await page.click('.flxg-row[data-id="201"] .flxg-title');
    await page.waitForSelector('.flxg-mtg-agenda');
    await page.waitForTimeout(600);
    await shot('24-gorev-toplanti');
    await page.keyboard.press('Escape');
    await page.click('.flxg-nav-item:has-text("Bildirimler"), .flxg-nav-item:has-text("Notifications")');
    await page.waitForSelector('.flxg-notif');
    await page.waitForTimeout(300);
    await shot('25-gorev-bildirimler');
  }
  await ctx.close();
}
await run('light', 'tr-TR', true);
await run('dark', 'tr-TR', false);
await run('light', 'en-US', false);
await browser.close();
console.log(errors.length ? 'HATALAR:\n' + errors.join('\n') : 'hata yok');
console.log('ok', out);
