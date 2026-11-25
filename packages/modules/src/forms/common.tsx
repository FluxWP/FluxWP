import { createContext, useContext, type ReactNode } from 'react';
import type { Translate } from './i18n';

export type Page = 'dashboard' | 'forms' | 'responses' | 'settings' | 'create' | 'payments' | 'automations' | 'system' | 'addons';

/** React pages. "create" (the builder) also needs the block editor, which only its wp-admin page loads. */
export const REACT_PAGES: Page[] = ['dashboard', 'forms', 'responses', 'settings', 'create', 'payments', 'automations', 'system', 'addons'];

/** The builder's wp-admin page (Forms › New form; &form=N edits). */
export const BUILDER_SLUG = 'Emsfb_create';

/** Pages only some users or setups have: shown when PHP lists them (#flxf-islands data-tabs). */
export const MENU_PAGES: Page[] = ['create', 'payments', 'automations', 'system', 'addons'];

export interface RouteOpts {
  form?: number;
  entry?: number;
  /** Automation: id, -1 for a new one. */
  flow?: number;
  /** Automations: "runs" for the run log. */
  tab?: string;
}

export interface Route {
  page: Page;
  form: number;
  entry: number;
  flow: number;
  tab: string;
}

export interface FormsCtx {
  t: Translate;
  wp: boolean;
  go: (page: Page, opts?: RouteOpts) => void;
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

/** A labelled form row. */
export function Field({ label, note, children }: { label: string; note?: string; children: ReactNode }) {
  return (
    <div className="flxfrm-field">
      <span className="flxfrm-field__label">{label}</span>
      {children}
      {note ? <span className="flxfrm-muted">{note}</span> : null}
    </div>
  );
}

/** Copies text and says so. */
export async function copyText(text: string, toast: FormsCtx['toast'], t: Translate): Promise<void> {
  try {
    await navigator.clipboard.writeText(text);
    toast(t('Copied.'));
  } catch {
    toast(text);
  }
}
