import { platform } from '../platform';
import { trimEnd } from '../text';
import { restBase } from '../api/client';

export interface SiteInfo {
  url: string;
  name: string;
  description: string;
  icon: string;
  /** Flux App kurulu mu (OAuth + /me). */
  hasFluxApp: boolean;
  /** @deprecated hasFluxApp */
  hasYtApp: boolean;
  /** WordPress Application Passwords yetkilendirme adresi (Flux App yoksa sınırlı mod). */
  appPasswordsUrl: string | null;
  namespaces: string[];
}

export class DiscoveryError extends Error {
  constructor(public reason: 'invalid_url' | 'unreachable' | 'not_wordpress') {
    super(reason);
  }
}

/** Kullanıcının yazdığı adresi kök site adresine çevirir: "ornek.org/" → "https://ornek.org". */
export function normalizeSiteUrl(input: string): string {
  let s = input.trim();
  if (!s) throw new DiscoveryError('invalid_url');
  if (!/^https?:\/\//i.test(s)) s = 'https://' + s;
  let u: URL;
  try {
    u = new URL(s);
  } catch {
    throw new DiscoveryError('invalid_url');
  }
  const cut = u.pathname.search(/\/(?:wp-admin|wp-json|wp-login\.php)/);
  const path = trimEnd(cut < 0 ? u.pathname : u.pathname.slice(0, cut));
  return `${u.protocol}//${u.host}${path}`;
}

type RestIndex = {
  name?: string; description?: string; url?: string; namespaces: string[]; site_icon_url?: string;
  authentication?: { 'application-passwords'?: { endpoints?: { authorization?: string } } };
};

async function readIndex(endpoint: string): Promise<RestIndex> {
  let res: Response;
  try { res = await platform().fetch(endpoint, { headers: { Accept: 'application/json' } }); }
  catch { throw new DiscoveryError('unreachable'); }
  if (!res.ok) throw new DiscoveryError(res.status === 404 ? 'not_wordpress' : 'unreachable');
  let data: RestIndex;
  try { data = await res.json(); }
  catch { throw new DiscoveryError('not_wordpress'); }
  if (!data || !Array.isArray(data.namespaces) || !data.namespaces.every(value => typeof value === 'string')) throw new DiscoveryError('not_wordpress');
  return data;
}

export async function discoverSite(input: string): Promise<SiteInfo> {
  const url = normalizeSiteUrl(input);
  let last: DiscoveryError = new DiscoveryError('not_wordpress');
  let unreachable = false;
  // WordPress supports the query route even when pretty REST URLs are not rewritten correctly.
  for (const endpoint of [restBase(url), `${url}/?rest_route=/`]) {
    let data: RestIndex;
    try { data = await readIndex(endpoint); }
    catch (error) {
      last = error instanceof DiscoveryError ? error : new DiscoveryError('unreachable');
      unreachable ||= last.reason === 'unreachable';
      continue;
    }
    const authorization = data.authentication?.['application-passwords']?.endpoints?.authorization;
    return {
      url: typeof data.url === 'string' && data.url ? trimEnd(data.url) : url,
      name: typeof data.name === 'string' && data.name ? data.name : new URL(url).host,
      description: typeof data.description === 'string' ? data.description : '',
      icon: typeof data.site_icon_url === 'string' ? data.site_icon_url : '',
      hasFluxApp: data.namespaces.includes('flxapp/v1'),
      hasYtApp: data.namespaces.includes('flxapp/v1'),
      appPasswordsUrl: typeof authorization === 'string' ? authorization : null,
      namespaces: data.namespaces,
    };
  }
  throw unreachable ? new DiscoveryError('unreachable') : last;
}
