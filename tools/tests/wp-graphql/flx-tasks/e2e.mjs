// Flux Tasks pilot end to end in wp-admin: the new view (wp-embed React screen) opens a task and
// saves through GraphQL. Prints the GraphQL operations the page sent; fails on console errors or
// when the task screen did not use GraphQL. Screenshots: desktop 1440 / mobile 390, light / dark.
// Then the pages the classic app has (document editor, usage dashboard, settings, profile) on the same
// screen; on desktop it types into the document and checks the text is saved.
// Usage: node tools/tests/wp-graphql/flx-tasks/e2e.mjs <site url> <out dir> <task title> [document title]
import { createRequire } from 'node:module';
import fs from 'node:fs';
import path from 'node:path';

const require = createRequire(import.meta.url);
const { chromium } = require('playwright');
const [url, out, title, docTitle] = process.argv.slice(2);
fs.mkdirSync(out, { recursive: true });

const browser = await chromium.launch(process.env.PW_CHROMIUM ? { executablePath: process.env.PW_CHROMIUM } : {});
let errors = 0;
const ops = new Set();
for (const scheme of ['light', 'dark']) {
  for (const [label, viewport] of [['masaustu', { width: 1440, height: 1000 }], ['mobil', { width: 390, height: 844 }]]) {
    const ctx = await browser.newContext({ viewport, colorScheme: scheme, deviceScaleFactor: 2, isMobile: label === 'mobil', hasTouch: label === 'mobil' });
    const page = await ctx.newPage();
    const tag = `[${scheme}/${label}]`;
    page.on('console', (m) => {
      if (m.type() === 'error' && !/ERR_TUNNEL_CONNECTION_FAILED|ERR_NAME_NOT_RESOLVED/.test(m.text())) {
        errors++;
        console.error(`${tag} console: ${m.text()}`);
      }
    });
    page.on('pageerror', (e) => {
      errors++;
      console.error(`${tag} pageerror: ${e.message}`);
    });
    page.on('request', (r) => {
      const op = r.headers()['x-flux-graphql-operation'];
      if (op) ops.add(`${r.method()} ${op}`);
    });
    page.on('response', async (r) => {
      if (r.headers()['content-type']?.includes('json') && r.url().includes('/graphql')) {
        const body = await r.json().catch(() => null);
        if (body?.errors?.length) {
          errors++;
          console.error(`${tag} graphql error: ${JSON.stringify(body.errors[0])}`);
        }
      }
    });
    await page.goto(url + '/wp-login.php');
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', 'admin');
    await Promise.all([page.waitForNavigation(), page.click('#wp-submit')]);
    await page.goto(url + '/wp-admin/admin.php?page=flx-tasks', { waitUntil: 'networkidle' });
    const shot = (n) => page.screenshot({ path: path.join(out, `${n}-${label}-${scheme === 'light' ? 'acik' : 'koyu'}.png`) });
    await page.getByText(title, { exact: true }).first().waitFor({ timeout: 20000 });
    await page.waitForTimeout(500);
    await shot('liste');
    await page.getByText(title, { exact: true }).first().click();
    if (label === 'mobil') await page.getByText(/^Yorumlar/).first().click();
    await page
      .getByText('Görseller hazır, onay bekliyor.')
      .first()
      .waitFor({ timeout: 20000 })
      .catch(async (e) => {
        await shot('hata');
        throw e;
      });
    await page.waitForTimeout(500);
    await shot('gorev');
    await page.keyboard.press('Escape');
    // Classic pages on the shared screen.
    for (const [hash, name, text] of [['#/dashboard', 'pano', 'Dosya depolama'], ['#/settings', 'ayarlar', 'Sistem kontrolü'], ['#/profile', 'profil', 'Klasik görünüm']]) {
      await page.evaluate((h) => (location.hash = h), hash);
      await page.getByText(text).first().waitFor({ timeout: 20000 });
      await page.waitForTimeout(400);
      await shot(name);
    }
    if (docTitle) {
      if (label === 'mobil') {
        await page.locator('.flxg-menu-btn').first().click();
        await page.waitForTimeout(400);
        await shot('menu');
      }
      // Open every project in the sidebar tree, then the document.
      const closed = page.locator('.flxg-space.is-collapsed .flxg-caret');
      for (let i = 0; i < 20 && (await closed.count()); i++) await closed.first().click();
      await page.getByText(docTitle, { exact: true }).first().click();
      const ed = page.locator('.flxg-doc-ed[contenteditable]').first();
      await ed.waitFor({ timeout: 20000 });
      if (label === 'masaustu' && scheme === 'light') {
        const line = 'Toplantı notu ' + Date.now();
        await ed.click();
        const saved = page.waitForResponse((r) => r.url().includes('/sync') && r.request().method() === 'POST' && (r.request().postData() ?? '').includes('Toplantı notu'), { timeout: 20000 });
        await page.keyboard.type(line);
        if (!(await saved).ok()) throw new Error('document sync failed');
        await page.reload({ waitUntil: 'networkidle' });
        await page.getByText(line).first().waitFor({ timeout: 20000 });
      }
      await page.waitForTimeout(900);
      await shot('belge');
    }
    console.log(`${tag} ok`);
    await ctx.close();
  }
}
await browser.close();
console.log('GraphQL operations: ' + [...ops].sort().join(', '));
if (![...ops].some((o) => o.endsWith('FlxTaskDetail'))) {
  console.error('the task screen did not use GraphQL');
  errors++;
}
process.exit(errors ? 1 : 0);
