// Shared checks for brands/<slug>/brand.json. Used by tools/fluxapp/brand.mjs (desktop hook),
// apps/mobile/scripts/brand.cjs (Expo) and the brand:new / brand:pull / brand:build scripts.
// No secrets: a brand file is names, ids and public OAuth client ids only.
const { existsSync } = require('node:fs');
const { join } = require('node:path');

const LOGIN = ['oauth', 'otp', 'apikey', 'apppass'];
const GENERIC_CLIENTS = new Set(['flx-mobil', 'flx-masaustu']);
const NAV = ['tabs', 'drawer', 'tabs+drawer'];
const HEX = /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/;
const SCHEME = /^[a-z][a-z0-9+.-]*$/;
const SLUG = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;
const ANDROID = /^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/;
const IOS = /^[a-zA-Z][\w-]*(\.[a-zA-Z][\w-]*)+$/;

const fail = (slug, msg) => {
  throw new Error(`[brand] ${slug}: ${msg}`);
};

/** Host of a URL or a bare host. Null when it cannot be parsed. */
function hostOf(input) {
  const raw = String(input ?? '').trim();
  if (!raw) return null;
  try {
    return new URL(/^https?:\/\//i.test(raw) ? raw : `https://${raw}`).host.toLowerCase();
  } catch {
    return null;
  }
}

/** Exact host or "*.example.org" (the apex itself is not included). Same rule as runtime siteAllowed. */
function hostAllowed(host, allowed) {
  return allowed.some((raw) => {
    const a = String(raw).trim().toLowerCase().replace(/^https?:\/\//, '').replace(/\/.*$/, '');
    if (a.startsWith('*.')) {
      const suffix = a.slice(1);
      const apex = a.slice(2);
      return host.endsWith(suffix) && host !== apex && host.length > apex.length;
    }
    return host === a;
  });
}

function assertMethods(slug, methods, label) {
  if (!Array.isArray(methods) || !methods.length || methods.some((m) => !LOGIN.includes(m))) fail(slug, `${label} must be oauth, otp, apikey or apppass`);
}

function assertColors(slug, colors, where) {
  if (!colors || typeof colors !== 'object') return;
  for (const [k, v] of Object.entries(colors)) {
    if (v != null && typeof v === 'string' && !HEX.test(v)) fail(slug, `${where}.${k} is not a hex color`);
  }
}

/**
 * @param {string} slug folder name
 * @param {Record<string, unknown>} b parsed brand.json
 * @param {string} [dir] brand directory; when set, icon and logo files must exist
 */
function assertBrand(slug, b, dir) {
  if (!SLUG.test(slug)) fail(slug, 'folder name must be lowercase letters, digits and single hyphens');
  if (!b || typeof b !== 'object') fail(slug, 'brand.json is not an object');
  for (const k of ['slug', 'name', 'bundleId', 'scheme', 'icon', 'logo', 'colors', 'loginMethods', 'defaultLocale', 'locales']) {
    if (b[k] === undefined || b[k] === '') fail(slug, `"${k}" missing`);
  }
  if (b.slug !== slug) fail(slug, `slug "${b.slug}" must match the folder name`);
  if (!SCHEME.test(b.scheme)) fail(slug, 'invalid scheme');
  if (!IOS.test(b.bundleId)) fail(slug, 'invalid bundleId');
  assertMethods(slug, b.loginMethods, 'loginMethods');
  if (!Array.isArray(b.locales) || !b.locales.length) fail(slug, 'locales missing');
  if (b.lockSite && !b.defaultSite) fail(slug, 'lockSite needs defaultSite');
  const colors = b.colors || {};
  for (const k of ['primary', 'primaryContrast', 'accent']) {
    if (!HEX.test(String(colors[k] || ''))) fail(slug, `colors.${k} is not a hex color`);
  }

  const allowed = Array.isArray(b.allowedSites) ? b.allowedSites : [];
  if (allowed.some((a) => typeof a !== 'string' || !String(a).trim())) fail(slug, 'allowedSites entries must be hosts');
  if (b.defaultSite && allowed.length && !hostAllowed(hostOf(b.defaultSite) || '', allowed)) fail(slug, 'allowedSites must include the defaultSite host');

  const restricted = allowed.length > 0 || !!b.lockSite;
  const mobile = b.mobile || {};
  const mobileClient = mobile.oauthClientId || '';
  const desktopClient = b.oauthClientId || '';
  if (restricted) {
    if (!mobileClient || GENERIC_CLIENTS.has(mobileClient)) fail(slug, 'a restricted brand needs its own mobile.oauthClientId (not flx-mobil)');
    if (!desktopClient || GENERIC_CLIENTS.has(desktopClient)) fail(slug, 'a restricted brand needs its own oauthClientId (not flx-masaustu)');
  }
  if (mobile.scheme && !SCHEME.test(mobile.scheme)) fail(slug, 'invalid mobile.scheme');
  if (mobile.loginMethods) assertMethods(slug, mobile.loginMethods, 'mobile.loginMethods');

  const ios = (b.ios && b.ios.bundleIdentifier) || b.bundleId;
  const pkg = (b.android && b.android.package) || ios;
  if (!IOS.test(ios)) fail(slug, 'invalid ios.bundleIdentifier');
  if (!ANDROID.test(pkg)) fail(slug, 'invalid android.package');
  if (restricted && (ios === 'com.fluxwp.app' || pkg === 'com.fluxwp.app')) fail(slug, 'a restricted brand cannot use the main bundle id com.fluxwp.app');

  if (b.shell) {
    if (b.shell.nav && !NAV.includes(b.shell.nav)) fail(slug, 'shell.nav invalid');
    if (b.shell.tabs && b.shell.tabs.length > 5) fail(slug, 'shell.tabs has more than 5 items');
  }
  if (b.theme) {
    assertColors(slug, b.theme.light, 'theme.light');
    assertColors(slug, b.theme.dark, 'theme.dark');
  }
  if (b.eas && b.eas.projectId && !/^[0-9a-f-]{36}$/i.test(String(b.eas.projectId))) fail(slug, 'eas.projectId must be a UUID or null');

  const blob = JSON.stringify(b);
  if (/PRIVATE KEY|EXPO_TOKEN|BEGIN [A-Z ]+KEY|AKIA[0-9A-Z]{16}/.test(blob)) fail(slug, 'brand.json must not contain secrets');

  if (dir) {
    for (const f of [b.icon, b.logo, b.logoDark].filter(Boolean)) {
      if (!existsSync(join(dir, f))) fail(slug, `missing file ${f}`);
    }
  }
  return { allowed, ios, pkg, mobile };
}

/** brand.json → the object the mobile app reads (current.json). */
function toMobileBrand(slug, b) {
  const { allowed, ios, pkg, mobile } = assertBrand(slug, b);
  const scheme = mobile.scheme || b.scheme;
  return {
    slug,
    name: b.name,
    shortName: b.shortName || b.name,
    bundleId: ios,
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
    loginMethods: mobile.loginMethods || b.loginMethods,
    defaultLocale: b.defaultLocale,
    locales: b.locales,
    supportUrl: b.supportUrl || null,
    updateUrl: b.updateUrl || null,
    oauthClientId: mobile.oauthClientId || 'flx-mobil',
    allowedSites: allowed,
    locked: Array.isArray(b.locked) ? b.locked : [],
    theme: b.theme || null,
    shell: b.shell || null,
    splash: mobile.splash || {},
    eas: b.eas || null,
  };
}

/** EAS profile names. The main app uses production/preview; every other brand gets its own. */
function profileNames(slug) {
  if (slug === 'flux') return { production: 'production', preview: 'preview', development: 'development' };
  return { production: `prod-${slug}`, preview: `preview-${slug}` };
}

/** Returns a new eas.json object with this brand's profiles (does not write). */
function withBrandProfiles(eas, slug) {
  const next = JSON.parse(JSON.stringify(eas));
  next.build = next.build || {};
  if (slug === 'flux') return next;
  const names = profileNames(slug);
  next.build[names.preview] = {
    extends: 'preview',
    distribution: 'internal',
    env: { FLUX_BRAND: slug },
    channel: `preview-${slug}`,
  };
  next.build[names.production] = {
    extends: 'production',
    env: { FLUX_BRAND: slug },
    channel: `production-${slug}`,
  };
  return next;
}

const androidPackage = (slug) => `com.${slug.replace(/-/g, '')}.app`;

/** Scaffold object for brand:new. Caller writes files. */
function scaffoldBrand(slug) {
  if (slug === 'flux') fail(slug, 'flux is the main FluxWP app (com.fluxwp.app) and is not scaffolded');
  if (!SLUG.test(slug)) fail(slug, 'use lowercase letters, digits and single hyphens');
  const pkg = androidPackage(slug);
  const brand = {
    slug,
    name: slug,
    shortName: slug,
    bundleId: `com.${slug.replace(/-/g, '')}.desktop`,
    scheme: `${slug}-desktop`,
    icon: 'icon.svg',
    logo: 'logo.svg',
    logoDark: 'logo-dark.svg',
    colors: { primary: '#0F766E', primaryContrast: '#FFFFFF', accent: '#F59E0B' },
    defaultSite: null,
    lockSite: false,
    loginMethods: ['oauth'],
    defaultLocale: 'tr',
    locales: ['tr', 'en'],
    supportUrl: null,
    updateUrl: null,
    oauthClientId: `flx-masaustu-${slug}`,
    appIcon: { background: null },
    ios: { bundleIdentifier: pkg, appleTeamId: null },
    android: { package: pkg },
    mobile: {
      scheme: slug,
      oauthClientId: `flx-mobil-${slug}`,
      loginMethods: ['oauth'],
      splash: { background: '#F4FBFA', backgroundDark: '#0C1414' },
    },
    eas: { projectId: null, owner: 'fluxwp', updatesChannel: `production-${slug}` },
    allowedSites: [],
    locked: [],
    theme: null,
    shell: null,
    store: { appStoreId: null, playUrl: null },
  };
  return brand;
}

const ICON_SVG = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256">
<rect width="256" height="256" fill="#0F766E"/>
<rect x="48" y="48" width="160" height="160" rx="36" fill="#FFFFFF"/>
<rect x="78" y="78" width="100" height="22" rx="6" fill="#0F766E"/>
<rect x="78" y="117" width="72" height="22" rx="6" fill="#0F766E"/>
<rect x="78" y="156" width="100" height="22" rx="6" fill="#F59E0B"/>
</svg>
`;

const logoSvg = (fill) => `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 360 80">
<rect x="0" y="8" width="64" height="64" rx="16" fill="#0F766E"/>
<rect x="14" y="24" width="36" height="8" rx="2" fill="#FFFFFF"/>
<rect x="14" y="36" width="26" height="8" rx="2" fill="#FFFFFF"/>
<rect x="14" y="48" width="36" height="8" rx="2" fill="#F59E0B"/>
<text x="80" y="54" font-family="ui-sans-serif, system-ui, sans-serif" font-size="36" font-weight="700" fill="${fill}">Brand</text>
</svg>
`;

/**
 * GET flxapp/v1/app/brand → a brand.json draft. Drops anything that looks like a secret.
 * @param {Record<string, unknown>} body
 * @param {string} slug
 */
function draftFromBrandResponse(body, slug) {
  const src = (body && body.brand_json) || {};
  const app = (body && body.flxapp_app) || {};
  const useSlug = slug || src.slug;
  if (!useSlug || !SLUG.test(useSlug)) fail(String(useSlug || '?'), 'pass --slug (lowercase)');
  const base = scaffoldBrand(useSlug);
  const brand = {
    ...base,
    ...src,
    slug: useSlug,
    icon: 'icon.svg',
    logo: 'logo.svg',
    logoDark: src.logoDark ? 'logo-dark.svg' : 'logo.svg',
    ios: { bundleIdentifier: androidPackage(useSlug), appleTeamId: null, ...(src.ios || {}) },
    android: { package: androidPackage(useSlug), ...(src.android || {}) },
    mobile: { ...base.mobile, ...(src.mobile || {}) },
    eas: {
      projectId: (src.eas && src.eas.projectId) || null,
      owner: (src.eas && src.eas.owner) || 'fluxwp',
      updatesChannel: (src.eas && src.eas.updatesChannel) || `production-${useSlug}`,
    },
    store: { appStoreId: null, playUrl: null, ...(src.store || {}) },
  };
  if (app.theme && typeof app.theme === 'object') brand.theme = app.theme;
  const mobileShell = app.shell && app.shell.mobile;
  if (mobileShell && typeof mobileShell === 'object') {
    const d = mobileShell.drawer || {};
    const t = mobileShell.topbar || {};
    brand.shell = {
      nav: mobileShell.nav || 'tabs+drawer',
      home: mobileShell.home || 'screen:core/dashboard',
      tabs: Array.isArray(mobileShell.tabs) ? mobileShell.tabs.slice(0, 5) : [],
      drawer: {
        enabled: d.enabled !== false,
        header: d.header || 'account',
        pins: d.pins || [],
        groups: d.groups || [],
        footer: d.footer || ['accounts', 'settings'],
      },
      topbar: {
        style: t.style || 'large',
        show_logo: t.show_logo !== false,
        site_switcher: t.site_switcher !== false,
        actions: t.actions || [],
      },
    };
    if (!brand.locked || !brand.locked.length) brand.locked = ['theme.light.primary', 'shell.tabs'];
  }
  delete brand.token;
  delete brand.secret;
  delete brand.password;
  delete brand.expoToken;
  const blob = JSON.stringify(brand);
  if (/PRIVATE KEY|EXPO_TOKEN|AKIA[0-9A-Z]{16}/.test(blob)) fail(useSlug, 'the site response contained a secret; nothing was written');
  return brand;
}

module.exports = {
  LOGIN,
  SLUG,
  HEX,
  fail,
  hostOf,
  hostAllowed,
  assertBrand,
  toMobileBrand,
  profileNames,
  withBrandProfiles,
  scaffoldBrand,
  draftFromBrandResponse,
  androidPackage,
  ICON_SVG,
  logoSvg,
};
