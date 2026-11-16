// Screens of this module folder, by flxapp_modules id (src/registry.ts).
import type { ModuleScreens } from '../registry';

export const screens: ModuleScreens = {
  'flxrc-recaptcha': () => import('./RecaptchaScreen').then((m) => ({ default: m.RecaptchaScreen })),
};
