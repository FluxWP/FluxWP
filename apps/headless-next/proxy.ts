// Pages end with a slash like their WordPress addresses; API routes and files do not.
import { NextResponse, type NextRequest } from 'next/server';

export function proxy(req: NextRequest) {
  const { pathname } = req.nextUrl;
  if (pathname.endsWith('/') || pathname.startsWith('/api/') || /\.[a-z0-9]+$/i.test(pathname)) return;
  // A plain URL: NextURL would format the pathname with the trailingSlash setting again.
  return NextResponse.redirect(new URL(`${pathname}/${req.nextUrl.search}`, req.url), 308);
}

export const config = { matcher: ['/((?!_next/|api/).*)'] };
