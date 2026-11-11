// Tek kod, iki yer: FluxWP ve WordPress admin. Anahtar = sunucudaki flxapp_modules id'si.
// Ekranlar açılınca yüklenir; kabuk hepsini baştan çekmez.
import { createElement, lazy, Suspense, type ComponentType } from 'react';
import { Loading } from '@fluxwp/ui';

export { ModuleHostProvider, useHost, useModuleApi, type ModuleHost } from './host';

function lazyScreen(load: () => Promise<{ default: ComponentType }>): ComponentType {
  const Screen = lazy(load);
  function Bound() {
    return createElement(Suspense, { fallback: createElement(Loading) }, createElement(Screen));
  }
  return Bound;
}

export const moduleScreens: Record<string, ComponentType> = {
  'flxb-review': lazyScreen(() => import('./review/ReviewScreen').then((m) => ({ default: m.ReviewScreen }))),
  'flxg-tasks': lazyScreen(() => import('./tasks/TasksScreen').then((m) => ({ default: m.TasksScreen }))),
  'flxc-inbox': lazyScreen(() => import('./whatsapp/WhatsAppScreen').then((m) => ({ default: m.WhatsAppScreen }))),
};
