// Unistyles themes. Start from the build brand; the active account's site theme replaces them at runtime
// (useAccountTheme) without re-rendering the tree. Light/dark follows the device unless the user picks one.
import { StyleSheet, UnistylesRuntime } from 'react-native-unistyles';
import { themeFromConfig, type AppTheme, type ThemeInput } from '@fluxwp/tokens';
import { brand } from '@/brand';
import { prefs } from './prefs';

const base = (mode: 'light' | 'dark', config?: ThemeInput['config']) =>
  themeFromConfig({ mode, brand: brand.colors, config, overrides: brand.theme ?? undefined });

const themes = { light: base('light'), dark: base('dark') };
const breakpoints = { xs: 0, md: 768, lg: 1100 };

type AppThemes = typeof themes;
type AppBreakpoints = typeof breakpoints;

/* eslint-disable @typescript-eslint/no-empty-object-type -- Unistyles module augmentation */
declare module 'react-native-unistyles' {
  export interface UnistylesThemes extends AppThemes {}
  export interface UnistylesBreakpoints extends AppBreakpoints {}
}
/* eslint-enable @typescript-eslint/no-empty-object-type */

const mode = prefs.get().theme;
StyleSheet.configure({
  themes,
  breakpoints,
  settings: mode === 'system' ? { adaptiveThemes: true } : { initialTheme: mode },
});

/** Applies an account's site theme (config.theme) to both modes. */
export function applySiteTheme(config: ThemeInput['config']): void {
  UnistylesRuntime.updateTheme('light', () => base('light', config));
  UnistylesRuntime.updateTheme('dark', () => base('dark', config));
}

export function applyThemeMode(m: 'system' | 'light' | 'dark'): void {
  if (m === 'system') UnistylesRuntime.setAdaptiveThemes(true);
  else {
    UnistylesRuntime.setAdaptiveThemes(false);
    UnistylesRuntime.setTheme(m);
  }
}

export type { AppTheme };
