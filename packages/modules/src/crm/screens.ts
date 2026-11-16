// Screens of this module folder, by flxapp_modules id (src/registry.ts).
import type { ModuleScreens } from '../registry';

export const screens: ModuleScreens = {
  crm: () => import('./CrmScreen').then((m) => ({ default: m.CrmScreen })),
};
