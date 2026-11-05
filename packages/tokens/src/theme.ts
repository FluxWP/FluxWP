// Account theme = palette + brand colors + config.theme (site: WordPress theme.json + Flux Admin + preset,
// derived on the server, plan §8.2) + white-label overrides. Web: toCssVars(); RN: Unistyles themes.
import { font, palette, radius as baseRadius, space, type BrandColors } from './index';

/** Colors a site config may set (flx-app-config/1 `theme.light|dark`). */
export interface ThemeColorsInput {
  primary?: string;
  onPrimary?: string;
  accent?: string;
  bg?: string;
  surface?: string;
  surface2?: string;
  border?: string;
  text?: string;
  textMuted?: string;
  danger?: string;
  warning?: string;
  success?: string;
  info?: string;
}

export interface ThemeConfigInput {
  light?: ThemeColorsInput;
  dark?: ThemeColorsInput;
  radius?: 'small' | 'medium' | 'large';
  density?: 'compact' | 'comfortable';
}

export type Mode = 'light' | 'dark';

export interface AppThemeColors {
  bg: string;
  surface: string;
  surface2: string;
  border: string;
  text: string;
  textMuted: string;
  sidebar: string;
  sidebarText: string;
  sidebarActive: string;
  danger: string;
  warning: string;
  success: string;
  info: string;
  surface3: string;
  overlay: string;
  primary: string;
  onPrimary: string;
  primarySoft: string;
  accent: string;
}

export interface AppTheme {
  mode: Mode;
  colors: AppThemeColors;
  space: typeof space;
  radius: { sm: number; md: number; lg: number; xl: number; full: number };
  font: typeof font;
  density: 'compact' | 'comfortable';
}

const HEX = /^#(?:[0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i;
const RGB = /^rgba?\([\d\s.,%]+\)$/i;
const isColor = (v: unknown): v is string => typeof v === 'string' && (HEX.test(v.trim()) || RGB.test(v.trim()));

const KEYS: (keyof ThemeColorsInput)[] = ['primary', 'onPrimary', 'accent', 'bg', 'surface', 'surface2', 'border', 'text', 'textMuted', 'danger', 'warning', 'success', 'info'];

/** Keeps only known keys with plain color values (server data is untrusted). */
export function cleanColors(input: unknown): ThemeColorsInput {
  const out: ThemeColorsInput = {};
  if (!input || typeof input !== 'object') return out;
  for (const k of KEYS) {
    const v = (input as Record<string, unknown>)[k];
    if (isColor(v)) out[k] = v.trim();
  }
  return out;
}

function rgb(hex: string): [number, number, number] | null {
  let h = hex.replace('#', '');
  if (h.length === 3 || h.length === 4) h = h.slice(0, 3).replace(/./g, (c) => c + c);
  if (h.length !== 6 && h.length !== 8) return null;
  return [parseInt(h.slice(0, 2), 16), parseInt(h.slice(2, 4), 16), parseInt(h.slice(4, 6), 16)];
}

/** WCAG relative luminance 0..1. */
export function luminance(color: string): number {
  const c = rgb(color);
  if (!c) return 0.5;
  const [r, g, b] = c.map((x) => {
    const s = x / 255;
    return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
  }) as [number, number, number];
  return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

export function contrast(a: string, b: string): number {
  const [x, y] = [luminance(a), luminance(b)].sort((m, n) => n - m) as [number, number];
  return (x + 0.05) / (y + 0.05);
}

/** Text color on a background (same rule as Flux Admin flxa_on_color). */
export const onColor = (bg: string): string => (contrast(bg, '#FFFFFF') >= contrast(bg, '#14171C') ? '#FFFFFF' : '#14171C');

export function alpha(color: string, a: number): string {
  const c = rgb(color);
  return c ? `rgba(${c[0]},${c[1]},${c[2]},${a})` : color;
}

const RADIUS = { small: 0.6, medium: 1, large: 1.4 } as const;

export interface ThemeInput {
  mode: Mode;
  brand: BrandColors;
  /** Site config theme (server). */
  config?: ThemeConfigInput;
  /** White-label brand overrides, applied last. */
  overrides?: { light?: ThemeColorsInput; dark?: ThemeColorsInput };
}

export function themeFromConfig({ mode, brand, config, overrides }: ThemeInput): AppTheme {
  const site = cleanColors(config?.[mode]);
  const over = cleanColors(overrides?.[mode]);
  const p = palette[mode];
  const pick = <K extends keyof ThemeColorsInput>(k: K, fallback: string) => over[k] ?? site[k] ?? fallback;

  let primary = pick('primary', brand.primary);
  // A dark brand primary disappears on dark surfaces when the site sends no dark tone.
  if (mode === 'dark' && !over.primary && !site.primary && contrast(primary, p.bg) < 3) primary = '#818CF8';
  const surface = pick('surface', p.surface);
  const colors: AppThemeColors = {
    bg: pick('bg', p.bg),
    surface,
    surface2: pick('surface2', p.surface2),
    border: pick('border', p.border),
    text: pick('text', p.text),
    textMuted: pick('textMuted', p.textMuted),
    sidebar: site.surface ?? p.sidebar,
    sidebarText: site.text ?? p.sidebarText,
    sidebarActive: alpha(primary, mode === 'dark' ? 0.22 : 0.1),
    danger: pick('danger', p.danger),
    warning: pick('warning', p.warning),
    success: pick('success', p.success),
    info: pick('info', p.info),
    surface3: p.surface3,
    overlay: p.overlay,
    primary,
    onPrimary: over.onPrimary ?? site.onPrimary ?? (primary === brand.primary ? brand.primaryContrast : onColor(primary)),
    primarySoft: alpha(primary, mode === 'dark' ? 0.22 : 0.1),
    accent: pick('accent', brand.accent),
  };
  const f = RADIUS[config?.radius ?? 'medium'];
  return {
    mode,
    colors,
    space,
    radius: { sm: Math.round(baseRadius.sm * f), md: Math.round(baseRadius.md * f), lg: Math.round(baseRadius.lg * f), xl: Math.round(baseRadius.xl * f), full: baseRadius.full },
    font,
    density: config?.density ?? 'comfortable',
  };
}
