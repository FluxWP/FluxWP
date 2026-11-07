import { mkdtempSync, mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { afterAll, describe, expect, test } from 'bun:test';
import { DEFAULT_CONFIG, mergeConfig } from '../../packages/manifest/src/config.ts';
import { brandGate } from '../../packages/runtime/src/brand-gate.ts';
import { createBrand } from './brand-new.mjs';

const require = createRequire(import.meta.url);
const { loadBrand } = require('../../apps/mobile/scripts/brand.cjs');
const { assertBrand, draftFromBrandResponse, hostAllowed } = require('./brand-lib.cjs');

describe('brand build allow-list', () => {
  test('example brand rejects a site that is not on its list', async () => {
    const { brand } = loadBrand('example');
    expect(brand.scheme).toBe('example');
    expect(brand.bundleId).toBe('org.example.app');
    expect(brand.oauthClientId).toBe('flx-mobil-example');
    expect(brand.allowedSites).toEqual(['north.example.org', 'south.example.org']);
    const g = brandGate(brand);
    expect(g.locked).toBe(false);
    expect(g.allowed('https://north.example.org')).toBe(true);
    expect(g.allowed('https://south.example.org/wp-admin/')).toBe(true);
    expect(g.allowed('https://east.example.org')).toBe(false);
    expect(g.allowed('https://north.example.org.evil.com')).toBe(false);
    await expect(g.findSite('https://evil.example.com')).rejects.toThrow('site_not_allowed');
  });

  test('the main FluxWP brand stays one app and accepts any site', () => {
    const { brand } = loadBrand('flux');
    expect(brand.bundleId).toBe('com.fluxwp.app');
    expect(brand.androidPackage).toBe('com.fluxwp.app');
    expect(brand.scheme).toBe('fluxwp');
    expect(brand.oauthClientId).toBe('flx-mobil');
    expect(brand.allowedSites).toEqual([]);
    expect(brand.lockSite).toBe(false);
    const g = brandGate(brand);
    expect(g.locked).toBe(false);
    expect(g.allowed('https://anywhere.example')).toBe(true);
  });

  test('locked theme and tabs from the example brand win over the site', () => {
    const { brand } = loadBrand('example');
    const merged = mergeConfig({
      brand: { theme: brand.theme ?? undefined, shell: brand.shell ?? undefined, locked: brand.locked },
      site: {
        ...DEFAULT_CONFIG,
        theme: { light: { primary: '#FF0000' }, dark: { primary: '#FF0000' } },
        shell: { ...DEFAULT_CONFIG.shell, tabs: [{ id: 'x', target: 'more', icon: 'menu', label: 'X' }] },
      },
    });
    expect(merged.theme?.light?.primary).toBe('#0F766E');
    expect(merged.theme?.dark?.primary).toBe('#2DD4BF');
    expect(merged.shell.tabs.map((t) => t.id)).toEqual(['home', 'posts', 'more']);
  });
});

describe('brand files', () => {
  test('wildcard hosts do not include the apex or a lookalike', () => {
    expect(hostAllowed('www.example.org', ['*.example.org'])).toBe(true);
    expect(hostAllowed('example.org', ['*.example.org'])).toBe(false);
    expect(hostAllowed('notexample.org', ['example.org'])).toBe(false);
  });

  test('a restricted brand cannot reuse the main client or bundle id', () => {
    const { raw } = loadBrand('example');
    expect(() => assertBrand('example', { ...raw, mobile: { ...raw.mobile, oauthClientId: 'flx-mobil' } })).toThrow(/oauthClientId/);
    expect(() => assertBrand('example', { ...raw, ios: { bundleIdentifier: 'com.fluxwp.app' }, android: { package: 'com.fluxwp.app' } })).toThrow(/com\.fluxwp\.app/);
    expect(() => assertBrand('example', { ...raw, name: 'EXPO_TOKEN=nope' })).toThrow(/secret/);
  });

  test('pulling a site draft keeps the allow-list and drops secrets', () => {
    const draft = draftFromBrandResponse(
      {
        brand_json: {
          slug: 'northwind',
          name: 'Northwind',
          scheme: 'northwind-desktop',
          oauthClientId: 'flx-masaustu-northwind',
          colors: { primary: '#112233', primaryContrast: '#FFFFFF', accent: '#F59E0B' },
          defaultSite: 'https://northwind.example.org',
          allowedSites: ['northwind.example.org'],
          lockSite: true,
          loginMethods: ['oauth'],
          defaultLocale: 'tr',
          locales: ['tr', 'en'],
          mobile: { scheme: 'northwind', oauthClientId: 'flx-mobil-northwind' },
        },
        flxapp_app: {
          theme: { light: { primary: '#112233' } },
          shell: { mobile: { nav: 'tabs', home: 'screen:core/dashboard', tabs: [{ id: 'home', target: 'screen:core/dashboard', icon: 'home' }] } },
        },
        app_clients: [{ id: 'flx-mobil-northwind', name: 'Northwind', platform: 'mobile', redirect_uris: ['northwind://oauth/callback'] }],
      },
      'northwind',
    );
    expect(draft.allowedSites).toEqual(['northwind.example.org']);
    expect(draft.lockSite).toBe(true);
    expect(draft.mobile.oauthClientId).toBe('flx-mobil-northwind');
    expect(draft.shell.nav).toBe('tabs');
    expect(draft.theme.light.primary).toBe('#112233');
    expect(JSON.stringify(draft)).not.toMatch(/PRIVATE KEY|EXPO_TOKEN|password/);
    expect(() => draftFromBrandResponse({ brand_json: { slug: 'acme', name: 'EXPO_TOKEN=abc' } }, 'acme')).toThrow(/secret/);
  });

  const temps: string[] = [];
  afterAll(() => {
    for (const d of temps) rmSync(d, { recursive: true, force: true });
  });

  test('brand:new scaffolds a folder and EAS profiles', () => {
    const rootDir = mkdtempSync(join(tmpdir(), 'flx-brand-'));
    temps.push(rootDir);
    mkdirSync(join(rootDir, 'apps/mobile'), { recursive: true });
    writeFileSync(join(rootDir, 'apps/mobile/eas.json'), JSON.stringify({ build: { preview: {}, production: {} } }));
    expect(() => createBrand('flux', { rootDir })).toThrow(/main FluxWP/);
    const { brand } = createBrand('acme', { rootDir });
    expect(brand.mobile.oauthClientId).toBe('flx-mobil-acme');
    expect(brand.ios.bundleIdentifier).toBe('com.acme.app');
    expect(brand.allowedSites).toEqual([]);
    const eas = JSON.parse(readFileSync(join(rootDir, 'apps/mobile/eas.json'), 'utf8'));
    expect(eas.build['prod-acme'].env.FLUX_BRAND).toBe('acme');
    expect(eas.build['preview-acme'].channel).toBe('preview-acme');
    expect(JSON.stringify(brand)).not.toMatch(/PRIVATE KEY|EXPO_TOKEN/);
  });
});
