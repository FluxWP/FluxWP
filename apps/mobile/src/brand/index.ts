// Build brand (scripts/brand.cjs → current.json). setBrand() makes @fluxwp/core use the mobile values
// (scheme fluxwp, client flx-mobil) instead of the desktop ones.
import { brand as coreBrand, setBrand, type Brand } from '@fluxwp/core';
import { brandGate } from '@fluxwp/runtime';
import type { ThemeColorsInput } from '@fluxwp/tokens';
import current from './current.json';
import logos from './logo.json';

export interface MobileBrand extends Brand {
  androidPackage: string;
  allowedSites: string[];
  /** Config paths the brand fixes over site and user layers (white-label). */
  locked: string[];
  theme: { light?: ThemeColorsInput; dark?: ThemeColorsInput } | null;
}

setBrand(current as unknown as Brand);

export const brand = coreBrand as MobileBrand;
export const gate = brandGate(brand);

/** Config layer 2 (plan §8.1): white-label theme + locked paths. FluxWP's own brand adds nothing. */
export const brandLayer = brand.theme ? { theme: { light: brand.theme.light, dark: brand.theme.dark }, locked: brand.locked } : { locked: brand.locked };

/** Brand logo SVG markup for light and dark surfaces. */
export const LOGO = logos.light;
export const LOGO_DARK = logos.dark;
