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
  function Bound() {
    return createElement(Suspense, { fallback: createElement(Loading) }, createElement(Screen));
  }
  return Bound;
}

const loaders = collectScreens(import.meta.glob<ModuleScreens>('./*/screens.ts', { eager: true, import: 'screens' }));

/** Every module screen by flxapp_modules id (from src/<folder>/screens.ts, see registry.ts). */
export const moduleScreens: Record<string, ComponentType> = Object.fromEntries(
  Object.entries(loaders).map(([id, load]) => [id, lazyScreen(load)]),
);
