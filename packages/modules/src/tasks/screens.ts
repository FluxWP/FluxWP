// Screens of this module folder, by flxapp_modules id (src/registry.ts).
import type { ModuleScreens } from '../registry';

export const screens: ModuleScreens = {
  'flxg-tasks': () => import('./TasksScreen').then((m) => ({ default: m.TasksScreen })),
};
