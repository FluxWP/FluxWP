// Screens of this module folder, by flxapp_modules id (src/registry.ts).
import type { ModuleScreens } from '../registry';

export const screens: ModuleScreens = {
  'flxf-forms': () => import('./FormsScreen').then((m) => ({ default: m.FormsScreen })),
};
