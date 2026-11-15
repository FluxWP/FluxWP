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
}

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

  async function request<R, V>(op: FlxOperation<R, V>, ...args: [...VariablesArg<V>, GraphQLRequestOptions?]): Promise<R> {
    const variables = args[0] as V | undefined;
    const ro = (args[1] ?? {}) as GraphQLRequestOptions;
    let res: ApiResult<Body>;
    try {
      res = await send(op, variables, !persisted, ro);
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
