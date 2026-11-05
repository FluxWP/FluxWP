// Mobile output of the brand pipeline: brands/<slug>/brand.json (shared with the desktop build) →
// src/brand/current.json + assets/brand/*. Same validation rules as tools/fluxapp/brand.mjs, plus the
// mobile keys (ios, android, mobile.scheme, allowedSites). In the monorepo this joins tools/fluxapp/brand.mjs.
// CommonJS on purpose: app.config.ts loads it and Expo's config loader does not transpile imports.
const { copyFileSync, existsSync, mkdirSync, readFileSync, readdirSync, writeFileSync } = require('node:fs');
const { dirname, join, resolve } = require('node:path');

const app = resolve(__dirname, '..');
const repo = resolve(app, '../..');

const fail = (slug, msg) => {
  throw new Error(`[brand] ${slug}: ${msg}`);
};

const hostOf = (u) => {
  try {
    return new URL(u).host.toLowerCase();
  } catch {
    return null;
  }
};

const hostAllowed = (host, allowed) =>
  allowed.some((raw) => {
    const a = String(raw).trim().toLowerCase().replace(/^https?:\/\//, '').replace(/\/.*$/, '');
    return a.startsWith('*.') ? host.endsWith(a.slice(1)) : host === a;
  });

/** brand.json → the object the app reads (`@fluxwp/core` Brand + mobile fields). */
function loadBrand(slugInput) {
  const slug = slugInput || 'flux';
  const dir = join(repo, 'brands', slug);
  const file = join(dir, 'brand.json');
  if (!existsSync(file)) fail(slug, `brands/${slug}/brand.json not found`);
  const b = JSON.parse(readFileSync(file, 'utf8'));
  for (const k of ['slug', 'name', 'icon', 'logo', 'colors', 'loginMethods', 'defaultLocale', 'locales']) {
    if (b[k] === undefined) fail(slug, `"${k}" missing`);
  }
  if (b.slug !== slug) fail(slug, `slug "${b.slug}" must match the folder name`);
  if (b.lockSite && !b.defaultSite) fail(slug, 'lockSite needs defaultSite');

  const m = b.mobile || {};
  const scheme = m.scheme || b.scheme;
  const bundle = (b.ios && b.ios.bundleIdentifier) || b.bundleId;
  const pkg = (b.android && b.android.package) || bundle;
  const methods = m.loginMethods || b.loginMethods;
  if (!/^[a-z][a-z0-9+.-]*$/.test(scheme)) fail(slug, 'invalid mobile scheme');
  if (!/^[a-zA-Z][\w-]*(\.[a-zA-Z][\w-]*)+$/.test(bundle)) fail(slug, 'invalid ios.bundleIdentifier');
  if (!/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/.test(pkg)) fail(slug, 'invalid android.package');
  if (!methods.length || methods.some((x) => !['oauth', 'otp', 'apikey', 'apppass'].includes(x))) fail(slug, 'invalid loginMethods');
  const allowed = Array.isArray(b.allowedSites) ? b.allowedSites : [];
  if (allowed.length && b.defaultSite && !hostAllowed(hostOf(b.defaultSite) || '', allowed)) fail(slug, 'allowedSites must include the defaultSite host');

  const brand = {
    slug,
    name: b.name,
    shortName: b.shortName || b.name,
    bundleId: bundle,
    androidPackage: pkg,
    scheme,
    icon: 'brand/icon.svg',
    logo: 'brand/logo.svg',
    logoDark: 'brand/logo-dark.svg',
    logoFile: b.logo,
    logoDarkFile: b.logoDark || b.logo,
    colors: b.colors,
    defaultSite: b.defaultSite || null,
    lockSite: !!b.lockSite,
    loginMethods: methods,
    defaultLocale: b.defaultLocale,
    locales: b.locales,
    supportUrl: b.supportUrl || null,
    updateUrl: b.updateUrl || null,
    oauthClientId: m.oauthClientId || 'flx-mobil',
    allowedSites: allowed,
    locked: Array.isArray(b.locked) ? b.locked : [],
    theme: b.theme || null,
    splash: m.splash || {},
    eas: b.eas || null,
  };
  return { brand, dir };
}

const writeIfChanged = (p, data) => {
  mkdirSync(dirname(p), { recursive: true });
  if (existsSync(p) && readFileSync(p, 'utf8') === data) return;
  writeFileSync(p, data);
};

const ASSETS = ['icon.png', 'adaptive-icon.png', 'adaptive-monochrome.png', 'splash.png', 'favicon.png', 'notification.png'];

/** Writes the generated files; returns the brand for app.config.ts. */
function applyBrand(slugInput) {
  const { brand, dir } = loadBrand(slugInput);
  const json = JSON.stringify(brand, null, 2) + '\n';
  writeIfChanged(join(app, 'src/brand/current.json'), json);
  const svg = (f) => (f && existsSync(join(dir, f)) ? readFileSync(join(dir, f), 'utf8').replace(/<metadata>[\s\S]*?<\/metadata>/g, '').replace(/\s+xmlns:c2pa="[^"]*"/g, '') : null);
  const logo = svg(brand.logoFile) || '';
  writeIfChanged(join(app, 'src/brand/logo.json'), JSON.stringify({ light: logo, dark: svg(brand.logoDarkFile) || logo }) + '\n');
  const src = join(dir, 'mobile');
  const out = join(app, 'assets/brand');
  mkdirSync(out, { recursive: true });
  for (const f of ASSETS) {
    if (!existsSync(join(src, f))) fail(brand.slug, `brands/${brand.slug}/mobile/${f} missing (run: bun run icons)`);
    copyFileSync(join(src, f), join(out, f));
  }
  for (const f of readdirSync(dir).filter((x) => x.endsWith('.svg'))) copyFileSync(join(dir, f), join(out, f));
  return brand;
}

module.exports = { loadBrand, applyBrand };

if (require.main === module) {
  const b = applyBrand(process.env.FLUX_BRAND);
  console.log(`[brand] ${b.name} (${b.slug}) → apps/mobile`);
}
