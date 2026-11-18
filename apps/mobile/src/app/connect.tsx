// Deep link landing (fluxwp://connect?site=<site url>): a site's "Open in app" button. The link carries only the
// site address; the add-site screen finds the site and the normal OAuth + PKCE sign-in follows.
import { Redirect, useLocalSearchParams } from 'expo-router';

/** https only (http on localhost for development); no credentials in the address. */
export function connectSite(raw: unknown): string {
  if (typeof raw !== 'string' || !raw) return '';
  try {
    const u = new URL(raw);
    const local = u.hostname === 'localhost' || u.hostname === '127.0.0.1';
    if (u.protocol !== 'https:' && !(local && u.protocol === 'http:')) return '';
    if (u.username || u.password) return '';
    return (u.origin + u.pathname).replace(/\/+$/, '');
  } catch {
    return '';
  }
}

export default function Connect() {
  const { site } = useLocalSearchParams<{ site?: string }>();
  const url = connectSite(site);
  return url ? <Redirect href={{ pathname: '/add-site', params: { url } }} /> : <Redirect href="/" />;
}
