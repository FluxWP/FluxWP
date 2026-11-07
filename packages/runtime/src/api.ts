// flx-app contract calls (bootstrap, config, manifest) and manifest requests over @fluxwp/core's client.
// Endpoints follow docs/mobile-app-plan.md §12.1; until flx-app ships them a 404 means "use the defaults".
import { ApiError, clientFor, createClient, platform, type Account, type ApiClient, type ApiResult } from '@fluxwp/core';
import { demoShellFetch } from './demo/fetch';
import type { PlannedRequest } from './source';
import { CORE_MANIFEST, parseBootstrap, parseConfig, parseManifestBundle, type AppConfig, type Bootstrap, type ManifestBundle, type ParseIssue } from '@fluxwp/manifest';

const demoClients = new Map<string, ApiClient>();

/** Core `clientFor`, except demo accounts also get the contract fixtures and sample REST data. */
export function apiFor(a: Account): ApiClient {
  if (a.authKind !== 'demo') return clientFor(a);
  let c = demoClients.get(a.id);
  if (!c) {
    c = createClient({ siteUrl: a.siteUrl, fetchImpl: demoShellFetch, getCredentials: async () => ({ token: 'demo' }) });
    demoClients.set(a.id, c);
  }
  return c;
}

const missing = (e: unknown) => e instanceof ApiError && (e.status === 404 || e.code === 'rest_no_route');

export async function loadBootstrap(siteUrl: string, fetchImpl?: typeof fetch): Promise<Bootstrap | null> {
  try {
    const api = createClient({ siteUrl, fetchImpl, getCredentials: async () => null });
    return parseBootstrap(await api<unknown>('flxapp/v1/app/bootstrap', { anonymous: true }));
  } catch (e) {
    if (missing(e)) return null;
    throw e;
  }
}

export interface Cached<T> {
  value: T;
  etag: string | null;
  /** 304: keep the previous value; `value` is null. */
  notModified: boolean;
}

async function cachedGet<T>(api: ApiClient, path: string, etag: string | null | undefined, parse: (body: unknown) => T): Promise<Cached<T>> {
  const res = await api<unknown>(path, { raw: true, headers: etag ? { 'If-None-Match': etag } : undefined });
  if (res.status === 304) return { value: null as T, etag: res.etag ?? etag ?? null, notModified: true };
  return { value: parse(res.data), etag: res.etag, notModified: false };
}

/** A persisted validator may outlive its cached body after a reset, eviction or app upgrade. */
export async function loadContractValue<T>(load: (etag: string | null) => Promise<Cached<T>>, etag: string | null, cached: () => T | undefined): Promise<Pick<Cached<T>, 'value' | 'etag'>> {
  const response = await load(etag);
  if (!response.notModified) return response;
  const value = cached();
  if (value !== undefined) return { value, etag: response.etag };
  const fresh = await load(null);
  if (fresh.notModified) throw new Error('Server returned HTTP 304 without a cached response.');
  return fresh;
}

/** Site config (layers 3+4). Null when the site has no config endpoint or sent an invalid one. */
export async function loadConfig(api: ApiClient, issues?: ParseIssue[], etag?: string | null): Promise<Cached<AppConfig | null>> {
  try {
    return await cachedGet(api, 'flxapp/v1/config', etag, (body) => parseConfig(body, issues));
  } catch (e) {
    if (missing(e)) return { value: null, etag: null, notModified: false };
    throw e;
  }
}

/** All module manifests; the built-in `core` module is added when the site does not send one. */
export async function loadManifests(api: ApiClient, issues?: ParseIssue[], etag?: string | null): Promise<Cached<ManifestBundle>> {
  try {
    const got = await cachedGet(api, 'flxapp/v1/manifest', etag, (body) => parseManifestBundle(body, issues));
    return got.notModified ? got : { ...got, value: withCore(got.value) };
  } catch (e) {
    if (!missing(e)) throw e;
    return { value: withCore({ modules: {} }), etag: null, notModified: false };
  }
}

export const withCore = (b: ManifestBundle): ManifestBundle => (b.modules.core ? b : { ...b, modules: { core: CORE_MANIFEST, ...b.modules } });

const etagKey = (accountId: string, name: string) => `etag:${accountId}:${name}`;

/** Last ETag for this account's config or manifest. Missing platform storage → null (full response). */
export async function rememberedEtag(accountId: string, name: string): Promise<string | null> {
  try {
    return (await platform().storage.get(etagKey(accountId, name))) || null;
  } catch {
    return null;
  }
}

export async function rememberEtag(accountId: string, name: string, etag: string | null): Promise<void> {
  try {
    await platform().storage.set(etagKey(accountId, name), etag ?? '');
  } catch {
    /* storage is optional; the next open just refetches */
  }
}

function planned(r: PlannedRequest, signal?: AbortSignal) {
  const query = r.override ? { ...r.query, _method: r.override } : r.query;
  return { method: r.method, query, body: r.method === 'GET' ? undefined : (r.body ?? {}), signal };
}

/** Runs a planned manifest request. Method override travels as `_method` (WP REST accepts it). */
export function runRequest<T = unknown>(api: ApiClient, r: PlannedRequest, signal?: AbortSignal): Promise<T> {
  return api<T>(r.path, planned(r, signal));
}

/** Same request, with status and response headers (wp paging: `X-WP-Total` / `X-WP-TotalPages`). */
export function runRequestRaw<T = unknown>(api: ApiClient, r: PlannedRequest, signal?: AbortSignal): Promise<ApiResult<T>> {
  return api<T>(r.path, { ...planned(r, signal), raw: true });
}
