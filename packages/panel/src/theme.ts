// Per-account site theme (WordPress theme.json + Flux Admin + overrides) as CSS variables.
import { brand } from '@fluxwp/core';
import { themeFromConfig, type AppTheme, type ThemeMode } from '@fluxwp/tokens';

const kebab = (s: string) => s.replace(/[A-Z]/g, (m) => '-' + m.toLowerCase());

export function applyTheme(theme: AppTheme) {
  const root = document.documentElement;
  for (const [k, v] of Object.entries(theme.colors)) root.style.setProperty('--flux-' + kebab(k), v);
  root.style.setProperty('--flux-primary-contrast', theme.colors.onPrimary);
  for (const [k, v] of Object.entries(theme.space)) root.style.setProperty(`--flux-space-${k}`, `${v}px`);
  for (const [k, v] of Object.entries(theme.radius)) root.style.setProperty(`--flux-radius-${k}`, `${v}px`);
  for (const [k, v] of Object.entries(theme.font.size)) root.style.setProperty(`--flux-font-${k}`, `${v}px`);
  root.style.setProperty('--flux-font-family', theme.font.family);
  root.style.setProperty('--flux-font-heading', theme.font.heading);
  root.style.setProperty('--flux-font-mono', theme.font.mono);
  root.dataset.theme = theme.mode;
  root.dataset.density = theme.density;
}

export function brandTheme(mode: Exclude<ThemeMode, 'system'>): AppTheme {
  return themeFromConfig({ mode, brand: brand.colors });
}
