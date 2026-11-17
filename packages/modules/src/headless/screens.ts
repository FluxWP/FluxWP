// Screens of this module folder, by flxapp_modules id (src/registry.ts).
import type { ModuleScreens } from '../registry';

export const screens: ModuleScreens = {
  'flxhl-headless': () => import('./HeadlessScreen').then((m) => ({ default: m.HeadlessScreen })),
};
