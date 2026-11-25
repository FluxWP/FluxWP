import { createContext, useContext } from 'react';
import type { Translate } from './i18n';

export type Page = 'dashboard' | 'forms' | 'responses' | 'settings' | 'create' | 'panel' | 'payments' | 'automations' | 'system' | 'addons';

/** React pages. */
export const REACT_PAGES: Page[] = ['dashboard', 'forms', 'responses', 'settings'];

/** Pages PHP prints (hidden island under the shared header): wp-admin slug. */
export const ISLANDS: Partial<Record<Page, string>> = {
  create: 'Emsfb_create',
  panel: 'Emsfb',
  payments: 'flxf_payments',
  automations: 'flxf_automations',
  system: 'flxf_system',
  addons: 'Emsfb_addon',
};

export interface Route {
  page: Page;
  form: number;
  entry: number;
}

export interface FormsCtx {
  t: Translate;
  wp: boolean;
  go: (page: Page, opts?: { form?: number; entry?: number }) => void;
  toast: (text: string, kind?: 'ok' | 'error') => void;
  num: (n: number) => string;
}

export const FormsContext = createContext<FormsCtx | null>(null);

export function useForms(): FormsCtx {
  const c = useContext(FormsContext);
  if (!c) throw new Error('forms context');
  return c;
}

export function errorText(error: unknown, fallback: string): string {
  if (error && typeof error === 'object' && 'message' in error && typeof error.message === 'string') return error.message;
  return fallback;
}

export function payloadError(errors: ReadonlyArray<{ message: string }> | undefined | null): string | null {
  if (!errors?.length) return null;
  return errors.map((e) => e.message).join(' ');
}

export function typeLabel(type: string, t: Translate): string {
  const map: Record<string, string> = {
    form: t('Form'),
    survey: t('Survey'),
    subscribe: t('Subscribe'),
    payment: t('Payment'),
    register: t('Register'),
    login: t('Login'),
    smart: t('Smart form'),
  };
  return map[type] ?? type;
}
