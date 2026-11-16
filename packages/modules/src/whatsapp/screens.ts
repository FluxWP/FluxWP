// Screens of this module folder, by flxapp_modules id (src/registry.ts).
import type { ModuleScreens } from '../registry';

export const screens: ModuleScreens = {
  'flxc-inbox': () => import('./WhatsAppScreen').then((m) => ({ default: m.WhatsAppScreen })),
};
