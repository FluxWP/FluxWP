// Tasarım token'ları. Web'de CSS değişkenine, RN'de tema objesine çevrilir.
// Marka renkleri (primary/accent) brand.json'dan gelir ve buradaki varsayılanı ezer.

export type ThemeMode = 'light' | 'dark';

export interface BrandColors {
  primary: string;
  primaryContrast: string;
  accent: string;
}

export const space = { 0: 0, 1: 4, 2: 8, 3: 12, 4: 16, 5: 20, 6: 24, 8: 32, 10: 40, 12: 48 } as const;
export const radius = { sm: 6, md: 10, lg: 14, xl: 20, full: 999 } as const;
export const font = {
  family: 'Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
  mono: 'ui-monospace, SFMono-Regular, Menlo, monospace',
  size: { xs: 12, sm: 13, md: 14, lg: 16, xl: 20, xxl: 28 },
  weight: { regular: 400, medium: 500, semibold: 600, bold: 700 },
} as const;

export const palette = {
  light: {
    bg: '#F6F7F9',
    surface: '#FFFFFF',
    surface2: '#F0F2F5',
    border: '#E3E6EB',
    text: '#14171C',
    textMuted: '#5F6773',
    sidebar: '#FFFFFF',
    sidebarText: '#2A2F37',
    sidebarActive: '#EEF1F6',
    danger: '#D93B3B',
    warning: '#C98A00',
    success: '#1F9D55',
    overlay: 'rgba(15,18,24,.45)',
  },
  dark: {
    bg: '#0E1014',
    surface: '#161A20',
    surface2: '#1D222A',
    border: '#272D36',
    text: '#E8EBF0',
    textMuted: '#98A1AE',
    sidebar: '#12151A',
    sidebarText: '#C9CFD8',
    sidebarActive: '#1F252E',
    danger: '#F06464',
    warning: '#E8B03A',
    success: '#3CC27A',
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
  vars['--flux-font-mono'] = font.mono;
  return vars;
}

/** React Native: düz tema objesi. */
export function nativeTheme(mode: ThemeMode, brand: BrandColors) {
  return { mode, colors: themeColors(mode, brand), space, radius, font };
}
