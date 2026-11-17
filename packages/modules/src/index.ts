// Tek kod, iki yer: FluxWP ve WordPress admin. Anahtar = sunucudaki flxapp_modules id'si.
// Ekranlar açılınca yüklenir; kabuk hepsini baştan çekmez. Her modül klasörü kendi src/<klasör>/screens.ts
// dosyasında ekranlarını bildirir (registry.ts); bu dosya paket PR'larında değişmez.
import { createElement, lazy, Suspense, type ComponentType } from 'react';
import { Loading } from '@fluxwp/ui';

export { ModuleHostProvider, useHost, useModuleApi, type ModuleHost } from './host';
export type { ModuleScreens, ScreenLoader } from './registry';
import { collectScreens, type ModuleScreens } from './registry';

function lazyScreen(load: () => Promise<{ default: ComponentType }>): ComponentType {
  const Screen = lazy(load);
  let ready: ComponentType | null = null;
  function Bound() {
    // Loaded ahead (preloadScreen): no Suspense fallback, so React does not hold the first paint
    // (React 19 keeps a shown fallback on screen for at least 300 ms).
    if (ready) return createElement(ready);
    return createElement(Suspense, { fallback: createElement(Loading) }, createElement(Screen));
  }
  (Bound as PreloadableScreen).preload = () =>
    load().then((m) => {
      ready = m.default;
    });
  return Bound;
}

const loaders = collectScreens(import.meta.glob<ModuleScreens>('./*/screens.ts', { eager: true, import: 'screens' }));

/** Every module screen by flxapp_modules id (from src/<folder>/screens.ts, see registry.ts). */
export const moduleScreens: Record<string, ComponentType> = Object.fromEntries(
  Object.entries(loaders).map(([id, load]) => [id, lazyScreen(load)]),
);

type PreloadableScreen = ComponentType & { preload?: () => Promise<void> };

/** Loads a screen's code before it mounts (wp-admin: first load and the screen navigator). */
export function preloadScreen(id: string): Promise<void> {
  const s = moduleScreens[id] as PreloadableScreen | undefined;
  return s?.preload ? s.preload().catch(() => undefined) : Promise.resolve();
}
