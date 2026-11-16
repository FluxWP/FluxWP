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
  'flxbk-dashboard': lazyScreen(() => import('./blocks/BlocksScreen').then((m) => ({ default: m.BlocksScreen }))),
  'flxl-lang': lazyScreen(() => import('./lang/LanguagesScreen').then((m) => ({ default: m.LanguagesScreen }))),
  'flxr-roles': lazyScreen(() => import('./roles/RolesScreen').then((m) => ({ default: m.RolesScreen }))),
  'flxk-packages': lazyScreen(() => import('./setup/PackagesScreen').then((m) => ({ default: m.PackagesScreen }))),
  'flxk-settings': lazyScreen(() => import('./setup/SettingsScreen').then((m) => ({ default: m.SettingsScreen }))),
  'flxapp-settings': lazyScreen(() => import('./app-settings/AppSettingsScreen').then((m) => ({ default: m.AppSettingsScreen }))),
  'flxapp-access': lazyScreen(() => import('./app-settings/AccessScreen').then((m) => ({ default: m.AccessScreen }))),
  'flxseo-seo': lazyScreen(() => import('./seo/SeoScreen').then((m) => ({ default: m.SeoScreen }))),
  'flxfm-files': lazyScreen(() => import('./files/FilesScreen').then((m) => ({ default: m.FilesScreen }))),
  'flxc-inbox': lazyScreen(() => import('./whatsapp/WhatsAppScreen').then((m) => ({ default: m.WhatsAppScreen }))),
};
