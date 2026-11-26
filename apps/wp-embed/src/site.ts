// Site address and GraphQL endpoint of a wp-admin screen, for pretty and plain permalinks.
import { trimEnd, type ApiClient } from '@fluxwp/core';

/** `GRAPHQL_PATH` of @fluxwp/graphql: `/graphql` next to `/wp-json/`. */
const GRAPHQL_PATH = '../graphql';

/**
 * Site address from rest_url(): `https://site/wp-json/` (pretty permalinks) or `https://site/index.php?rest_route=/`
 * and `https://site/?rest_route=/` (plain permalinks, where the address must not keep the script or the query).
 */
export function siteFromRest(rest: string): string {
  try {
    const u = new URL(rest, location.href);
    if (u.searchParams.has('rest_route')) {
      u.search = '';
      u.hash = '';
      u.pathname = u.pathname.replace(/\/index\.php$/i, '');
      return trimEnd(u.href) || location.origin;
    }
  } catch {
    /* fall through */
  }
  return trimEnd(rest.replace(/\/wp-json\/?$/, '')) || location.origin;
}

/**
 * The client with the GraphQL calls sent to `endpoint` (the server's own URL: `/graphql`, or `/?graphql=1` on
 * plain permalinks, which needs no rewrite rule). Everything else goes through unchanged.
 */
export function withGraphQLEndpoint(api: ApiClient, endpoint: string | undefined): ApiClient {
  if (!endpoint) return api;
  const call = ((path: string, opts?: Parameters<ApiClient>[1]) => api(path === GRAPHQL_PATH ? endpoint : path, opts as never)) as ApiClient;
  return call;
}
