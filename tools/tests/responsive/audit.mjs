// Audit real wp-admin pages without submitting forms. Writes JSON findings and screenshots.
// WP_ADMIN_USER / WP_ADMIN_PASSWORD authenticate the disposable WordPress fixture.
import { chromium } from 'playwright';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import path from 'node:path';
import { parseArgs } from 'node:util';

const { values } = parseArgs({
  options: {
    url: { type: 'string' },
    inventory: { type: 'string' },
    out: { type: 'string' },
    widths: { type: 'string', default: '320,390,768,1024,1440' },
    pages: { type: 'string' },
    tabs: { type: 'boolean', default: false },
    scheme: { type: 'string', default: 'light' },
  },
});
if (!values.url || !values.inventory || !values.out) {
  throw new Error('Usage: audit.mjs --url URL --inventory inventory.json --out DIRECTORY [--widths 320,390,768,1024,1440] [--tabs]');
}
const base = new URL(values.url);
const widths = values.widths.split(',').map(Number);
if (widths.some((width) => !Number.isInteger(width) || width < 280)) throw new Error('Invalid viewport widths');
const out = path.resolve(values.out);
await mkdir(out, { recursive: true });
const inventory = JSON.parse(await readFile(values.inventory, 'utf8'));
const screens = new Map(inventory.screens.map((screen) => [screen.page, screen]));
const routes = Object.keys(inventory.pages ?? {}).length ? Object.keys(inventory.pages) : [...screens.keys()];
const requested = values.pages?.split(',');
const unknown = requested?.filter((route) => !routes.includes(route));
if (unknown?.length) throw new Error('Unknown menu routes: ' + unknown.join(', '));
const pages = requested ? routes.filter((route) => requested.includes(route)) : routes;
const browser = await chromium.launch({
  ...(process.env.PW_CHROMIUM || existsSync('/usr/bin/chromium') ? { executablePath: process.env.PW_CHROMIUM || '/usr/bin/chromium' } : {}),
  args: ['--no-sandbox'],
});
const results = [];
let reportWrite = Promise.resolve();
const hasOverflow = (view) => view.documentWidth > view.width + 2 || view.outside.some((element) => element.right > view.viewport + 2 || element.left < -2);
try {
  const auth = await browser.newContext();
  const login = await auth.newPage();
  await login.goto(new URL('/wp-login.php', base).href);
  await login.locator('#user_login').fill(process.env.WP_ADMIN_USER || 'admin');
  await login.locator('#user_pass').fill(process.env.WP_ADMIN_PASSWORD || 'admin12345');
  await login.locator('#wp-submit').click();
  await login.waitForURL('**/wp-admin/**');
  const storageState = await auth.storageState();
  await auth.close();
  let next = 0;
  async function worker() {
    const context = await browser.newContext({ storageState, colorScheme: values.scheme, viewport: { width: 1440, height: 1000 } });
    // Remote providers and external media are irrelevant to fixture layout checks.
    await context.route('**/*', (route) => {
      const requestUrl = new URL(route.request().url());
      return requestUrl.protocol === 'data:' || requestUrl.origin === base.origin ? route.continue() : route.abort();
    });
    while (next < pages.length) {
      const route = pages[next++];
      const page = await context.newPage();
      page.setDefaultTimeout(12000);
      const errors = [];
      page.on('pageerror', (error) => errors.push(error.message));
      const record = { page: route, module: screens.get(route)?.module ?? 'classic', errors: [], views: [] };
      try {
        const menuUrl = inventory.urls?.[route] || screens.get(route)?.url;
        const target = menuUrl ? new URL(menuUrl, base) : new URL('/wp-admin/admin.php?page=' + route, base);
        if (target.origin !== base.origin) throw new Error('The inventory contains a route outside the fixture origin');
        record.url = target.href;
        const response = await page.goto(target.href, { waitUntil: 'domcontentloaded', timeout: 45000 });
        record.http = response?.status();
        if (record.http >= 400) throw new Error('Admin route returned HTTP ' + record.http);
        await page.locator('.flx-screen:visible,#flxk-ob:visible,#wpbody-content:visible').first().waitFor({ timeout: 20000 });
        await page.waitForTimeout(900);
        await page.evaluate((scheme) => {
          document.documentElement.classList.toggle('flxa-dark', scheme === 'dark');
          document.documentElement.classList.toggle('flxa-light', scheme === 'light');
        }, values.scheme);
        const isScreen = screens.has(route);
        if (isScreen) {
          await page.waitForFunction(() => {
            const screen = document.querySelector('.flx-screen');
            return screen && screen.textContent.trim().length > 40 && !screen.querySelector('.loading');
          }, undefined, { timeout: 10000 }).catch(() => {});
        }
        async function capture(view) {
          for (const width of widths) {
            await page.evaluate(async () => {
              // A browser view transition is allowed to abort on resize. Measure the settled layout.
              const transition = document.__reactViewTransition;
              if (transition) await Promise.race([
                transition.finished.catch(() => {}),
                new Promise((_, reject) => setTimeout(() => reject(new Error('View transition did not settle')), 5000)),
              ]);
            });
            await page.setViewportSize({ width, height: 1000 });
            await page.waitForTimeout(500);
            const measured = await page.evaluate(() => {
              const root = document.querySelector('#flxk-ob') ?? document.querySelector('.flx-screen') ?? document.querySelector('#wpbody-content > .wrap') ?? document.querySelector('#wpbody-content');
              const bounds = root.getBoundingClientRect();
              const viewport = document.documentElement.clientWidth;
              const tabs = [...root.querySelectorAll('.flx-tabs')].map((nav) => {
                const active = nav.querySelector('[aria-current="page"]');
                if (!active || !nav.clientWidth) return null;
                const navRect = nav.getBoundingClientRect(), activeRect = active.getBoundingClientRect();
                return { label: active.textContent.trim(), visible: activeRect.left >= navRect.left - 2 && activeRect.right <= navRect.right + 2 };
              }).filter(Boolean);
              const outside = [];
              const elements = root.querySelectorAll('*');
              for (const element of elements) {
                if (['SCRIPT', 'STYLE', 'SVG', 'PATH', 'OPTION'].includes(element.tagName)) continue;
                const rect = element.getBoundingClientRect();
                if (!rect.width || !rect.height || rect.right <= bounds.right + 2 && rect.left >= bounds.left - 2) continue;
                if (getComputedStyle(element).visibility === 'hidden' || rect.right <= 0) continue;
                let parent = element.parentElement;
                let clipped = false;
                while (parent && parent !== root) {
                  const style = getComputedStyle(parent);
                  if (['auto', 'scroll', 'hidden', 'clip'].includes(style.overflowX)) { clipped = true; break; }
                  parent = parent.parentElement;
                }
                if (clipped) continue;
                outside.push({
                  tag: element.tagName.toLowerCase(),
                  class: String(element.className).slice(0, 160),
                  text: element.textContent.trim().replace(/\s+/g, ' ').slice(0, 100),
                  left: Math.round(rect.left),
                  right: Math.round(rect.right),
                  width: Math.round(rect.width),
                });
                if (outside.length === 12) break;
              }
              return {
                viewport,
                tabs,
                documentWidth: document.documentElement.scrollWidth,
                rootWidth: Math.round(bounds.width),
                rootScrollWidth: root.scrollWidth,
                heading: root.querySelector('h1')?.textContent,
                text: root.textContent.trim().replace(/\s+/g, ' ').slice(0, 170),
                outside,
              };
            });
            const finding = { view, width, ...measured };
            record.views.push(finding);
            if (width === 390 || hasOverflow(finding)) {
              await page.screenshot({ path: path.join(out, encodeURIComponent(route) + '-' + view + '-' + width + '.png'), fullPage: false, timeout: 12000 });
            }
          }
        }
        await capture(values.scheme);
        if (values.tabs) {
          const tabs = page.locator('.flx-page > .flx-tabs > button');
          const count = await tabs.count();
          for (let index = 0; index < count; index++) {
            const tab = tabs.nth(index);
            const label = (await tab.textContent())?.trim();
            if (await tab.getAttribute('aria-current') === 'page') continue;
            const previousUrl = page.url();
            await Promise.all([
              page.waitForURL((url) => url.href !== previousUrl, { timeout: 1800 }).catch((error) => {
                if (error.name !== 'TimeoutError') throw error;
              }),
              tab.click(),
            ]);
            await page.waitForTimeout(350);
            await capture(values.scheme + '-tab-' + index);
            record.views[record.views.length - 1].tab = label;
            // Classic island tabs navigate to separately inventoried menu routes.
            if (page.url() !== previousUrl) break;
          }
        }
      } catch (error) {
        record.error = error.message;
      }
      record.errors = [...new Set(errors)];
      await page.close();
      results.push(record);
      reportWrite = reportWrite.then(() => writeFile(path.join(out, 'audit.json'), JSON.stringify({ base: base.href, widths, results }, null, 2) + '\n'));
      await reportWrite;
      const issues = record.views.filter(hasOverflow);
      console.log(JSON.stringify({ page: route, http: record.http, views: record.views.length, overflow: issues.length, errors: record.errors, error: record.error }));
    }
    await context.close();
  }
  // Keep one page foregrounded: background tabs can suspend browser view transitions.
  await worker();
} finally {
  await browser.close();
}
console.log('Audited ' + results.length + ' admin routes. Report: ' + path.join(out, 'audit.json'));
const failures = results.filter((record) => record.error || record.errors.length || record.views.some((view) => hasOverflow(view) || view.tabs?.some((tab) => !tab.visible)));
if (failures.length) {
  console.error(failures.length + ' routes need inspection.');
  process.exitCode = 1;
}
