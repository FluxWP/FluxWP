// Flux App contract (me, counts, sync, config, manifest, dashboard, prefs, session, logout, bootstrap) over GraphQL.
//
// `flxappTransport(api)` wraps an ApiClient: a request to one of these `flxapp/v1/...` routes is answered from
// the typed GraphQL operations, shaped exactly like the REST body (and ETag / 304 for config and manifest), so
// every consumer (runtime hooks, panel, login) keeps its REST types and parsers. Any other path goes to REST.
//
// Older sites (no Flux GraphQL, Flux App before 0.5.0) answer REST only: the first sign of that switches the
// wrapper to REST for good, so FluxApp keeps working with every site version.
import { ApiError, setClientEnhancer, type ApiClient, type ApiResult, type Me, type MenuItem, type MenuLayout, type ModuleInfo, type RequestOptions } from '@fluxwp/core';
import { createGraphQLClient, type GraphQLClient } from '../client';
import { isFlxGraphQLError, type FlxGraphQLError } from '../errors';
import {
  FlxAppBootstrap,
  FlxAppConfig,
  FlxAppCounts,
  FlxAppDashboard,
  FlxAppLogout,
  FlxAppManifest,
  FlxAppMe,
  FlxAppPrefs,
  FlxAppPrefsUpdate,
  FlxAppSessionCreate,
  FlxAppSessionEnd,
  FlxAppSync,
} from '../generated/operations';
import type { FlxAppMeQuery, FlxAppPrefsFieldsFragment } from '../generated/types';
import { graphQLUnavailable } from './flx-tasks';

type MeData = NonNullable<FlxAppMeQuery['flxAppMe']>;
type Count = { key: string; value: number };

const counts = (rows: ReadonlyArray<Count>): Record<string, number> => Object.fromEntries(rows.map((c) => [c.key, c.value]));
const opt = <T>(v: T | null | undefined): T | undefined => (v === null || v === undefined ? undefined : v);

/** Badges arrive as text; a count stays a number like in the REST body. */
const badge = (v: string | null): number | string | undefined => {
  if (v === null) return undefined;
  return /^\d+$/.test(v) ? Number(v) : v;
};

export function meFromGraphQL(m: MeData): Me {
  const menu: MenuItem[] = m.menu.map((i) => {
    const row: MenuItem = {
      id: i.id,
      slug: i.slug,
      title: i.title,
      url: i.url,
      icon: i.icon,
      items: i.items.map((s) => ({ slug: s.slug, title: s.title, url: s.url, ...(badge(s.badge) !== undefined ? { badge: badge(s.badge) as number | string } : {}) })),
    };
    if (i.iconUrl) row.icon_url = i.iconUrl;
    const b = badge(i.badge);
    if (b !== undefined) row.badge = b;
    return row;
  });
  const modules: Record<string, ModuleInfo> = {};
  for (const x of m.modules) {
    const extra = x.extra && typeof x.extra === 'object' && !Array.isArray(x.extra) ? (x.extra as Record<string, unknown>) : {};
    modules[x.id] = {
      ...extra,
      title: x.title,
      ...(x.menu ? { menu: x.menu } : {}),
      ...(x.api ? { api: x.api } : {}),
      ...(x.icon ? { icon: x.icon } : {}),
      ...(x.version ? { version: x.version } : {}),
      ...(x.order !== null ? { order: x.order } : {}),
      ...(x.url ? { url: x.url } : {}),
      manifest: x.hasManifest,
    } as ModuleInfo;
  }
  const layout: MenuLayout | undefined = m.layout
    ? { pins: [...m.layout.pins], groups: m.layout.groups.map((g) => ({ id: g.id, label: g.label, collapsed: g.collapsed, items: [...g.items] })) }
    : undefined;
  return {
    user: {
      id: m.user.databaseId,
      login: m.user.login,
      name: m.user.name,
      email: m.user.email,
      avatar: m.user.avatar,
      roles: m.user.roles.map((r) => ({ id: r.id, name: r.name })),
      locale: m.user.locale,
      is_admin: m.user.isAdmin,
      profile_url: m.user.profileUrl,
    },
    site: {
      name: m.site.name,
      url: m.site.url,
      admin_url: m.site.adminUrl,
      rest_url: m.site.restUrl,
      icon: m.site.icon,
      locale: m.site.locale,
      timezone: m.site.timezone,
    },
    auth: {
      kind: m.auth.kind.toLowerCase(),
      ...(m.auth.client ? { client: m.auth.client } : {}),
      ...(m.auth.scope ? { scope: m.auth.scope } : {}),
      ...(m.auth.expires !== null ? { expires: m.auth.expires } : {}),
    },
    menu,
    modules,
    counts: counts(m.counts),
    bottom: [...m.bottom],
    ...(layout ? { layout } : {}),
    web_session: m.webSession,
    caps: Object.fromEntries(m.caps.map((c) => [c.key, c.granted])),
    versions: Object.fromEntries(m.versions.map((v) => [v.slug, v.version])),
    hashes: { me: m.hashes.me, config: m.hashes.config, manifest: m.hashes.manifest },
  };
}

function prefsFromGraphQL(p: FlxAppPrefsFieldsFragment) {
  return {
    order: [...p.order],
    hidden: [...p.hidden],
    pinned: [...p.pinned],
    forceWeb: [...p.forceWeb],
    tabs: [...p.tabs],
    cardOrder: [...p.cardOrder],
    cardHidden: [...p.cardHidden],
    push: Object.fromEntries(p.push.map((x) => [x.event, x.on])),
    events: p.events.map((e) => ({ id: e.id, title: e.title, source: e.source, default: e.default, on: e.on })),
    updated: p.updated,
  };
}

const strip = (s: string | undefined): string => (s ?? '').replace(/^W\//, '').replace(/"/g, '');
const header = (o: RequestOptions, name: string): string | undefined => {
  for (const [k, v] of Object.entries(o.headers ?? {})) if (k.toLowerCase() === name.toLowerCase()) return v;
  return undefined;
};

/** A cached document: `ETag` is the digest, 304 when the client already has it. */
function documentResult<T>(hash: string, notModified: boolean, body: T | null): ApiResult<T> {
  const etag = `"${hash}"`;
  return { data: notModified ? null : body, status: notModified ? 304 : 200, etag, headers: { get: (n) => (n.toLowerCase() === 'etag' ? etag : null) } };
}

/** A GraphQL failure as the REST error the callers already handle. */
function asApiError(e: FlxGraphQLError): ApiError {
  return new ApiError(e.status || 500, e.code === 'NOT_FOUND' ? 'rest_no_route' : `flxapp_${e.code.toLowerCase()}`, e.message, e.errors[0]?.extensions);
}

type Handler = (path: string, o: RequestOptions) => Promise<unknown>;

export interface FlxAppTransportOptions {
  /** Ready GraphQL client (tests); default: one built on `api`. */
  graphql?: GraphQLClient;
}

/** The `flxapp/v1` contract over GraphQL; every other path and any unsupported site goes to REST. */
export function flxappTransport(api: ApiClient, o: FlxAppTransportOptions = {}): ApiClient & { readonly transport: () => 'graphql' | 'rest' } {
  const gql = o.graphql ?? createGraphQLClient({ api });
  // Discovery runs before sign-in: never send the account token with it.
  const anon =
    o.graphql ?? createGraphQLClient({ api: ((path: string, opts?: RequestOptions) => api(path, { ...opts, anonymous: true } as RequestOptions & { raw: true })) as ApiClient });
  let useRest = false;

  const routes = new Map<string, Handler>();
  const route = (name: string, method: 'GET' | 'POST', h: Handler) => routes.set(`${method} flxapp/v1/${name}`, h);

  route('me', 'GET', async () => {
    const r = await gql.request(FlxAppMe);
    if (!r.flxAppMe) throw new ApiError(500, 'flxapp_bad_response', 'Empty me');
    return meFromGraphQL(r.flxAppMe);
  });
  route('counts', 'GET', async () => counts((await gql.request(FlxAppCounts)).flxAppCounts));
  route('dashboard', 'GET', async () => {
    const d = (await gql.request(FlxAppDashboard)).flxAppDashboard;
    const row = <T extends Record<string, unknown>>(x: T) => Object.fromEntries(Object.entries(x).filter(([, v]) => v !== null && v !== undefined));
    return {
      cards: d.cards.map((c) => row(c)),
      lists: d.lists.map((l) => ({ ...row({ ...l, items: undefined }), items: l.items.map((i) => row(i)) })),
    };
  });
  route('sync', 'GET', async (_p, opts) => {
    const since = Number(opts.query?.since ?? 0);
    const s = (await gql.request(FlxAppSync, since > 0 ? { since } : {})).flxAppSync;
    return {
      counts: counts(s.counts),
      hashes: { me: s.hashes.me, config: s.hashes.config, manifest: s.hashes.manifest },
      events: s.events.map((e) => ({ event: e.event, title: e.title, body: e.body, target: e.target, params: e.params, at: e.at })),
      server_time: s.serverTime,
    };
  });
  route('config', 'GET', async (_p, opts) => {
    const c = (await gql.request(FlxAppConfig, { ifHash: strip(header(opts, 'If-None-Match')) || null })).flxAppConfig;
    return documentResult(c.hash, c.notModified, c.document as Record<string, unknown> | null);
  });
  route('manifest', 'GET', async (_p, opts) => {
    const m = (await gql.request(FlxAppManifest, { ifHash: strip(header(opts, 'If-None-Match')) || null })).flxAppManifest;
    const modules = Object.fromEntries(m.modules.map((x) => [x.id, x.document]));
    return documentResult(m.hash, m.notModified, { schema: m.schema, hash: m.hash, platform: m.platform ? m.platform.toLowerCase() : null, modules });
  });
  route('prefs', 'GET', async () => prefsFromGraphQL((await gql.request(FlxAppPrefs)).flxAppPrefs));
  route('prefs', 'POST', async (_p, opts) => {
    const b = (opts.body && typeof opts.body === 'object' ? opts.body : {}) as Record<string, unknown>;
    const input: Record<string, unknown> = {};
    for (const k of ['order', 'hidden', 'pinned', 'forceWeb', 'tabs', 'cardOrder', 'cardHidden']) if (Array.isArray(b[k])) input[k] = b[k];
    if (b.push && typeof b.push === 'object') {
      input.push = Object.entries(b.push as Record<string, unknown>).map(([event, on]) => ({ event, on: !!on }));
    }
    const r = (await gql.request(FlxAppPrefsUpdate, { input })).flxAppPrefsUpdate;
    if (!r?.prefs) throw new ApiError(400, 'flxapp_invalid_input', r?.errors[0]?.message ?? 'Invalid preferences');
    return prefsFromGraphQL(r.prefs);
  });
  route('session', 'POST', async (_p, opts) => {
    const b = (opts.body && typeof opts.body === 'object' ? opts.body : {}) as Record<string, unknown>;
    const input: Record<string, unknown> = {};
    if (typeof b.path === 'string') input.path = b.path;
    if (typeof b.url === 'string') input.url = b.url;
    if (typeof b.front === 'boolean') input.front = b.front;
    if (typeof b.embed === 'boolean') input.embed = b.embed;
    const r = (await gql.request(FlxAppSessionCreate, { input })).flxAppSessionCreate;
    if (!r?.url) throw new ApiError(400, 'flxapp_bad_path', r?.errors[0]?.message ?? 'Invalid admin path.');
    return { url: r.url, expires_in: r.expiresIn ?? 60 };
  });
  route('session/end', 'POST', async () => ({ ok: true, ended: (await gql.request(FlxAppSessionEnd)).flxAppSessionEnd?.ended ?? 0 }));
  route('logout', 'POST', async () => ({ ok: (await gql.request(FlxAppLogout)).flxAppLogout?.ok ?? false }));
  route('app/bootstrap', 'GET', async () => (await anon.request(FlxAppBootstrap)).flxAppBootstrap.document);

  const wrapped = (async (path: string, opts: RequestOptions = {}) => {
    const key = `${opts.method ?? (opts.body !== undefined ? 'POST' : 'GET')} ${path.replace(/^\/+/, '').split('?')[0] ?? ''}`;
    const handler = routes.get(key);
    // Streams, uploads and anything outside the contract stay on REST; so does a site without the GraphQL fields.
    if (!handler || useRest || opts.as === 'blob' || opts.formData) return api(path, opts as RequestOptions & { raw: true });
    try {
      const out = await handler(path, opts);
      // `raw` callers (config, manifest) want status + headers; the others the body.
      const isResult = typeof out === 'object' && out !== null && 'status' in out && 'headers' in out;
      if (opts.raw) return isResult ? out : { data: out, status: 200, etag: null, headers: { get: () => null } };
      return isResult ? (out as ApiResult<unknown>).data : out;
    } catch (e) {
      if (isFlxGraphQLError(e)) {
        if (graphQLUnavailable(e) || e.code === 'BAD_RESPONSE') {
          useRest = true;
          return api(path, opts as RequestOptions & { raw: true });
        }
        // Offline or blocked cross-origin (discovery): REST for this call only.
        if (e.code === 'NETWORK') return api(path, opts as RequestOptions & { raw: true });
        throw asApiError(e);
      }
      throw e;
    }
  }) as ApiClient & { transport: () => 'graphql' | 'rest' };
  wrapped.transport = () => (useRest ? 'rest' : 'graphql');
  return wrapped;
}

/**
 * Every account client of this app (panel, desktop, web) speaks the Flux App contract over GraphQL, with REST
 * as the fallback. Call once at start, before the first request.
 */
export function installFlxAppGraphQL(): void {
  setClientEnhancer((api) => flxappTransport(api));
}
