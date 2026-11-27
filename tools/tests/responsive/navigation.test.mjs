import assert from 'node:assert/strict';
import { existsSync } from 'node:fs';
import { test } from 'node:test';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

test('selected tabs remain visible on resize, selection changes and root replacement', async () => {
  const executablePath = process.env.PW_CHROMIUM || (existsSync('/usr/bin/chromium') ? '/usr/bin/chromium' : undefined);
  const browser = await chromium.launch({ executablePath, args: ['--no-sandbox'] });
  try {
    const page = await browser.newPage({ viewport: { width: 1440, height: 800 } });
    await page.setContent(`<style>
      body { margin: 0; min-height: 2000px; }
      .flx-screen { width: min(900px,100%); margin-top: 500px; }
      .flx-tabs { display:flex; overflow-x:auto; }
      button { flex:0 0 150px; }
    </style><main class="flx-screen"><nav class="flx-tabs">${Array.from({ length: 12 }, (_, i) =>
      `<button ${i === 11 ? 'aria-current="page"' : ''}>Tab ${i}</button>`).join('')}</nav></main>`);
    await page.addScriptTag({ path: fileURLToPath(new URL('../../../plugins/flx-graphql/assets/js/responsive.js', import.meta.url)) });
    async function visible() {
      await page.waitForFunction(() => {
        const nav = document.querySelector('.flx-tabs').getBoundingClientRect();
        const active = document.querySelector('[aria-current="page"]').getBoundingClientRect();
        return active.left >= nav.left - 1 && active.right <= nav.right + 1;
      });
    }
    await visible();
    await page.evaluate(() => window.scrollTo(0, 400));
    await page.setViewportSize({ width: 320, height: 800 });
    await visible();
    assert.equal(await page.evaluate(() => window.scrollY), 400);
    await page.evaluate(() => {
      const first = document.querySelector('button');
      first.focus({ preventScroll: true });
      document.querySelector('[aria-current]').removeAttribute('aria-current');
      first.setAttribute('aria-current', 'page');
    });
    await visible();
    assert.equal(await page.locator('button').first().evaluate((element) => element === document.activeElement), true);
    await page.evaluate(() => {
      const root = document.querySelector('.flx-screen');
      const replacement = root.cloneNode(true);
      replacement.querySelector('[aria-current]').removeAttribute('aria-current');
      replacement.querySelector('button:last-child').setAttribute('aria-current', 'page');
      root.replaceWith(replacement);
    });
    await visible();
    assert.equal(await page.evaluate(() => window.scrollY), 400);
  } finally {
    await browser.close();
  }
});
