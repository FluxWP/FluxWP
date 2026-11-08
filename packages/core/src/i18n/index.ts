import i18next, { type i18n as I18n } from 'i18next';
import { brand } from '../brand';
import tr from './locales/tr.json';
import en from './locales/en.json';
import de from './locales/de.json';
import es from './locales/es.json';
import fr from './locales/fr.json';
import pt_br from './locales/pt-br.json';
import ja from './locales/ja.json';
import zh from './locales/zh.json';
import ar from './locales/ar.json';
import ru from './locales/ru.json';

export const resources = {en: { translation: en },tr: { translation: tr },de: { translation: de },es: { translation: es },fr: { translation: fr },"pt-br": { translation: pt_br },ja: { translation: ja },zh: { translation: zh },ar: { translation: ar },ru: { translation: ru }} as const;
export type Locale = keyof typeof resources;
export const locales = Object.keys(resources) as Locale[];

/** "tr_TR" / "en-US" → "tr" / "en"; desteklenmiyorsa marka varsayılanı. */
export function normalizeLocale(input?: string | null): Locale {
  const full = (input ?? '').toLowerCase().replaceAll('_', '-');
  const base = full.split('-')[0]!;
  const l = (full.startsWith('pt') ? 'pt-br' : full === 'zh-cn' ? 'zh' : full) as Locale;
  if (locales.includes(l)) return l;
  if (locales.includes(base as Locale)) return base as Locale;
  return locales.includes(brand.defaultLocale as Locale) ? brand.defaultLocale as Locale : 'en';
}

export function createI18n(locale?: string): I18n {
  const inst = i18next.createInstance();
  void inst.init({
    resources,
    lng: normalizeLocale(locale),
    fallbackLng: 'en',
    interpolation: { escapeValue: false },
    initAsync: false,
  });
  return inst;
}
