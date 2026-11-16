// Screens of this module folder, by flxapp_modules id (src/registry.ts).
import type { ModuleScreens } from '../registry';

export const screens: ModuleScreens = {
  'flxcb-inbox': () => import('./ChatbotInboxScreen').then((m) => ({ default: m.ChatbotInboxScreen })),
  'flxcb-settings': () => import('./ChatbotSettingsScreen').then((m) => ({ default: m.ChatbotSettingsScreen })),
};
