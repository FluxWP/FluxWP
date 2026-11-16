// Screens of this module folder, by flxapp_modules id (src/registry.ts).
import type { ModuleScreens } from '../registry';

export const screens: ModuleScreens = {
  'flxan-stats': () => import('./StatsScreen').then((m) => ({ default: m.StatsScreen })),
  'flxan-journeys': () => import('./JourneysScreen').then((m) => ({ default: m.JourneysScreen })),
  'flxan-settings': () => import('./SettingsScreen').then((m) => ({ default: m.SettingsScreen })),
  'flxan-telemetry': () => import('./TelemetryScreen').then((m) => ({ default: m.TelemetryScreen })),
};
