// Module screen registry. Each module folder declares its own screens in `src/<folder>/screens.ts`:
//
//     import type { ModuleScreens } from '../registry';
//     export const screens: ModuleScreens = {
//       'flxg-tasks': () => import('./TasksScreen').then((m) => ({ default: m.TasksScreen })),
//     };
//
// index.ts collects every screens.ts (import.meta.glob), so adding a screen never edits a shared file and
// parallel package pull requests do not collide. Keys are flxapp_modules ids (unique, package prefixed).
import type { ComponentType } from 'react';

export type ScreenLoader = () => Promise<{ default: ComponentType }>;
export type ModuleScreens = Record<string, ScreenLoader>;

/** Folder screens.ts modules → one map. Sorted by folder; a repeated id keeps the first one and is reported. */
export function collectScreens(found: Record<string, ModuleScreens>): Record<string, ScreenLoader> {
  const out: Record<string, ScreenLoader> = {};
  for (const path of Object.keys(found).sort()) {
    for (const [id, load] of Object.entries(found[path] ?? {})) {
      if (id in out) {
        console.error(`[@fluxwp/modules] screen "${id}" is declared twice (${path}); the first one is used.`);
        continue;
      }
      out[id] = load;
    }
  }
  return out;
}
