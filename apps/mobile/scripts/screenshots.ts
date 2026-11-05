#!/usr/bin/env bun
// Web preview screenshots (mobile 390×844, light + dark) with the demo account: add site, shell + tabs,
// drawer, list, detail, webview fallback, account switcher. Serve the export first:
//   bunx expo export --platform web --output-dir dist && bunx serve -s dist -l 8090
//   bun scripts/screenshots.ts [base-url] [out-dir]
import { chromium, type Page } from 'playwright-core';
import { existsSync, mkdirSync } from 'node:fs';
import { join, resolve } from 'node:path';

const base = process.argv[2] ?? 'http://127.0.0.1:8090';
const out = resolve(process.argv[3] ?? 'screenshots');
mkdirSync(out, { recursive: true });
const executablePath = process.env.CHROME_PATH ?? ['/usr/local/bin/google-chrome', '/usr/bin/google-chrome', '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'].find(existsSync);

const errors: string[] = [];
const browser = await chromium.launch({ executablePath });

async function shot(page: Page, name: string) {
  await page.waitForTimeout(700);
  await page.screenshot({ path: join(out, `${name}.png`) });
  console.log(`[shot] ${name}.png`);
}

for (const scheme of ['light', 'dark'] as const) {
  const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, colorScheme: scheme, locale: 'tr-TR', isMobile: true, hasTouch: true });
  const page = await ctx.newPage();
  page.on('pageerror', (e) => errors.push(`${scheme} pageerror: ${e.message}`));
  page.on('console', (m) => m.type() === 'error' && errors.push(`${scheme} console: ${m.text().slice(0, 300)}`));

  await page.goto(`${base}/add-site`);
  await page.getByTestId('site-url').waitFor();
  await shot(page, `add-site-${scheme}`);
  await page.getByTestId('site-url').fill('ornek-site.org');
  await shot(page, `add-site-filled-${scheme}`);

  await page.getByTestId('demo').click();
  await page.getByText('Kişiler', { exact: true }).first().waitFor({ timeout: 20_000 });
  await page.waitForTimeout(1200);
  await shot(page, `shell-home-tabs-${scheme}`);

  await page.getByTestId('open-drawer').filter({ visible: true }).first().click();
  await page.waitForTimeout(800);
  await shot(page, `shell-drawer-${scheme}`);
  // Tap the dimmed area to close, like a user would.
  await page.mouse.click(375, 420);
  await page.waitForTimeout(900);

  await page.getByRole('tab', { name: 'Kişiler' }).click();
  await page.getByText('Ayşe Kaya').first().waitFor({ timeout: 15_000 });
  await shot(page, `list-crm-contacts-${scheme}`);

  await page.getByRole('tab', { name: 'Yazılar' }).click();
  await page.getByText('Fidan dikim şenliği', { exact: false }).first().waitFor({ timeout: 15_000 });
  await shot(page, `list-posts-${scheme}`);
  const row = await page.getByText('Fidan dikim şenliği', { exact: false }).first().boundingBox();
  await page.mouse.click(row!.x + 40, row!.y + 10);
  await page.getByText('Ayrıntılar').first().waitFor({ timeout: 15_000 });
  await shot(page, `detail-post-${scheme}`);

  await page.getByRole('tab', { name: 'WhatsApp' }).click();
  await page.waitForTimeout(1500);
  await shot(page, `webview-fallback-${scheme}`);

  await page.getByRole('tab', { name: 'Başlangıç' }).click();
  await page.waitForTimeout(600);
  await page.getByTestId('site-switcher').filter({ visible: true }).first().click();
  await page.waitForTimeout(700);
  await shot(page, `account-switcher-${scheme}`);
  await ctx.close();
}
await browser.close();
if (errors.length) {
  console.log('[errors]');
  for (const e of [...new Set(errors)]) console.log(' ', e);
}
