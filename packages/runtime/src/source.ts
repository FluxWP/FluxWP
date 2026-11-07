// Sources (§7.3) → REST requests, and responses → pages. Platform-neutral; the app runs the request
// through @fluxwp/core's client (auth, refresh, same-site guard).
import { fill, getPath, value, type Action, type ExprContext, type FormScreen, type Paging, type RequestSpec, type Source } from '@fluxwp/manifest';

export class ManifestError extends Error {
  constructor(public reason: string) {
    super(reason);
    this.name = 'ManifestError';
  }
}

export interface PlannedRequest {
  path: string;
  method: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
  query: Record<string, string | number | boolean>;
  body?: Record<string, unknown>;
  /** Set when DELETE/PUT/PATCH travel as POST (+ X-HTTP-Method-Override). */
  override?: 'PUT' | 'PATCH' | 'DELETE';
}

function cleanPath(p: string): string {
  const s = p.trim().replace(/^\/+/, '').replace(/^wp-json\//, '');
  if (!s || /^[a-z][a-z0-9+.-]*:/i.test(s) || s.startsWith('//') || s.split(/[/?]/).includes('..')) throw new ManifestError('path');
  return s;
}

/** Template values inside the path are URL-encoded so a value cannot add path segments. */
function renderPath(tpl: string, ctx: ExprContext): string {
  return cleanPath(tpl.replace(/\{\{([^{}]+)\}\}/g, (m) => encodeURIComponent(String(value(m, ctx) ?? ''))));
}

export interface ListQuery {
  page?: number;
  cursor?: string | null;
  search?: { param: string; value: string };
  filters?: Record<string, string | number | boolean | null | undefined>;
}

export function sourceRequest(src: Source, ctx: ExprContext, list: ListQuery = {}): PlannedRequest {
  const query: PlannedRequest['query'] = {};
  for (const [k, v] of Object.entries(src.query ?? {})) {
    const r = typeof v === 'string' ? value(v, ctx) : v;
    if (r !== undefined && r !== null && r !== '') query[k] = r as string | number | boolean;
  }
  for (const [k, v] of Object.entries(list.filters ?? {})) if (v !== undefined && v !== null && v !== '') query[k] = v;
  if (list.search?.value.trim()) query[list.search.param] = list.search.value.trim();
  const pg: Paging = src.paging ?? { mode: 'none' };
  if (pg.mode === 'page' || pg.mode === 'wp') {
    query[pg.param ?? 'page'] = list.page ?? 1;
    if (pg.size) query[pg.size_param ?? 'per_page'] = pg.size;
  } else if (pg.mode === 'cursor' && list.cursor) {
    query[pg.param ?? 'cursor'] = list.cursor;
  }
  const method = src.method ?? 'GET';
  return { path: renderPath(src.path, ctx), method, query, body: src.body ? fill(src.body, ctx) : undefined };
}

export interface Page<T = Record<string, unknown>> {
  items: T[];
  page: number;
  total?: number;
  /** Next page number or cursor; null when done. */
  next: number | string | null;
}

/** `headers`: response headers when the transport exposes them (wp mode X-WP-TotalPages). */
export function parsePage(src: Source, body: unknown, page: number, headers?: { get(name: string): string | null }): Page {
  const pg: Paging = src.paging ?? { mode: 'none' };
  const raw = Array.isArray(body) ? body : getPath(body, pg.items ?? 'items');
  const items = (Array.isArray(raw) ? raw : []).filter((x): x is Record<string, unknown> => !!x && typeof x === 'object');
  const n = (v: unknown) => (typeof v === 'number' ? v : typeof v === 'string' && v !== '' ? Number(v) : undefined);
  let total: number | undefined;
  let pages: number | undefined;
  if (pg.mode === 'wp') {
    total = n(headers?.get('X-WP-Total'));
    pages = n(headers?.get('X-WP-TotalPages'));
  } else if (!Array.isArray(body)) {
    total = n(getPath(body, pg.total ?? 'total'));
    pages = n(getPath(body, pg.pages ?? 'pages'));
  }
  let next: Page['next'] = null;
  if (pg.mode === 'page' || pg.mode === 'wp') {
    if (pages !== undefined && Number.isFinite(pages)) next = page < pages ? page + 1 : null;
    else if (pg.size) next = items.length >= pg.size ? page + 1 : null;
  } else if (pg.mode === 'cursor') {
    const c = getPath(body, pg.next ?? 'next');
    next = typeof c === 'string' || typeof c === 'number' ? c : null;
  }
  return { items, page, total, next };
}

/** Action / form request. DELETE/PUT/PATCH become POST + override when the site asks for it (§7.6). */
export function actionRequest(req: RequestSpec, ctx: ExprContext, methodOverride = true): PlannedRequest {
  const method = req.method ?? 'POST';
  const planned: PlannedRequest = { path: renderPath(req.path, ctx), method, query: {}, body: req.body ? fill(req.body, ctx) : undefined };
  if (methodOverride && (method === 'DELETE' || method === 'PUT' || method === 'PATCH')) return { ...planned, method: 'POST', override: method };
  return planned;
}

/** "consent.email" → { consent: { email } } */
export function setPath(obj: Record<string, unknown>, path: string, v: unknown): void {
  const keys = path.split('.');
  let cur = obj;
  for (const k of keys.slice(0, -1)) {
    if (k === '__proto__' || k === 'constructor' || k === 'prototype') return;
    if (!cur[k] || typeof cur[k] !== 'object') cur[k] = Object.create(null);
    cur = cur[k] as Record<string, unknown>;
  }
  const last = keys[keys.length - 1]!;
  if (last === '__proto__' || last === 'constructor' || last === 'prototype') return;
  cur[last] = v;
}

/** Form submit: `path_update` when `params.id` is filled, else `path_create`; body = form values. */
export function formRequest(form: FormScreen, values: Record<string, unknown>, ctx: ExprContext, methodOverride = true): PlannedRequest {
  const hasId = !!ctx.params?.id;
  const path = (hasId ? form.submit.path_update : form.submit.path_create) ?? form.submit.path;
  const req = actionRequest({ ...form.submit, path }, { ...ctx, input: values }, methodOverride);
  const nested: Record<string, unknown> = Object.create(null);
  for (const [k, v] of Object.entries(values)) setPath(nested, k, v);
  return { ...req, body: { ...(req.body ?? {}), ...nested } };
}

/** Which actions a screen shows: `can` is decided by the server; `when` uses the row/data. */
export function actionAllowed(a: Action | undefined, ctx: ExprContext, testFn: (c: Action['when'], ctx: ExprContext) => boolean): a is Action {
  if (!a) return false;
  if (a.can && ctx.can && ctx.can[a.can] === false) return false;
  return testFn(a.when, ctx);
}
