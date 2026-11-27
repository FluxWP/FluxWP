# Responsive wp-admin audit

Run this against a disposable, seeded WordPress installation with real admin styles, Flux GraphQL, WooCommerce, the Flux theme and the plugins to inspect. Do not use a production site: opening admin screens can run plugin initialization even though this runner never submits forms. Existing seed scripts are under `tools/readme-media/seed/`.

Install workspace dependencies with `bun install --frozen-lockfile`. Use Playwright's Chromium or set `PW_CHROMIUM` to an installed Chromium binary. PHP must be able to access the fixture files and database.

Run `node --test tools/tests/responsive/navigation.test.mjs` for the shared tab behavior regression test (resize, changed selection, replaced roots, preserved focus and vertical scroll).

```bash
php tools/tests/responsive/inventory.php /absolute/wordpress/root > /tmp/flux-inventory.json
export WP_ADMIN_USER=admin
export WP_ADMIN_PASSWORD='fixture-password'
node tools/tests/responsive/audit.mjs \
  --url http://127.0.0.1:8877 \
  --inventory /tmp/flux-inventory.json \
  --out /tmp/flux-responsive-light
node tools/tests/responsive/audit.mjs \
  --url http://127.0.0.1:8877 \
  --inventory /tmp/flux-inventory.json \
  --out /tmp/flux-responsive-dark \
  --scheme dark --widths 390,1440
```

Default widths are 320, 390, 768, 1024 and 1440 CSS pixels, at 1000 pixels high. Each page shrinks and grows in the same browser tab. `--pages slug,other-slug` narrows the run; `--tabs` also clicks top-level screen tabs. A tab that navigates to another menu route is captured once, then left to that route's normal inventory coverage. No save, flush, payment, send or installation actions are clicked.

The inventory uses actual visible menu routes and their canonical URLs (including `themes.php` routes). Registered but unavailable feature routes are not assumed accessible. Activate additional presets or enable optional features in separate fixtures to audit those configurations.

The runner writes `audit.json`, mobile screenshots and screenshots for detected overflow. It exits nonzero for HTTP failures, uncaught JavaScript errors, page/control overflow or a selected tab outside its visible strip. Locally scrolling tables, clipped content and closed off-canvas drawers are allowed. Read screenshots as well: pixel bounds alone cannot detect poor hierarchy, truncated text, missing states or inaccessible controls. Screenshots are evidence, not golden-image comparisons.

Remote providers and media are blocked in the browser. Mail and server-side external HTTP must also be disabled in the fixture. This checks browser layout, not external provider functionality or native iOS/Android behavior. Do not commit authentication state, screenshots, fixture databases or generated audit results.

For package smoke tests with a separate CLI updater and PHP development server, disable OPcache in that server. The updater's CLI process cannot invalidate another process's cached PHP files; otherwise an immediate upgrade check can mix cached previous files with new files.
