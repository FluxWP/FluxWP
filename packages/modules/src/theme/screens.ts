// Screens of this module folder, by flxapp_modules id (src/registry.ts).
import type { ModuleScreens } from '../registry';

export const screens: ModuleScreens = {
  'flxt-theme-settings': () => import('./ThemeSettingsScreen').then((m) => ({ default: m.ThemeSettingsScreen })),
  'flxt-theme-system': () => import('./SystemScreen').then((m) => ({ default: m.SystemScreen })),
};
