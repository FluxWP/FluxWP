// flx-app contract calls (bootstrap, config, manifest) and manifest requests over @fluxwp/core's client.
// Endpoints follow docs/mobile-app-plan.md §12.1; until flx-app ships them a 404 means "use the defaults".
import { ApiError, clientFor, createClient, type Account, type ApiClient } from '@fluxwp/core';
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

/** Site config (layers 3+4). Null when the site has no config endpoint or sent an invalid one. */
export async function loadConfig(api: ApiClient, issues?: ParseIssue[]): Promise<AppConfig | null> {
  try {
    return parseConfig(await api<unknown>('flxapp/v1/config'), issues);
  } catch (e) {
    if (missing(e)) return null;
    throw e;
  }
}

/** All module manifests; the built-in `core` module is added when the site does not send one. */
export async function loadManifests(api: ApiClient, issues?: ParseIssue[]): Promise<ManifestBundle> {
  let bundle: ManifestBundle = { modules: {} };
  try {
    bundle = parseManifestBundle(await api<unknown>('flxapp/v1/manifest'), issues);
  } catch (e) {
    if (!missing(e)) throw e;
  }
  return withCore(bundle);
}

export const withCore = (b: ManifestBundle): ManifestBundle => (b.modules.core ? b : { ...b, modules: { core: CORE_MANIFEST, ...b.modules } });

/** Runs a planned manifest request. Method override travels as `_method` (WP REST accepts it). */
export function runRequest<T = unknown>(api: ApiClient, r: PlannedRequest, signal?: AbortSignal): Promise<T> {
  const query = r.override ? { ...r.query, _method: r.override } : r.query;
  return api<T>(r.path, { method: r.method, query, body: r.method === 'GET' ? undefined : (r.body ?? {}), signal });
}
