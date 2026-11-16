// Screens of this module folder, by flxapp_modules id (src/registry.ts).
import type { ModuleScreens } from '../registry';

export const screens: ModuleScreens = {
  'flxs-certificates': () => import('./CertificatesScreen').then((m) => ({ default: m.CertificatesScreen })),
};
