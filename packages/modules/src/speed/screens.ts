// Screens of this module folder, by flxapp_modules id (src/registry.ts).
import type { ModuleScreens } from '../registry';

export const screens: ModuleScreens = {
  'flxp-speed': () => import('./SpeedScreen').then((m) => ({ default: m.SpeedScreen })),
};
