// Screens of this module folder, by flxapp_modules id (src/registry.ts).
import type { ModuleScreens } from '../registry';

export const screens: ModuleScreens = {
  'flxads-ads': () => import('./AdsScreen').then((m) => ({ default: m.AdsScreen })),
};
