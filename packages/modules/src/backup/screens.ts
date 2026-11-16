// Screens of this module folder, by flxapp_modules id (src/registry.ts).
import type { ModuleScreens } from '../registry';

export const screens: ModuleScreens = {
  'flxy-backup': () => import('./BackupScreen').then((m) => ({ default: m.BackupScreen })),
};
