// Screens of this module folder, by flxapp_modules id (src/registry.ts).
import type { ModuleScreens } from '../registry';

export const screens: ModuleScreens = {
  'flxc-inbox': () => import('./WhatsAppScreen').then((m) => ({ default: m.WhatsAppScreen })),
  'flxc-settings': () => import('./SettingsScreen').then((m) => ({ default: m.SettingsScreen })),
  'flxc-reports': () => import('./ReportsScreen').then((m) => ({ default: m.ReportsScreen })),
};
