// Screens of this module folder, by flxapp_modules id (src/registry.ts).
import type { ModuleScreens } from '../registry';

export const screens: ModuleScreens = {
  'flxk-packages': () => import('./PackagesScreen').then((m) => ({ default: m.PackagesScreen })),
  'flxk-settings': () => import('./SettingsScreen').then((m) => ({ default: m.SettingsScreen })),
};
