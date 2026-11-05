// Device preferences (plan §8.1 layer 6): theme mode and app language. Read synchronously from MMKV so
// the first frame already has the right theme.
import { create } from 'zustand';
import { device } from '@/platform/storage';

export type ThemePref = 'system' | 'light' | 'dark';

interface PrefsState {
  theme: ThemePref;
  /** null = device language. */
  locale: string | null;
  setTheme(t: ThemePref): void;
  setLocale(l: string | null): void;
}

const KEY = 'prefs';

const read = (): Pick<PrefsState, 'theme' | 'locale'> => {
  try {
    const p = JSON.parse(device.getString(KEY) ?? '{}') as Partial<PrefsState>;
    return { theme: p.theme === 'light' || p.theme === 'dark' ? p.theme : 'system', locale: typeof p.locale === 'string' ? p.locale : null };
  } catch {
    return { theme: 'system', locale: null };
  }
};

export const usePrefs = create<PrefsState>((set, get) => {
  const save = () => device.set(KEY, JSON.stringify({ theme: get().theme, locale: get().locale }));
  return {
    ...read(),
    setTheme: (theme) => (set({ theme }), save()),
    setLocale: (locale) => (set({ locale }), save()),
  };
});

export const prefs = { get: () => usePrefs.getState() };
