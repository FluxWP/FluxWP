// Tek kod, iki yer: FluxWP ve WordPress admin. Anahtar = sunucudaki flxapp_modules id'si.
import type { ComponentType } from 'react';
import { ReviewScreen } from './review/ReviewScreen';
import { TasksScreen } from './tasks/TasksScreen';
import { WhatsAppScreen } from './whatsapp/WhatsAppScreen';

export { ModuleHostProvider, useHost, useModuleApi, type ModuleHost } from './host';

export const moduleScreens: Record<string, ComponentType> = {
  'flxb-review': ReviewScreen,
  'flxg-tasks': TasksScreen,
  'flxc-inbox': WhatsAppScreen,
};
