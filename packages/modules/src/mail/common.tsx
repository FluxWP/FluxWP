import { createContext, useContext } from 'react';
import type { Translate } from './i18n';

export type Page = 'templates' | 'logs' | 'settings' | 'system';

export interface MailCtx {
  t: Translate;
  page: Page;
  go: (page: Page, query?: Record<string, string>) => void;
  toast: (text: string, kind?: 'ok' | 'error') => void;
  logStatus: string;
}

export const MailContext = createContext<MailCtx | null>(null);

export function useMail(): MailCtx {
  const c = useContext(MailContext);
  if (!c) throw new Error('mail context');
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
