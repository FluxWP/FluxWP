// Cihaz tercihleri: tema ve dil.
import { create } from 'zustand';
import { platform, type Locale } from '@fluxwp/core';

export type ThemePref = 'system' | 'light' | 'dark';

interface PrefsState {
  theme: ThemePref;
  locale: Locale | null;
  load(): Promise<void>;
  setTheme(t: ThemePref): void;
  setLocale(l: Locale | null): void;
}

export const usePrefs = create<PrefsState>((set, get) => {
  const save = () => void platform().storage.set('prefs', JSON.stringify({ theme: get().theme, locale: get().locale }));
  return {
    theme: 'system',
    locale: null,
    async load() {
      try {
        const raw = await platform().storage.get('prefs');
        if (raw) set(JSON.parse(raw));
      } catch {
        /* varsayılan */
      }
    },
    setTheme: (theme) => (set({ theme }), save()),
    setLocale: (locale) => (set({ locale }), save()),
  };
});
