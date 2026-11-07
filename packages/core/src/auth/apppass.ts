// WordPress Application Passwords (site without Flux App, plan §4.3): the in-app browser approves on
// authorize-application.php and redirects back with user_login + password. HTTPS only.
import { platform } from '../platform';
import { randomString } from './pkce';

export class AppPasswordError extends Error {
  constructor(public code: 'insecure' | 'rejected' | 'cancelled' | 'unsupported') {
    super(code);
  }
}

export interface AppPassword {
  user: string;
  password: string;
  siteUrl: string;
}

export function appPasswordUrl(authorizeEndpoint: string, p: { appName: string; appId: string; successUrl: string; rejectUrl?: string }): string {
  const u = new URL(authorizeEndpoint);
  u.searchParams.set('app_name', p.appName);
  u.searchParams.set('app_id', p.appId);
  u.searchParams.set('success_url', p.successUrl);
  u.searchParams.set('reject_url', p.rejectUrl ?? p.successUrl);
  return u.toString();
}

export async function requestAppPassword(authorizeEndpoint: string | null, appName: string): Promise<AppPassword> {
  if (!authorizeEndpoint) throw new AppPasswordError('unsupported');
  if (new URL(authorizeEndpoint).protocol !== 'https:') throw new AppPasswordError('insecure');
  const redirect = await platform().prepareOAuthRedirect('appauth/callback');
  await platform().openExternal(appPasswordUrl(authorizeEndpoint, { appName, appId: uuid(), successUrl: redirect.redirectUri }));
  const back = await redirect.wait();
  const q = back.searchParams;
  if (q.get('success') === 'false') throw new AppPasswordError('rejected');
  const user = q.get('user_login');
  const password = q.get('password');
  if (!user || !password) throw new AppPasswordError('rejected');
  return { user, password, siteUrl: q.get('site_url') ?? '' };
}

/** Basic credential value: base64("user:password"), UTF-8 safe. */
export function basicToken(user: string, password: string): string {
  const bytes = new TextEncoder().encode(`${user}:${password}`);
  let s = '';
  for (const b of bytes) s += String.fromCharCode(b);
  return btoa(s);
}

function uuid(): string {
  const h = Array.from(atob(randomString(16).replace(/-/g, '+').replace(/_/g, '/') + '=='), (c) => c.charCodeAt(0).toString(16).padStart(2, '0')).join('').padEnd(32, '0');
  return `${h.slice(0, 8)}-${h.slice(8, 12)}-4${h.slice(13, 16)}-a${h.slice(17, 20)}-${h.slice(20, 32)}`;
}
