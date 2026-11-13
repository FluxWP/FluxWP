// Shared ten-locale strings; mobile-only copy currently has TR/EN with English fallback.
import { createInstance } from 'i18next';
import { initReactI18next } from 'react-i18next';
import { getLocales } from 'expo-localization';
import { appResources, normalizeLocale, queryClientFor, type Locale } from '@fluxwp/runtime';
import { accountsStore, getDemoLocale, setDemoLocale } from '@fluxwp/core';
import { prefs } from '@/theme/prefs';
import { setRequestLocale } from '@/platform';
import tr from './tr.json';
import en from './en.json';

const mobile = { tr, en } as const;

export const deviceLocale = (): Locale => normalizeLocale(getLocales()[0]?.languageTag);

const i18n = createInstance();
const initialLocale = prefs.get().locale ? normalizeLocale(prefs.get().locale) : deviceLocale();
setDemoLocale(initialLocale);
setRequestLocale(initialLocale);
i18n.on('languageChanged', (language) => {
  setDemoLocale(language);
  setRequestLocale(language);
  void refreshDemoAccounts();
  // Site labels (menu, tabs) come back in the requested language: demo data resets, real sites refetch.
  for (const account of accountsStore.getState().accounts) {
    const client = queryClientFor(account.id);
    void (account.authKind === 'demo' ? client.resetQueries() : client.invalidateQueries());
  }
});
void i18n.use(initReactI18next).init({
  resources: Object.fromEntries((Object.keys(appResources) as Locale[]).map((l) => [l, { translation: { ...appResources[l].translation, mobile: mobile[l === 'tr' ? 'tr' : 'en'] } }])),
  lng: initialLocale,
  fallbackLng: 'en',
  interpolation: { escapeValue: false },
  initAsync: false,
});

export function setLanguage(locale: string | null): void {
  void i18n.changeLanguage(locale ? normalizeLocale(locale) : deviceLocale());
}

/** Upgrade old demo names locally; real connected accounts are never renamed. */
export async function refreshDemoAccounts(): Promise<void> {
  const userName = getDemoLocale() === 'en' ? 'Demo User' : 'Demo Kullanıcı';
  for (const account of accountsStore.getState().accounts) {
    if (account.authKind === 'demo' && (account.siteName !== 'Demo Site' || account.userName !== userName)) await accountsStore.getState().update(account.id, { siteName: 'Demo Site', userName });
  }
}

export { i18n };
