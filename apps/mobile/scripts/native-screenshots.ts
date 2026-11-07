#!/usr/bin/env bun
// Native screen shots at 390×844, light and dark, after the demo sign-in.
//   bunx serve -s dist -l 8090
//   bun scripts/native-screenshots.ts http://127.0.0.1:8090 <out-dir>
import { chromium, type Page } from 'playwright-core';
import { existsSync, mkdirSync } from 'node:fs';
import { join, resolve } from 'node:path';

const base = process.argv[2] ?? 'http://127.0.0.1:8090';
const out = resolve(process.argv[3] ?? 'screenshots-native');
mkdirSync(out, { recursive: true });
const executablePath = process.env.CHROME_PATH ?? ['/usr/local/bin/google-chrome', '/usr/bin/google-chrome'].find(existsSync);

const screens: [id: string, testId: string, text: string][] = [
  ['flxc-inbox', 'screen-inbox', 'Ayşe Kaya'],
  ['flxg-tasks', 'screen-tasks', 'Fidan'],
  ['flxb-review', 'screen-review', 'Ayşe Kaya'],
  ['core-post', 'screen-post', 'Başlık'],
  ['core-media', 'screen-media', 'Galeri'],
  ['flxsm-compose', 'screen-social', 'Örnek Vakfı'],
  ['flx-studio', 'screen-studio', 'Üret'],
  ['flx-ai', 'screen-ai', 'Fidan dikim duyurusu'],
  ['flx-tv-remote', 'screen-tv', 'Salon TV'],
];

const browser = await chromium.launch({ executablePath });
const errors: string[] = [];

async function shot(page: Page, name: string) {
  await page.waitForTimeout(400);
  const file = join(out, `${name}.png`);
  await page.screenshot({ path: file });
  if (!existsSync(file)) throw new Error('missing ' + file);
  console.log('[shot]', file);
}

for (const scheme of ['light', 'dark'] as const) {
  const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, colorScheme: scheme, locale: 'tr-TR', isMobile: true, hasTouch: true });
  const page = await ctx.newPage();
  page.on('pageerror', (e) => errors.push(`${scheme} ${e.message}`));
  await page.goto(`${base}/add-site`);
  await page.getByTestId('demo').click();
  await page.getByText('Kişiler', { exact: true }).first().waitFor({ timeout: 20_000 });
  const home = new URL(page.url());
  const account = home.pathname.split('/').filter(Boolean)[0];
  if (!account) throw new Error('no account in ' + page.url());
  for (const [id, testId, text] of screens) {
    await page.goto(`${base}/${account}/n/${id}`);
    await page.getByTestId(testId).waitFor({ timeout: 15_000 });
    await page.getByText(text, { exact: false }).first().waitFor({ timeout: 15_000 });
    await shot(page, `${id}-${scheme}`);
  }
  await ctx.close();
}
await browser.close();
if (errors.length) {
  console.log('[errors]');
  for (const e of [...new Set(errors)]) console.log(' ', e.slice(0, 300));
}
