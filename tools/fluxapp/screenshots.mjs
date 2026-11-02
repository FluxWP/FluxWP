#!/usr/bin/env node
// Panel ekran görüntüleri (demo hesabı). Kullanım: node tools/fluxapp/screenshots.mjs <çıktı-klasörü> [port]
// Önce: bun run --filter @fluxwp/panel build && (cd packages/panel && bunx vite preview --port <port>)
import { createRequire } from 'node:module';
import { mkdirSync } from 'node:fs';
const require = createRequire(import.meta.url);
const { chromium } = require('playwright');

const out = process.argv[2] || 'screens';
const port = process.argv[3] || '4173';
const prefix = process.env.SHOT_PREFIX || '';
const base = `http://localhost:${port}/`;
mkdirSync(out, { recursive: true });

const browser = await chromium.launch();
const shot = async (page, name) => page.screenshot({ path: `${out}/${prefix}${name}.png` });

async function run(scheme, locale) {
  const ctx = await browser.newContext({ viewport: { width: 1360, height: 860 }, deviceScaleFactor: 2, colorScheme: scheme, locale });
  const page = await ctx.newPage();
  // Kilitli marka: site keşfini sahte cevapla.
  await page.route('**/wp-json/', (r) => r.fulfill({ json: { name: 'Örnek Vakfı', url: 'https://example.org', namespaces: ['wp/v2', 'flxapp/v1'] } }));
  await page.goto(base);
  await page.waitForTimeout(600);
  const suffix = `-${scheme}-${locale.slice(0, 2)}`;
  await shot(page, `01-giris${suffix}`);
  const demo = page.locator('.demo-link');
  if (await demo.count()) {
    await demo.click();
    await page.waitForSelector('.card-value');
    await page.waitForTimeout(400);
    await shot(page, `02-dashboard${suffix}`);
    if (scheme === 'light' && locale.startsWith('tr')) {
      await page.keyboard.press('Control+k');
      await page.keyboard.type('gör');
      await page.waitForTimeout(200);
      await shot(page, '03-komut-paleti');
      await page.keyboard.press('Escape');
      await page.click('.page-head .btn-ghost');
      await shot(page, '05-kart-duzenle');
      await page.click('.page-head .btn-primary');
      await page.click('.nav-item:has-text("Yazılar")');
      await page.waitForTimeout(200);
      await shot(page, '06-panel-gorunumu');
      await page.click('.account-card');
      await shot(page, '07-hesap-karti');
      await page.click('.menu-pop.up .menu-item:has-text("Ayarlar"), .menu-pop.up .menu-item:has-text("Settings")');
      await page.waitForTimeout(200);
      await shot(page, '08-ayarlar');
    }
    // Yerel modüller
    const nav = (label) => page.click(`.nav-item:has-text("${label}")`);
    await nav('Onay bekleyenler'); // demo sunucusu Türkçe döner
    await page.waitForSelector('.detail-actions');
    await page.waitForTimeout(300);
    await shot(page, `10-onay${suffix}`);
    await nav('Görevler');
    await page.waitForSelector('.task-row');
    await page.click('.task-main >> nth=0');
    await page.waitForSelector('.drawer-body');
    await page.waitForTimeout(300);
    await shot(page, `11-gorevler${suffix}`);
    await nav('WhatsApp');
    await page.waitForSelector('.row-item');
    await page.click('.row-item >> nth=0');
    await page.waitForSelector('.wa-bubble');
    await page.waitForTimeout(400);
    await shot(page, `12-whatsapp${suffix}`);
  }
  await ctx.close();
}

await run('light', 'tr-TR');
await run('dark', 'tr-TR');
if (!prefix) await run('light', 'en-US');
await browser.close();
console.log('ok', out);
