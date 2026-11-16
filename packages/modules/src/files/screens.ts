// Screens of this module folder, by flxapp_modules id (src/registry.ts).
import type { ModuleScreens } from '../registry';

export const screens: ModuleScreens = {
  'flxfm-files': () => import('./FilesScreen').then((m) => ({ default: m.FilesScreen })),
};
