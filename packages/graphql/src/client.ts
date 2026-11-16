import { ApiError, type ApiClient, type ApiResult } from '@fluxwp/core';
import { FlxGraphQLError, type GraphQLErrorItem } from './errors';
import type { FlxOperation, VariablesArg } from './operation';

/** `/graphql` next to `/wp-json/` (also under a subdirectory install). */
export const GRAPHQL_PATH = '../graphql';

/** GET URLs longer than this go out as POST (proxies cut long query strings). */
const MAX_GET_URL = 2000;

export interface GraphQLClientOptions {
  /** The site's REST client (auth, nonce, 401 retry, `_locale=user`). */
  api: ApiClient;
  /** Persisted queries (queryId). Default true; false always sends the document (development). */
  persisted?: boolean;
  /**
   * Queries started in the same tick go out as one HTTP request (WPGraphQL batch, up to BATCH_MAX).
   * Default true; a lone query still goes out as a cacheable GET. Mutations are never batched.
   */
  batch?: boolean;
}

/** Operations per batch request (Flux GraphQL's default batch limit is 10). */
const BATCH_MAX = 10;

interface Pending {
  op: FlxOperation<unknown, unknown>;
  variables: unknown;
  ro: GraphQLRequestOptions;
  resolve: (r: ApiResult<Body>) => void;
  reject: (e: unknown) => void;
}

/**
 * One queue per REST client: every screen makes its own GraphQL client on the same `api`, so their
 * first queries still share a request.
 */
const queues = new WeakMap<ApiClient, { items: Pending[]; timer: ReturnType<typeof setTimeout> | null }>();

export interface GraphQLRequestOptions {
  signal?: AbortSignal;
  headers?: Record<string, string>;
}

interface Body {
  data?: unknown;
  errors?: GraphQLErrorItem[];
}

export interface GraphQLClient {
  request<R, V>(op: FlxOperation<R, V>, ...args: [...VariablesArg<V>, GraphQLRequestOptions?]): Promise<R>;
}

const isErrorList = (v: unknown): v is GraphQLErrorItem[] =>
  Array.isArray(v) && v.every((e) => typeof e === 'object' && e !== null && typeof (e as { message?: unknown }).message === 'string');

export function createGraphQLClient(o: GraphQLClientOptions): GraphQLClient {
  const persisted = o.persisted !== false;
  const batch = o.batch !== false;

  async function send(op: FlxOperation<unknown, unknown>, variables: unknown, withDocument: boolean, ro: GraphQLRequestOptions): Promise<ApiResult<Body>> {
    const vars = variables && typeof variables === 'object' && Object.keys(variables).length ? variables : undefined;
    const base = { raw: true as const, throwOnError: false, signal: ro.signal, headers: { 'X-Flux-GraphQL-Operation': op.name, ...ro.headers } };
    if (op.kind === 'query' && !withDocument) {
      const query = { queryId: op.id, operationName: op.name, variables: vars ? JSON.stringify(vars) : undefined };
      const len = Object.values(query).reduce((n, v) => n + (v ? encodeURIComponent(v).length + 16 : 0), 0);
      if (len < MAX_GET_URL) return o.api<Body>(GRAPHQL_PATH, { ...base, method: 'GET', query });
    }
    const body = withDocument ? { query: op.document, operationName: op.name, variables: vars } : { queryId: op.id, operationName: op.name, variables: vars };
    return o.api<Body>(GRAPHQL_PATH, { ...base, method: 'POST', body });
  }

  /** Sends the queued queries: one alone as usual, more as WPGraphQL batches. */
  async function flush(all: Pending[]): Promise<void> {
    const items = all.filter((it) => {
      if (!it.ro.signal?.aborted) return true;
      it.reject(it.ro.signal.reason ?? new Error('aborted'));
      return false;
    });
    if (!items.length) return;
    if (items.length === 1) {
      const it = items[0]!;
      send(it.op, it.variables, false, it.ro).then(it.resolve, it.reject);
      return;
    }
    for (let i = 0; i < items.length; i += BATCH_MAX) {
      const part = items.slice(i, i + BATCH_MAX);
      const body = part.map((it) => {
        const v = it.variables;
        const vars = v && typeof v === 'object' && Object.keys(v).length ? v : undefined;
        return { queryId: it.op.id, operationName: it.op.name, variables: vars };
      });
      o.api<Body[]>(GRAPHQL_PATH, {
        raw: true,
        throwOnError: false,
        method: 'POST',
        body,
        headers: { 'X-Flux-GraphQL-Operation': part.map((it) => it.op.name).join(',') },
      }).then(
        (res) => {
          const list = res.data;
          if (!Array.isArray(list) || list.length !== part.length) {
            // A server or proxy that does not batch: each query on its own.
            for (const it of part) send(it.op, it.variables, false, it.ro).then(it.resolve, it.reject);
            return;
          }
          part.forEach((it, n) => it.resolve({ ...res, data: list[n] as Body }));
        },
        (e) => {
          for (const it of part) it.reject(e);
        },
      );
    }
  }

  function queued(op: FlxOperation<unknown, unknown>, variables: unknown, ro: GraphQLRequestOptions): Promise<ApiResult<Body>> {
    if (!batch || op.kind !== 'query' || ro.headers) return send(op, variables, false, ro);
    let q = queues.get(o.api);
    if (!q) {
      q = { items: [], timer: null };
      queues.set(o.api, q);
    }
    const queue = q;
    return new Promise<ApiResult<Body>>((resolve, reject) => {
      queue.items.push({ op, variables, ro, resolve, reject });
      if (!queue.timer) {
        queue.timer = setTimeout(() => {
          const items = queue.items;
          queue.items = [];
          queue.timer = null;
          void flush(items);
        }, 0);
      }
    });
  }

  async function request<R, V>(op: FlxOperation<R, V>, ...args: [...VariablesArg<V>, GraphQLRequestOptions?]): Promise<R> {
    const variables = args[0] as V | undefined;
    const ro = (args[1] ?? {}) as GraphQLRequestOptions;
    let res: ApiResult<Body>;
    try {
      res = persisted ? await queued(op as FlxOperation<unknown, unknown>, variables, ro) : await send(op, variables, true, ro);
      // A server without this plugin version's manifest: send the document once (allowed for admins
      // in "admins" mode and in "open" mode; strict servers refuse it with QUERY_NOT_ALLOWED).
      if (persisted && res.data?.errors?.some((e) => e.extensions?.code === 'PERSISTED_QUERY_NOT_FOUND')) {
        res = await send(op, variables, true, ro);
      }
    } catch (e) {
      if (e instanceof ApiError) {
        throw new FlxGraphQLError(op.name, e.status, [{ message: e.message }], e.code === 'invalid_json' ? 'BAD_RESPONSE' : 'NETWORK');
      }
      throw e;
    }
    const body = res.data;
    const errors = isErrorList(body?.errors) ? body.errors : [];
    const data = body?.data;
    if (data === undefined || data === null || typeof data !== 'object') {
      throw new FlxGraphQLError(op.name, res.status, errors.length ? errors : [{ message: 'Empty GraphQL response' }], errors.length ? undefined : 'BAD_RESPONSE');
    }
    // Partial data: a field the user may not read comes back null with an error. A null root field
    // that the operation needs is an error for the caller; otherwise the data is returned.
    if (errors.length) {
      const roots = new Set(errors.map((e) => e.path?.[0]).filter((p): p is string => typeof p === 'string'));
      const brokenRoot = [...roots].some((r) => (data as Record<string, unknown>)[r] === null);
      if (brokenRoot || !roots.size) throw new FlxGraphQLError(op.name, res.status, errors);
    }
    return data as R;
  }

  return { request };
}
