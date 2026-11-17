// Screens of this module folder, by flxapp_modules id (src/registry.ts).
import type { ModuleScreens } from '../registry';

export const screens: ModuleScreens = {
  'flxi-mail': () => import('./MailScreen').then((m) => ({ default: m.MailScreen })),
};
