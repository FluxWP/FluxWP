// Seçili marka. current.json'u tools/fluxapp/brand.mjs üretir (FLUX_BRAND).
import current from './current.json';
import type { BrandColors } from '@fluxwp/tokens';

export interface Brand {
  slug: string;
  name: string;
  shortName: string;
  bundleId: string;
  scheme: string;
  icon: string;
  logo: string;
  /** Koyu tema logosu (yoksa logo). */
  logoDark?: string;
  colors: BrandColors;
  /** Doluysa uygulama açılışta bu siteye bağlanır. */
  defaultSite: string | null;
  /** true: site ekleme/değiştirme gizli, uygulama yalnız defaultSite'tır. */
  lockSite: boolean;
  /** Giriş ekranında gösterilecek yollar. Kilitli markada genelde yalnız "oauth". */
  loginMethods: ('oauth' | 'apikey' | 'otp' | 'apppass')[];
  /** Beyaz etiket: bağlanılabilecek siteler (host ya da "*.alanadi"); boş = her site. */
  allowedSites?: string[];
  defaultLocale: string;
  locales: string[];
  supportUrl: string | null;
  /** Güncelleme sunucusu (FluxWP/updater). Boşsa güncelleme denetlenmez. */
  updateUrl?: string | null;
  /** Uygulama ikonu: beyaz (ya da verilen) yuvarlak kare zemin üstünde ikon; background null ise ikon kareyi doldurur. */
  appIcon?: { background?: string | null; scale?: number };
  /** Flux App OAuth istemcisi. Boşsa yerleşik "flx-masaustu". */
  oauthClientId?: string;
}

/** Active brand. Desktop/web read the generated current.json; the mobile app calls setBrand() at startup. */
export const brand: Brand = { ...(current as Brand) };

/** Replaces the active brand in place, so modules that imported `brand` see the new values. */
export function setBrand(next: Brand): void {
  for (const k of Object.keys(brand)) delete (brand as unknown as Record<string, unknown>)[k];
  Object.assign(brand, next);
}

export const oauthClientId = (): string => brand.oauthClientId || 'flx-masaustu';
