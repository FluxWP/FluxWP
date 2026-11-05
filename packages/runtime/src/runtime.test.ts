import { describe, expect, it } from 'vitest';
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { createClient, type Me } from '@fluxwp/core';
import { CORE_MANIFEST, DEFAULT_CONFIG, mergeConfig, parseConfig, parseManifestBundle, render, type ListScreen } from '@fluxwp/manifest';
import config from '@fluxwp/manifest/fixtures/config.json';
import bundle from '@fluxwp/manifest/fixtures/manifest.json';
import { demoMe } from '../../core/src/demo/fixtures';
import { brandGate, siteAllowed } from './brand-gate';
import { DEMO_SITE, demoShellFetch } from './demo/fetch';
import { accountForPush, routePush } from './notify';
import { resolveTarget, routeForAdminPath, type ResolveEnv } from './resolve';
import { resolveShell } from './shell';
import { actionRequest, formRequest, parsePage, sourceRequest } from './source';
import { classifyNavigation, embedUserAgent, parseBridgeMessage } from './web-session';
import { rowView } from './controllers/list';
import { fieldSchema, initialValues } from './controllers/form';
import { appResources, textOf } from './i18n';
import * as v from 'valibot';

const manifests = { core: CORE_MANIFEST, ...parseManifestBundle(bundle).modules };
const env: ResolveEnv = { manifests, me: demoMe as Me, native: new Set(['dashboard']), appVersion: '1.0.0' };

describe('resolveTarget (§6 order)', () => {
  it('manifest first, then native, then web', () => {
    expect(resolveTarget('module:crm', env)).toEqual({ kind: 'manifest', module: 'crm', screen: 'overview', params: {} });
    expect(resolveTarget('screen:crm/contact', env, { id: '5' })).toMatchObject({ kind: 'manifest', screen: 'contact', params: { id: '5' } });
    expect(resolveTarget('native:dashboard', env)).toMatchObject({ kind: 'native', component: 'dashboard' });
    // flxc-inbox has no manifest and no native screen here → its wp-admin page.
    expect(resolveTarget('module:flxc-inbox', env)).toEqual({ kind: 'web', path: 'admin.php?page=flx-chat' });
  });

  it('honours min_app and forceWeb', () => {
    const old = { ...env, appVersion: '0.9.0' };
    expect(resolveTarget('module:crm', old)).toEqual({ kind: 'web', path: 'admin.php?page=flx-crm' });
    expect(resolveTarget('module:crm', { ...env, forceWeb: new Set(['crm']) }).kind).toBe('web');
  });

  it('menu items open a native screen when a manifest route claims their page', () => {
    expect(resolveTarget('menu:edit.php', env)).toMatchObject({ kind: 'manifest', module: 'core', screen: 'posts' });
    expect(resolveTarget('menu:flxs', env)).toEqual({ kind: 'web', path: 'admin.php?page=flxs' });
    expect(routeForAdminPath('post.php?post=12&action=edit', env)).toMatchObject({ screen: 'post', params: { id: '12' } });
  });
});

describe('shell model', () => {
  it('resolves the fixture config: tabs, badges, drawer groups', () => {
    const cfg = mergeConfig({ site: parseConfig(config) });
    const shell = resolveShell(cfg, { ...env, me: demoMe as Me, counts: { 'flx-chat': 7 } });
    expect(shell.tabs.map((t) => t.id)).toEqual(['home', 'crm', 'chat', 'posts', 'more']);
    expect(shell.tabs.find((t) => t.id === 'chat')).toMatchObject({ badge: 7, route: { kind: 'web' } });
    expect(shell.drawer.groups[0]!.items[0]).toMatchObject({ label: 'Yazılar', route: { kind: 'manifest', screen: 'posts' } });
    expect(shell.drawer.footer.map((f) => f.target)).toEqual(['accounts', 'settings']);
  });

  it('works without a site config (built-in core only)', () => {
    const shell = resolveShell(DEFAULT_CONFIG, { ...env, manifests: { core: CORE_MANIFEST } });
    expect(shell.tabs.map((t) => t.id)).toEqual(['home', 'posts', 'comments', 'more']);
    expect(shell.home.route).toMatchObject({ kind: 'native', component: 'dashboard' });
  });
});

describe('sources and requests', () => {
  const contacts = manifests.crm!.sources.contacts!;
  it('builds paged list requests with search and filters', () => {
    const r = sourceRequest(contacts, {}, { page: 2, search: { param: 's', value: ' ay ' }, filters: { role: 'donor', segment: '' } });
    expect(r).toMatchObject({ path: 'flx-crm/v1/contacts', method: 'GET', query: { orderby: 'last_activity_at', order: 'desc', page: 2, per_page: 25, s: 'ay', role: 'donor' } });
  });

  it('encodes template values in paths', () => {
    expect(sourceRequest(manifests.crm!.sources.contact!, { params: { id: '5/../x' } }).path).toBe('flx-crm/v1/contacts/5%2F..%2Fx');
  });

  it('pages by body (page) and by size fallback (wp without headers)', () => {
    expect(parsePage(contacts, { items: [{ id: 1 }], total: 30, pages: 2 }, 1).next).toBe(2);
    expect(parsePage(contacts, { items: [{ id: 1 }], total: 30, pages: 2 }, 2).next).toBeNull();
    const posts = CORE_MANIFEST.sources.posts!;
    expect(parsePage(posts, Array.from({ length: 20 }, (_, i) => ({ id: i })), 1).next).toBe(2);
    expect(parsePage(posts, [{ id: 1 }], 1, { get: (h) => (h === 'X-WP-TotalPages' ? '3' : null) }).next).toBe(2);
  });

  it('sends DELETE as POST + override and nests dotted form fields', () => {
    const del = actionRequest({ method: 'DELETE', path: 'flx-crm/v1/contacts/{{data.id}}' }, { data: { id: 9 } });
    expect(del).toMatchObject({ method: 'POST', override: 'DELETE', path: 'flx-crm/v1/contacts/9' });
    const form = manifests.crm!.screens.contact_form as Parameters<typeof formRequest>[0];
    const upd = formRequest(form, { name: 'A', consent: { email: true } }, { params: { id: '4' } });
    expect(upd).toMatchObject({ path: 'flx-crm/v1/contacts/4', body: { name: 'A', consent: { email: true } } });
    expect(formRequest(form, { name: 'A' }, { params: {} }).path).toBe('flx-crm/v1/contacts');
  });

  it('rejects absolute paths', () => {
    expect(() => sourceRequest({ path: 'https://evil.example/x' }, {})).toThrow();
    expect(() => sourceRequest({ path: '{{params.p}}' }, { params: { p: '//evil' } })).not.toThrow();
  });
});

describe('rows and forms', () => {
  it('resolves a CRM row', () => {
    const screen = manifests.crm!.screens.contacts as ListScreen;
    const row = rowView(screen.item, { id: 1, name: 'Ayşe Kaya', email: '', phone: '+90 5', ltv: 4350, currency: 'TRY', churn: 80, segment_label: 'Sadık', roles: [{ label: 'Bağışçı' }] }, { locale: 'tr-TR' }, '1', (s, c) => (s && c ? render(s, c) : (s ?? '')));
    expect(row.title).toBe('Ayşe Kaya');
    expect(row.subtitle).toBe('+90 5');
    expect(row.badges.map((b) => b.text)).toEqual(['Sadık', 'Risk']);
    expect(row.chips).toEqual([{ text: 'Bağışçı', color: '' }]);
    expect(row.avatar?.text).toBe('Ayşe Kaya');
  });

  it('validates fields with valibot and prefills raw values', () => {
    const email = fieldSchema({ name: 'email', type: 'email', label: 'E' });
    expect(v.safeParse(email, '').success).toBe(true);
    expect(v.safeParse(email, 'x@').issues?.[0]?.message).toBe('form.invalid');
    expect(v.safeParse(fieldSchema({ name: 't', type: 'text', label: 'T', required: true }), ' ').success).toBe(true);
    expect(v.safeParse(fieldSchema({ name: 't', type: 'text', label: 'T', required: true }), '').issues?.[0]?.message).toBe('form.required');
    expect(initialValues([{ name: 'title', type: 'text', label: 'T' }, { name: 'consent.sms', type: 'switch', label: 'S' }], { title: { raw: 'Ham', rendered: '<b>Ham</b>' } })).toEqual({ title: 'Ham', consent: { sms: false } });
  });
});

describe('webview decisions', () => {
  const opts = { siteUrl: 'https://demo.fluxapp.local', env: { ...env } };
  it('classifies navigation', () => {
    expect(classifyNavigation('https://demo.fluxapp.local/wp-admin/admin.php?page=flx-crm-contacts&id=4', opts)).toMatchObject({ action: 'native', route: { screen: 'contact', params: { id: '4' } } });
    expect(classifyNavigation('https://demo.fluxapp.local/wp-admin/upload.php', opts)).toEqual({ action: 'allow' });
    expect(classifyNavigation('https://demo.fluxapp.local/wp-login.php?redirect_to=x', opts)).toEqual({ action: 'relogin' });
    expect(classifyNavigation('https://demo.fluxapp.local/wp-login.php?action=logout', opts)).toEqual({ action: 'block', reason: 'logout' });
    expect(classifyNavigation('https://example.com/', opts)).toEqual({ action: 'browser', url: 'https://example.com/' });
    expect(classifyNavigation('tel:+905320000000', opts).action).toBe('system');
    expect(classifyNavigation('javascript:alert(1)', opts)).toEqual({ action: 'block', reason: 'scheme' });
  });

  it('accepts only whitelisted bridge messages', () => {
    expect(parseBridgeMessage('{"type":"title","title":"Ortam"}')).toEqual({ type: 'title', title: 'Ortam' });
    expect(parseBridgeMessage({ type: 'counts', counts: { a: 1, b: 'x' } })).toEqual({ type: 'counts', counts: { a: 1 } });
    expect(parseBridgeMessage({ type: 'eval', code: 'x' })).toBeNull();
    expect(parseBridgeMessage('not json')).toBeNull();
  });

  it('builds the embed user agent flx-app looks for', () => {
    expect(embedUserAgent({ version: '0.1.0', surface: 'mobile', brand: 'flux', os: 'ios' })).toBe('FLXApp/0.1.0 (mobile; flux; ios)');
  });
});

describe('brand gate and push routing', () => {
  it('locks white-label brands to their hosts', () => {
    expect(siteAllowed('https://a.org', [])).toBe(true);
    expect(siteAllowed('https://www.a.org', ['*.a.org'])).toBe(true);
    expect(siteAllowed('https://a.org', ['*.a.org'])).toBe(false);
    expect(siteAllowed('https://evil-a.org', ['a.org'])).toBe(false);
    const g = brandGate({ lockSite: true, defaultSite: 'https://a.org', allowedSites: [] });
    expect(g.allowed('https://a.org/x')).toBe(true);
    expect(g.allowed('https://b.org')).toBe(false);
    return expect(g.findSite('b.org')).rejects.toThrow('site_not_allowed');
  });

  it('routes a push to the matching account only', () => {
    const acc = { id: 'demo.fluxapp.local#1', siteUrl: DEMO_SITE, siteName: '', siteIcon: '', userId: 1, userName: '', userAvatar: '', authKind: 'demo' as const, addedAt: 0 };
    expect(accountForPush({ site: 'https://demo.fluxapp.local', user: 2 }, [acc])).toBeNull();
    expect(routePush({ site: 'https://demo.fluxapp.local', user: 1, target: 'screen:crm/contact', params: { id: '3' } }, [acc], () => env)).toMatchObject({ route: { kind: 'manifest', screen: 'contact' } });
  });
});

describe('demo transport', () => {
  it('serves the contract fixtures and wp paging headers', async () => {
    const api = createClient({ siteUrl: DEMO_SITE, fetchImpl: demoShellFetch, getCredentials: async () => ({ token: 'demo' }) });
    expect(parseConfig(await api('flxapp/v1/config'))).not.toBeNull();
    expect(Object.keys(parseManifestBundle(await api('flxapp/v1/manifest')).modules)).toEqual(['crm']);
    const res = await demoShellFetch(`${DEMO_SITE}/wp-json/wp/v2/posts?per_page=3&page=1`);
    expect(res.headers.get('X-WP-TotalPages')).toBe('3');
  });
});

describe('i18n', () => {
  it('has the same keys in tr and en, including core strings', () => {
    const keys = (o: object, p = ''): string[] => Object.entries(o).flatMap(([k, x]) => (typeof x === 'object' ? keys(x, p + k + '.') : [p + k]));
    expect(keys(appResources.tr.translation).sort()).toEqual(keys(appResources.en.translation).sort());
    expect(textOf('$t:shell.tabs.home', (k) => k)).toBe('shell.tabs.home');
  });
});

describe('platform neutrality', () => {
  it('imports no react-native, react-dom or DOM-only globals', () => {
    const files = (d: string): string[] => readdirSync(d).flatMap((f) => (statSync(join(d, f)).isDirectory() ? files(join(d, f)) : /\.tsx?$/.test(f) && !f.includes('.test.') ? [join(d, f)] : []));
    for (const dir of [__dirname, join(__dirname, '../../manifest/src')]) {
      for (const f of files(dir)) {
        const src = readFileSync(f, 'utf8');
        expect(src, f).not.toMatch(/from ['"](react-native|react-dom|expo[\w-]*)['"/]/);
        expect(src, f).not.toMatch(/\b(document|window|localStorage)\./);
      }
    }
  });
});
