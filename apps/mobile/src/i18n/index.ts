// i18next: shared strings (@fluxwp/core + @fluxwp/runtime) + mobile-only strings. TR default, EN.
import { createInstance } from 'i18next';
import { initReactI18next } from 'react-i18next';
import { getLocales } from 'expo-localization';
import { appResources, normalizeLocale, type Locale } from '@fluxwp/runtime';
import { prefs } from '@/theme/prefs';
import tr from './tr.json';
import en from './en.json';

const mobile = { tr, en } as const;

export const deviceLocale = (): Locale => normalizeLocale(getLocales()[0]?.languageTag);

const i18n = createInstance();
void i18n.use(initReactI18next).init({
  resources: Object.fromEntries((Object.keys(appResources) as Locale[]).map((l) => [l, { translation: { ...appResources[l].translation, mobile: mobile[l] } }])),
  lng: prefs.get().locale ? normalizeLocale(prefs.get().locale) : deviceLocale(),
  fallbackLng: 'tr',
  interpolation: { escapeValue: false },
  initAsync: false,
});

export function setLanguage(locale: string | null): void {
  void i18n.changeLanguage(locale ? normalizeLocale(locale) : deviceLocale());
}

export { i18n };
