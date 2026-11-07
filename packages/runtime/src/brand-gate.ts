// White-label lock (plan §11.4), app side. The server side is the brand's own OAuth client.
import { discoverSite, normalizeSiteUrl, type Brand, type SiteInfo } from '@fluxwp/core';

/** Host from an allow-list entry. `https://a.org/path` and `a.org` both yield `a.org`. */
function allowHost(raw: string): string {
  let s = raw.trim().toLowerCase();
  if (s.startsWith('https://')) s = s.slice(8);
  else if (s.startsWith('http://')) s = s.slice(7);
  const slash = s.indexOf('/');
  return slash === -1 ? s : s.slice(0, slash);
}

/** Exact host or "*.example.org"; full URLs in the list are accepted. Empty list = any site. */
export function siteAllowed(siteUrl: string, allowed?: string[] | null): boolean {
  if (!allowed?.length) return true;
  let host: string;
  try {
    host = new URL(siteUrl).host.toLowerCase();
  } catch {
    return false;
  }
  return allowed.some((raw) => {
    const a = allowHost(raw);
    return a.startsWith('*.') ? host.endsWith(a.slice(1)) && host !== a.slice(2) : host === a;
  });
}

export class SiteNotAllowedError extends Error {
  constructor() {
    super('site_not_allowed');
    this.name = 'SiteNotAllowedError';
  }
}

export interface BrandGate {
  /** Site adding / switching is hidden (single-site brand). */
  locked: boolean;
  defaultSite: string | null;
  allowed(siteUrl: string): boolean;
  /** Address → site; refuses hosts outside the list before any request goes out. */
  findSite(input: string): Promise<SiteInfo>;
}

export function brandGate(brand: Pick<Brand, 'lockSite' | 'defaultSite' | 'allowedSites'>): BrandGate {
  const list = brand.allowedSites?.length ? brand.allowedSites : brand.lockSite && brand.defaultSite ? [brand.defaultSite] : [];
  const allowed = (u: string) => siteAllowed(u, list);
  return {
    locked: !!brand.lockSite,
    defaultSite: brand.defaultSite,
    allowed,
    async findSite(input) {
      if (!allowed(normalizeSiteUrl(input))) throw new SiteNotAllowedError();
      const site = await discoverSite(input);
      if (!allowed(site.url)) throw new SiteNotAllowedError();
      return site;
    },
  };
}
