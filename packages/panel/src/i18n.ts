// Panel strings: English is the fallback, Turkish is the other locale (core + runtime shell).
import i18next from 'i18next';
import { appResources, normalizeLocale } from '@fluxwp/runtime';

export const i18n = i18next.createInstance();

export function ensureI18n(lng?: string) {
  const language = normalizeLocale(lng);
  if (!i18n.isInitialized) {
    void i18n.init({
      resources: appResources,
      lng: language,
      fallbackLng: 'en',
      interpolation: { escapeValue: false },
      initAsync: false,
    });
  }
  return i18n;
}
