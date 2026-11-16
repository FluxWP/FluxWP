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
  'flxrc-recaptcha': lazyScreen(() => import('./recaptcha/RecaptchaScreen').then((m) => ({ default: m.RecaptchaScreen }))),
  'flxbk-dashboard': lazyScreen(() => import('./blocks/BlocksScreen').then((m) => ({ default: m.BlocksScreen }))),
  'flxy-backup': lazyScreen(() => import('./backup/BackupScreen').then((m) => ({ default: m.BackupScreen }))),
  'flxl-lang': lazyScreen(() => import('./lang/LanguagesScreen').then((m) => ({ default: m.LanguagesScreen }))),
  'flxr-roles': lazyScreen(() => import('./roles/RolesScreen').then((m) => ({ default: m.RolesScreen }))),
  'studio': lazyScreen(() => import('./studio/StudioScreen').then((m) => ({ default: m.StudioScreen }))),
  'flxcb-settings': lazyScreen(() => import('./chatbot/ChatbotSettingsScreen').then((m) => ({ default: m.ChatbotSettingsScreen }))),
  'flxcb-inbox': lazyScreen(() => import('./chatbot/ChatbotInboxScreen').then((m) => ({ default: m.ChatbotInboxScreen }))),
  'flxan-stats': lazyScreen(() => import('./analytics/StatsScreen').then((m) => ({ default: m.StatsScreen }))),
  'flxan-journeys': lazyScreen(() => import('./analytics/JourneysScreen').then((m) => ({ default: m.JourneysScreen }))),
  'flxan-settings': lazyScreen(() => import('./analytics/SettingsScreen').then((m) => ({ default: m.SettingsScreen }))),
  'flxan-telemetry': lazyScreen(() => import('./analytics/TelemetryScreen').then((m) => ({ default: m.TelemetryScreen }))),
  'flxp-speed': lazyScreen(() => import('./speed/SpeedScreen').then((m) => ({ default: m.SpeedScreen }))),
  'flxai-admin': lazyScreen(() => import('./ai/AiScreen').then((m) => ({ default: m.AiScreen }))),
  'flxa-admin': lazyScreen(() => import('./admin/AdminScreen').then((m) => ({ default: m.AdminScreen }))),
  'flxt-theme-settings': lazyScreen(() => import('./theme/ThemeSettingsScreen').then((m) => ({ default: m.ThemeSettingsScreen }))),
  'flxt-theme-system': lazyScreen(() => import('./theme/SystemScreen').then((m) => ({ default: m.SystemScreen }))),
  crm: lazyScreen(() => import('./crm/CrmScreen').then((m) => ({ default: m.CrmScreen }))),
  'flxk-packages': lazyScreen(() => import('./setup/PackagesScreen').then((m) => ({ default: m.PackagesScreen }))),
  'flxk-settings': lazyScreen(() => import('./setup/SettingsScreen').then((m) => ({ default: m.SettingsScreen }))),
  'flxapp-settings': lazyScreen(() => import('./app-settings/AppSettingsScreen').then((m) => ({ default: m.AppSettingsScreen }))),
  'flxapp-access': lazyScreen(() => import('./app-settings/AccessScreen').then((m) => ({ default: m.AccessScreen }))),
  'flxseo-seo': lazyScreen(() => import('./seo/SeoScreen').then((m) => ({ default: m.SeoScreen }))),
  'flxwk-wiki': lazyScreen(() => import('./wiki/WikiScreen').then((m) => ({ default: m.WikiScreen }))),
  'flxsm-social': lazyScreen(() => import('./social/SocialScreen').then((m) => ({ default: m.SocialScreen }))),
  'flxfm-files': lazyScreen(() => import('./files/FilesScreen').then((m) => ({ default: m.FilesScreen }))),
  'flxc-inbox': lazyScreen(() => import('./whatsapp/WhatsAppScreen').then((m) => ({ default: m.WhatsAppScreen }))),
};
