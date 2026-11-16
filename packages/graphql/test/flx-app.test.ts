import { describe, expect, it, vi } from 'vitest';
import { ApiError, createClient } from '@fluxwp/core';
import { flxappTransport, meFromGraphQL } from '../src/adapters/flx-app';

const json = (status: number, body: unknown, headers: Record<string, string> = {}) =>
  new Response(JSON.stringify(body), { status, headers: { 'content-type': 'application/json', ...headers } });

const me = {
  user: { databaseId: 3, login: 'ayse', name: 'Ayşe', email: 'a@x.org', avatar: 'https://x/a.png', roles: [{ id: 'editor', name: 'Editor' }], locale: 'tr_TR', isAdmin: false, profileUrl: 'https://x/p' },
  site: { name: 'Site', url: 'https://x.org', adminUrl: 'https://x.org/wp-admin/', restUrl: 'https://x.org/wp-json/', icon: '', locale: 'en_US', timezone: '+00:00' },
  auth: { kind: 'ACCESS', client: 'flx-mobil', scope: 'flx', expires: 1800000000 },
  menu: [{ id: 'index-php', slug: 'index.php', title: 'Home', url: 'https://x.org/wp-admin/', icon: 'home', iconUrl: null, badge: '3', items: [{ slug: 'a', title: 'A', url: 'u', badge: null }] }],
  modules: [{ id: 'flxg', title: 'Tasks', menu: 'flx-tasks', api: null, icon: 'check', version: '1.0', order: 5, url: null, hasManifest: true, extra: { native: 'tasks' } }],
  counts: [{ key: 'flxg', value: 4 }],
  bottom: ['index.php'],
  webSession: true,
  caps: [{ key: 'read', granted: true }, { key: 'manage_options', granted: false }],
  versions: [{ slug: 'flx-app', version: '0.5.0' }],
  layout: { pins: ['index.php'], groups: [{ id: 'general', label: '', collapsed: false, items: ['index.php'] }] },
  hashes: { me: 'a', config: 'b', manifest: 'c' },
};

function setup(...responses: Response[]) {
  const f = vi.fn<typeof fetch>();
  for (const r of responses) f.mockResolvedValueOnce(r);
  const api = createClient({ siteUrl: 'https://x.org', fetchImpl: f, getCredentials: async () => ({ token: 't' }) });
  return { f, api: flxappTransport(api) };
}
const path = (f: ReturnType<typeof vi.fn<typeof fetch>>, i = 0) => new URL(String(f.mock.calls[i]![0])).pathname;

describe('Flux App over GraphQL', () => {
  it('shapes /me like the REST body', () => {
    const r = meFromGraphQL(me as never);
    expect(r.user).toEqual({ id: 3, login: 'ayse', name: 'Ayşe', email: 'a@x.org', avatar: 'https://x/a.png', roles: [{ id: 'editor', name: 'Editor' }], locale: 'tr_TR', is_admin: false, profile_url: 'https://x/p' });
    expect(r.auth).toEqual({ kind: 'access', client: 'flx-mobil', scope: 'flx', expires: 1800000000 });
    expect(r.menu[0]).toEqual({ id: 'index-php', slug: 'index.php', title: 'Home', url: 'https://x.org/wp-admin/', icon: 'home', badge: 3, items: [{ slug: 'a', title: 'A', url: 'u' }] });
    expect(r.modules.flxg).toEqual({ native: 'tasks', title: 'Tasks', menu: 'flx-tasks', icon: 'check', version: '1.0', order: 5, manifest: true });
    expect(r.counts).toEqual({ flxg: 4 });
    expect(r.caps).toEqual({ read: true, manage_options: false });
    expect(r.versions).toEqual({ 'flx-app': '0.5.0' });
    expect(r.layout?.groups[0]).toEqual({ id: 'general', label: '', collapsed: false, items: ['index.php'] });
    expect(r.web_session).toBe(true);
    expect(r.hashes).toEqual({ me: 'a', config: 'b', manifest: 'c' });
  });

  it('answers flxapp/v1/me from GraphQL', async () => {
    const { f, api } = setup(json(200, { data: { flxAppMe: me } }));
    const r = await api<{ user: { id: number } }>('flxapp/v1/me');
    expect(r.user.id).toBe(3);
    expect(path(f)).toBe('/graphql');
    expect(api.transport()).toBe('graphql');
  });

  it('config: ETag in, 304 out', async () => {
    const { f, api } = setup(
      json(200, { data: { flxAppConfig: { hash: 'abc123', notModified: false, document: { schema: 'flx-app-config/1', hash: 'abc123' } } } }),
      json(200, { data: { flxAppConfig: { hash: 'abc123', notModified: true, document: null } } }),
    );
    const a = await api<{ schema: string }>('flxapp/v1/config', { raw: true });
    expect(a).toMatchObject({ status: 200, etag: '"abc123"', data: { schema: 'flx-app-config/1' } });
    const b = await api<unknown>('flxapp/v1/config', { raw: true, headers: { 'If-None-Match': '"abc123"' } });
    expect(b).toMatchObject({ status: 304, data: null, etag: '"abc123"' });
    expect(f.mock.calls[1]![1]?.method).toBe('GET');
    expect(String(f.mock.calls[1]![0])).toContain('variables=' + encodeURIComponent(JSON.stringify({ ifHash: 'abc123' })));
  });

  it('manifest: modules list becomes the id → document map', async () => {
    const { api } = setup(json(200, { data: { flxAppManifest: { schema: 'flx-app-manifest/1', hash: 'h', notModified: false, platform: 'MOBILE', modules: [{ id: 'core', document: { id: 'core' } }] } } }));
    const r = await api<{ data: unknown }>('flxapp/v1/manifest', { raw: true });
    expect(r.data).toEqual({ schema: 'flx-app-manifest/1', hash: 'h', platform: 'mobile', modules: { core: { id: 'core' } } });
  });

  it('sync: since goes as a variable, answer is REST shaped', async () => {
    const { f, api } = setup(json(200, { data: { flxAppSync: { counts: [{ key: 'a', value: 1 }], hashes: { me: '1', config: '2', manifest: '3' }, events: [{ event: 'e', title: 't', body: '', target: '', params: {}, at: 5 }], serverTime: 99 } } }));
    const r = await api<{ server_time: number; counts: Record<string, number> }>('flxapp/v1/sync', { query: { since: 42 } });
    expect(r).toMatchObject({ server_time: 99, counts: { a: 1 } });
    expect(String(f.mock.calls[0]![0])).toContain(encodeURIComponent(JSON.stringify({ since: 42 })));
  });

  it('prefs: push map becomes the typed list and back', async () => {
    const prefs = { order: [], hidden: [], pinned: ['a'], forceWeb: [], tabs: [], cardOrder: [], cardHidden: [], push: [{ event: 'x', on: false }], events: [{ id: 'x', title: 'X', source: '', default: true, on: false }], updated: 7 };
    const { f, api } = setup(json(200, { data: { flxAppPrefsUpdate: { prefs, errors: [] } } }));
    const r = await api<{ push: Record<string, boolean>; pinned: string[] }>('flxapp/v1/prefs', { method: 'POST', body: { pinned: ['a'], push: { x: false } } });
    expect(r.push).toEqual({ x: false });
    expect(JSON.parse(String(f.mock.calls[0]![1]?.body)).variables).toEqual({ input: { pinned: ['a'], push: [{ event: 'x', on: false }] } });
  });

  it('session: path in, url + expires_in out; a payload error is a 400', async () => {
    const ok = setup(json(200, { data: { flxAppSessionCreate: { url: 'https://x.org/sso', expiresIn: 60, errors: [] } } }));
    expect(await ok.api('flxapp/v1/session', { method: 'POST', body: { path: 'index.php' } })).toEqual({ url: 'https://x.org/sso', expires_in: 60 });
    const bad = setup(json(200, { data: { flxAppSessionCreate: { url: null, expiresIn: null, errors: [{ code: 'INVALID_INPUT', message: 'Invalid admin path.', field: 'path' }] } } }));
    await expect(bad.api('flxapp/v1/session', { method: 'POST', body: { path: '//x' } })).rejects.toMatchObject({ status: 400, code: 'flxapp_bad_path' });
  });

  it('a GraphQL FORBIDDEN is the same ApiError REST gives', async () => {
    const { api } = setup(json(200, { data: null, errors: [{ message: 'No access', path: ['flxAppPrefs'], extensions: { code: 'FORBIDDEN' } }] }));
    await expect(api('flxapp/v1/prefs')).rejects.toBeInstanceOf(ApiError);
  });

  it('falls back to REST for good on a site without the GraphQL fields', async () => {
    const { f, api } = setup(
      json(200, { errors: [{ message: 'Cannot query field "flxAppMe" on type "RootQuery".' }] }),
      json(200, { user: { id: 1 } }),
      json(200, { counts: {} }),
    );
    expect(await api('flxapp/v1/me')).toEqual({ user: { id: 1 } });
    expect(api.transport()).toBe('rest');
    await api('flxapp/v1/counts');
    expect(path(f, 1)).toBe('/wp-json/flxapp/v1/me');
    expect(path(f, 2)).toBe('/wp-json/flxapp/v1/counts');
  });

  it('paths outside the contract stay on REST', async () => {
    const { f, api } = setup(json(200, { items: [] }));
    await api('flxapp/v1/devices');
    expect(path(f)).toBe('/wp-json/flxapp/v1/devices');
    expect(api.transport()).toBe('graphql');
  });

  it('bootstrap is anonymous', async () => {
    const { f, api } = setup(json(200, { data: { flxAppBootstrap: { document: { schema: 'flx-app-bootstrap/1' } } } }));
    expect(await api('flxapp/v1/app/bootstrap', { anonymous: true })).toEqual({ schema: 'flx-app-bootstrap/1' });
    const headers = new Headers(f.mock.calls[0]![1]?.headers as HeadersInit);
    expect(headers.get('authorization')).toBeNull();
  });
});
