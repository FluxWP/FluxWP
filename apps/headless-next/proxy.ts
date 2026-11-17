// Pages end with a slash like their WordPress addresses; API routes and files do not.
// Whole-page mode (default): every page is drawn by WordPress through app/wp-page (lib/page.ts).
import { NextResponse, type NextRequest } from 'next/server';
import { RENDER } from '@/lib/env';

export function proxy(req: NextRequest) {
  const { pathname, search } = req.nextUrl;
  if (/^\/(api|wp-page|wp-json|wp-content|wp-includes|wp-admin)\//.test(pathname) || /\.[a-z0-9]+$/i.test(pathname)) return;
  if (!pathname.endsWith('/')) {
    // A plain URL: NextURL would format the pathname with the trailingSlash setting again.
    return NextResponse.redirect(new URL(`${pathname}/${search}`, req.url), 308);
  }
  if (RENDER !== 'wordpress' || pathname.startsWith('/__preview/')) return;
  const headers = new Headers(req.headers);
  headers.set('x-flx-path', pathname + search);
  return NextResponse.rewrite(new URL(`/wp-page${pathname}${search}`, req.url), { request: { headers } });
}

export const config = { matcher: ['/((?!_next/|api/|wp-json/|wp-content/|wp-includes/|wp-admin/).*)'] };
