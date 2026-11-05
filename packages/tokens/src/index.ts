// Tasarım token'ları. Web'de CSS değişkenine, RN'de tema objesine çevrilir.
// Marka renkleri (primary/accent) brand.json'dan gelir ve buradaki varsayılanı ezer.

export type ThemeMode = 'light' | 'dark';

export interface BrandColors {
  primary: string;
  primaryContrast: string;
  accent: string;
}

export const space = { 0: 0, 1: 4, 2: 8, 3: 12, 4: 16, 5: 20, 6: 24, 8: 32, 10: 40, 12: 48 } as const;
// Flux Admin medium radius: card 14, control 10, small 8.
export const radius = { sm: 8, md: 10, lg: 14, xl: 20, full: 999 } as const;
const system = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
export const font = {
  family: `Figtree, ${system}`,
  heading: `Figtree, Nunito, ${system}`,
  mono: 'ui-monospace, SFMono-Regular, Menlo, monospace',
  size: { xs: 12, sm: 13, md: 14, lg: 16, xl: 20, xxl: 28 },
  weight: { regular: 400, medium: 500, semibold: 600, bold: 700 },
} as const;

// Antrasit + beige, matching Flux Admin defaults and themes/fluxwp theme.json
// (base, mint, line, ink). Brand indigo / amber override primary and accent.
export const palette = {
  light: {
    bg: '#F3F2EF',
    surface: '#FFFFFF',
    surface2: '#F8F5F0',
    border: '#E6E0D6',
    text: '#111827',
    textMuted: '#4B5563',
    sidebar: '#FFFFFF',
    sidebarText: '#1F2937',
    sidebarActive: '#EEF2FF',
    danger: '#B42318',
    warning: '#A04A06',
    success: '#1F7A4D',
    info: '#1D5FA8',
    surface3: '#F2EEE7',
    overlay: 'rgba(6,7,14,.5)',
  },
  dark: {
    bg: '#101114',
    surface: '#191B20',
    surface2: '#1C1F27',
    border: '#272A34',
    text: '#E8E9EF',
    textMuted: '#9A9EAE',
    sidebar: '#14161A',
    sidebarText: '#E8E9EF',
    sidebarActive: '#252833',
    danger: '#FF8A80',
    warning: '#F0A35E',
    success: '#3DDC97',
    info: '#8DBBF2',
    surface3: '#252833',
    overlay: 'rgba(0,0,0,.6)',
  },
} as const;

export type ColorKey = keyof (typeof palette)['light'];
export type ThemeColors = Record<ColorKey, string> & BrandColors;

export function themeColors(mode: ThemeMode, brand: BrandColors): ThemeColors {
  return { ...palette[mode], ...brand };
}

const kebab = (s: string) => s.replace(/[A-Z]/g, (m) => '-' + m.toLowerCase());

/** Web: `--flux-*` CSS değişkenleri. */
export function toCssVars(mode: ThemeMode, brand: BrandColors): Record<string, string> {
  const vars: Record<string, string> = {};
  for (const [k, v] of Object.entries(themeColors(mode, brand))) vars[`--flux-${kebab(k)}`] = v;
  for (const [k, v] of Object.entries(space)) vars[`--flux-space-${k}`] = `${v}px`;
  for (const [k, v] of Object.entries(radius)) vars[`--flux-radius-${k}`] = `${v}px`;
  for (const [k, v] of Object.entries(font.size)) vars[`--flux-font-${k}`] = `${v}px`;
  vars['--flux-font-family'] = font.family;
  vars['--flux-font-heading'] = font.heading;
  vars['--flux-font-mono'] = font.mono;
  return vars;
}

/** React Native: düz tema objesi. */
export function nativeTheme(mode: ThemeMode, brand: BrandColors) {
  return { mode, colors: themeColors(mode, brand), space, radius, font };
}

export * from './theme';
