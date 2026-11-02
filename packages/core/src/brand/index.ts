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
  loginMethods: ('oauth' | 'apikey')[];
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

export const brand: Brand = current as Brand;
export const oauthClientId = (): string => brand.oauthClientId || 'flx-masaustu';
