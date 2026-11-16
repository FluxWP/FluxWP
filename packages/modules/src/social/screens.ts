// Screens of this module folder, by flxapp_modules id (src/registry.ts).
import type { ModuleScreens } from '../registry';

export const screens: ModuleScreens = {
  'flxsm-social': () => import('./SocialScreen').then((m) => ({ default: m.SocialScreen })),
};
