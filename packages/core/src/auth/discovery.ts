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

export async function discoverSite(input: string): Promise<SiteInfo> {
  const url = normalizeSiteUrl(input);
  let res: Response;
  try {
    res = await platform().fetch(restBase(url), { headers: { Accept: 'application/json' } });
  } catch {
    throw new DiscoveryError('unreachable');
  }
  if (!res.ok) throw new DiscoveryError(res.status === 404 ? 'not_wordpress' : 'unreachable');
  let j: { name?: string; description?: string; home?: string; url?: string; namespaces?: string[]; site_icon_url?: string; authentication?: { 'application-passwords'?: { endpoints?: { authorization?: string } } } };
  try {
    j = await res.json();
  } catch {
    throw new DiscoveryError('not_wordpress');
  }
  if (!Array.isArray(j.namespaces)) throw new DiscoveryError('not_wordpress');
  return {
    url: trimEnd(j.url || url),
    name: j.name || new URL(url).host,
    description: j.description || '',
    icon: j.site_icon_url || '',
    hasFluxApp: j.namespaces.includes('flxapp/v1'),
    hasYtApp: j.namespaces.includes('flxapp/v1'),
    appPasswordsUrl: j.authentication?.['application-passwords']?.endpoints?.authorization ?? null,
    namespaces: j.namespaces,
  };
}
