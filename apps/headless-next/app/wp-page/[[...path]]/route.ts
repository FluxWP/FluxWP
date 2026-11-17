// Pages in whole-page mode. proxy.ts rewrites every page address here and passes the original in x-flx-path.
import type { NextRequest } from 'next/server';
import { getWpPage, isLive, liveWpPage } from '@/lib/page';

function original(req: NextRequest): string {
  const fromProxy = req.headers.get('x-flx-path');
  if (fromProxy) return fromProxy;
  const u = req.nextUrl;
  return (u.pathname.replace(/^\/wp-page/, '') || '/') + u.search;
}

export async function GET(req: NextRequest): Promise<Response> {
  const path = original(req);
  if (isLive(req.headers.get('cookie'), new URL(path, 'http://x').search)) return liveWpPage(req, path);
  let page;
  try {
    page = await getWpPage(path);
  } catch {
    return new Response('WordPress is not reachable right now.', { status: 502, headers: { 'content-type': 'text/plain; charset=utf-8', 'cache-control': 'no-store' } });
  }
  if (page.live) return liveWpPage(req, path);
  if (page.location) return new Response(null, { status: page.status, headers: { location: page.location } });
  return new Response(page.body, { status: page.status, headers: { 'content-type': page.type } });
}

// Forms that post to a page (search, comments, cart, checkout) reach WordPress live.
export async function POST(req: NextRequest): Promise<Response> {
  return liveWpPage(req, original(req));
}
