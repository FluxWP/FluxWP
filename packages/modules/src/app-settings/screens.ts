// Screens of this module folder, by flxapp_modules id (src/registry.ts).
import type { ModuleScreens } from '../registry';

export const screens: ModuleScreens = {
  'flxapp-settings': () => import('./AppSettingsScreen').then((m) => ({ default: m.AppSettingsScreen })),
  'flxapp-access': () => import('./AccessScreen').then((m) => ({ default: m.AccessScreen })),
};
