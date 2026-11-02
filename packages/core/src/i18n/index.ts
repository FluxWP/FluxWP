import i18next, { type i18n as I18n } from 'i18next';
import { brand } from '../brand';
import tr from './locales/tr.json';
import en from './locales/en.json';

export const resources = { tr: { translation: tr }, en: { translation: en } } as const;
export type Locale = keyof typeof resources;
export const locales = Object.keys(resources) as Locale[];

/** "tr_TR" / "en-US" → "tr" / "en"; desteklenmiyorsa marka varsayılanı. */
export function normalizeLocale(input?: string | null): Locale {
  const l = (input ?? '').toLowerCase().split(/[-_]/)[0] as Locale;
  return locales.includes(l) ? l : (brand.defaultLocale as Locale);
}

export function createI18n(locale?: string): I18n {
  const inst = i18next.createInstance();
  void inst.init({
    resources,
    lng: normalizeLocale(locale),
    fallbackLng: 'tr',
    interpolation: { escapeValue: false },
    initAsync: false,
  });
  return inst;
}
