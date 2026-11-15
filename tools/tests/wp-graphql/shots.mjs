// Screenshots of a wp-admin screen: desktop 1440 + mobile 390, light + dark.
// Usage: node tools/tests/wp-graphql/shots.mjs <site url> <admin path> <out dir> <name> [--wait <selector>]
// Signs in as admin/admin. Console errors are printed and make the run fail.
import { createRequire } from 'node:module';
import fs from 'node:fs';
import path from 'node:path';

const require = createRequire(import.meta.url);
const { chromium } = require('playwright');
const [url, adminPath, out, name, ...rest] = process.argv.slice(2);
const waitFor = rest[0] === '--wait' ? rest[1] : null;
fs.mkdirSync(out, { recursive: true });

const browser = await chromium.launch();
let errors = 0;
for (const scheme of ['light', 'dark']) {
  for (const [label, viewport] of [['masaustu', { width: 1440, height: 1000 }], ['mobil', { width: 390, height: 844 }]]) {
    const ctx = await browser.newContext({ viewport, colorScheme: scheme, deviceScaleFactor: 2, isMobile: label === 'mobil', hasTouch: label === 'mobil' });
    const page = await ctx.newPage();
    page.on('console', (m) => {
      if (m.type() === 'error') {
        errors++;
        console.error(`[${scheme}/${label}] console: ${m.text()}`);
      }
    });
    page.on('pageerror', (e) => {
      errors++;
      console.error(`[${scheme}/${label}] pageerror: ${e.message}`);
    });
    await page.goto(url + '/wp-login.php');
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', 'admin');
    await Promise.all([page.waitForNavigation(), page.click('#wp-submit')]);
    await page.goto(url + adminPath, { waitUntil: 'networkidle' });
    if (waitFor) await page.waitForSelector(waitFor, { timeout: 15000 });
    await page.waitForTimeout(600);
    const file = path.join(out, `${name}-${label}-${scheme === 'light' ? 'acik' : 'koyu'}.png`);
    await page.screenshot({ path: file, fullPage: true });
    console.log(file);
    await ctx.close();
  }
}
await browser.close();
process.exit(errors ? 1 : 0);
