// Whole-page mode (FLUX_RENDER=wordpress): WordPress renders the page with its theme and Flux Headless rewrites
// its links to this site (GET <page>?flxhl_page=<front end id>). Anonymous pages are cached under the tags
// WordPress sends in X-Flx-Cache-Tags (the revalidate webhook names the same tags). Per-visitor pages (cart,
// checkout, account: X-Flx-Live) and visitors with a WordPress session are served live with their cookies.
import 'server-only';
import { cacheLife, cacheTag } from 'next/cache';
import { FRONTEND_ID, WP_URL } from './env';

export interface WpPage {
  status: number;
  location: string | null;
  type: string;
  body: string;
  /** Per-visitor page: never served from the cache. */
  live: boolean;
}

/** Cookies that make a page personal (signed-in user, cart session, comment author). */
const SESSION = /(?:^|;\s*)(wordpress_logged_in_|wp_woocommerce_session_|woocommerce_items_in_cart|woocommerce_cart_hash|comment_author_)/;
/** Queries that change state on WordPress. */
const ACTION = /[?&](add-to-cart|wc-ajax|remove_item|undo_item|removed_item|order-received|key)=/;

/** Whether a request must reach WordPress live instead of the cache. */
export function isLive(cookie: string | null, search: string): boolean {
  return (!!cookie && SESSION.test(cookie)) || ACTION.test(search);
}

/** The WordPress address of a path on this site, marked as a front-end request. */
export function wpUrl(pathAndQuery: string): URL {
  if (!WP_URL) throw new Error('Set FLUX_WP_URL.');
  const url = new URL(pathAndQuery, `${WP_URL}/`);
  url.searchParams.set('flxhl_page', FRONTEND_ID ?? 'default');
  return url;
}

async function read(r: Response, store: boolean): Promise<WpPage> {
  const redirect = r.status >= 300 && r.status < 400;
  const live = r.headers.get('x-flx-live') === '1';
  return {
    status: r.status,
    location: redirect ? r.headers.get('location') : null,
    type: r.headers.get('content-type') ?? 'text/html; charset=utf-8',
    body: redirect || (live && store) ? '' : await r.text(),
    live,
  };
}

export async function getWpPage(pathAndQuery: string): Promise<WpPage> {
  'use cache';
  cacheLife('wordpress');
  const r = await fetch(wpUrl(pathAndQuery), { redirect: 'manual', headers: { accept: 'text/html' }, cache: 'no-store' });
  if (r.status >= 500) throw new Error(`WordPress answered ${r.status} for ${pathAndQuery}`);
  const tags = (r.headers.get('x-flx-cache-tags') ?? '').split(',').map((t) => t.trim()).filter(Boolean);
  cacheTag('flx-headless:all', 'flx-headless:html', ...tags);
  return read(r, true);
}

const FORWARD = ['cookie', 'content-type', 'accept', 'accept-language', 'user-agent', 'referer'];

/** A request passed to WordPress as it is (visitor's cookies, method and body); Set-Cookie comes back. */
export async function liveWpPage(req: Request, pathAndQuery: string): Promise<Response> {
  const headers = new Headers();
  for (const h of FORWARD) {
    const v = req.headers.get(h);
    if (v) headers.set(h, v);
  }
  const method = req.method.toUpperCase();
  const body = method === 'GET' || method === 'HEAD' ? undefined : await req.arrayBuffer();
  const r = await fetch(wpUrl(pathAndQuery), { method, body, headers, redirect: 'manual', cache: 'no-store' });
  const out = new Headers({ 'cache-control': 'private, no-store' });
  for (const h of ['content-type', 'location']) {
    const v = r.headers.get(h);
    if (v) out.set(h, v);
  }
  for (const c of r.headers.getSetCookie()) out.append('set-cookie', c);
  return new Response(r.status >= 300 && r.status < 400 ? null : r.body, { status: r.status, headers: out });
}
