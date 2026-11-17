// Screens of this module folder, by flxapp_modules id (src/registry.ts).
import type { ModuleScreens } from '../registry';

export const screens: ModuleScreens = {
  'flxcm-store': () => import('./CommerceScreen').then((m) => ({ default: m.CommerceScreen })),
};
