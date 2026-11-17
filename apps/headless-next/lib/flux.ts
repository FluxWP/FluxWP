// Talks to WordPress (Flux GraphQL + Flux Headless). Reads are GETs with a persisted queryId, so they
// work when Flux GraphQL only runs registered queries, and WordPress/CDN can cache them.
import 'server-only';
import { cacheLife, cacheTag } from 'next/cache';
import persisted from '../../../plugins/flx-headless/graphql/persisted-queries.json';
import { FRONTEND_ID, GRAPHQL_URL, PREVIEW_APP_PASSWORD, PREVIEW_USER, SECRET } from './env';
import type { PostCard, Route, Site } from './types';
import { hmacHex } from './verify';

const documents = persisted as Record<string, string>;
const ids: Record<string, string> = {};
for (const [id, doc] of Object.entries(documents)) {
  const name = /^(?:query|mutation) (\w+)/m.exec(doc)?.[1];
  if (name) ids[name] = id;
}

export { FRONTEND_ID, GRAPHQL_URL } from './env';

type Vars = Record<string, string | number | boolean | null | undefined>;

async function gql<T>(name: string, vars: Vars, preview = 0): Promise<T> {
  const id = ids[name];
  if (!GRAPHQL_URL) throw new Error('Set FLUX_WP_URL (or FLUX_GRAPHQL_URL).');
  if (!id) throw new Error(`Unknown operation ${name}; copy the plugin's persisted-queries.json again.`);
  const url = new URL(GRAPHQL_URL);
  url.searchParams.set('queryId', id);
  const clean = Object.fromEntries(Object.entries(vars).filter(([, v]) => v !== undefined && v !== null));
  url.searchParams.set('variables', JSON.stringify(clean));
  const headers: Record<string, string> = { Accept: 'application/json' };
  if (preview && PREVIEW_USER && PREVIEW_APP_PASSWORD) {
    headers.Authorization = 'Basic ' + Buffer.from(`${PREVIEW_USER}:${PREVIEW_APP_PASSWORD}`).toString('base64');
  } else if (preview && SECRET) {
    // Draft preview signed with the front end's secret: WordPress shows only this draft.
    const t = Math.floor(Date.now() / 1000);
    headers['X-Flx-Preview'] = `t=${t},v1=${await hmacHex(SECRET, `${t}.preview.${preview}`)}`;
  }
  const res = await fetch(url, { headers });
  const json = (await res.json()) as { data?: T; errors?: { message: string }[] };
  if (json.errors?.length || !json.data) throw new Error(`${name}: ${json.errors?.[0]?.message ?? `HTTP ${res.status}`}`);
  return json.data;
}

/** What a front-end path shows. Cached under the tags WordPress gives; the webhook refreshes them. */
export async function getRoute(path: string): Promise<Route> {
  'use cache';
  cacheLife('wordpress');
  const { flxRoute } = await gql<{ flxRoute: Route }>('FlxRoute', { path, frontend: FRONTEND_ID });
  cacheTag('flx-headless:all', 'flx-headless:routes', ...flxRoute.cacheTags);
  return flxRoute;
}

/**
 * A draft for the editor's Preview button. Only called in Draft Mode, where cached functions run on every
 * request and nothing is stored. Signed with the connection key's secret (or an application password).
 */
export async function getPreview(id: number): Promise<Route> {
  'use cache';
  const { flxRoute } = await gql<{ flxRoute: Route }>('FlxRoute', { path: '/', previewId: id, frontend: FRONTEND_ID }, id);
  return flxRoute;
}

/** The frame: name, logo, languages, menus, palette, contact details. */
export async function getSite(lang?: string): Promise<Site> {
  'use cache';
  cacheLife('wordpress');
  const { flxSite } = await gql<{ flxSite: Site }>('FlxSite', { lang, frontend: FRONTEND_ID });
  cacheTag('flx-headless:all', 'flx-headless:site', 'flx-headless:menus', ...flxSite.cacheTags);
  return flxSite;
}

/** Latest posts of a listing (blog, category, tag, author). */
export async function getPosts(filter: { categoryId?: number; tagId?: string; authorId?: number }): Promise<PostCard[]> {
  'use cache';
  cacheLife('wordpress');
  cacheTag('flx-headless:all', 'flx-headless:archive:post');
  if (filter.categoryId) cacheTag(`flx-headless:term:${filter.categoryId}`);
  const { posts } = await gql<{ posts: { nodes: PostCard[] } }>('FlxHeadlessPosts', { first: 12, ...filter });
  return posts.nodes;
}
