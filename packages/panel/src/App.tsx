import { useEffect, useMemo, useState } from 'react';
import { I18nextProvider } from 'react-i18next';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { toCssVars } from '@fluxwp/tokens';
import { accountsStore, brand, createI18n, normalizeLocale } from '@fluxwp/core';
import { useAccounts, useActiveAccount } from './state/hooks';
import { usePrefs } from './state/prefs';
import { AddSite } from './screens/AddSite';
import { Shell } from './screens/Shell';

const queryClient = new QueryClient({ defaultOptions: { queries: { retry: 1, refetchOnWindowFocus: true } } });

function useSystemDark() {
  const mq = typeof matchMedia === 'function' ? matchMedia('(prefers-color-scheme: dark)') : null;
  const [dark, setDark] = useState(mq?.matches ?? false);
  useEffect(() => {
    if (!mq) return;
    const on = (e: MediaQueryListEvent) => setDark(e.matches);
    mq.addEventListener('change', on);
    return () => mq.removeEventListener('change', on);
  }, [mq]);
  return dark;
}

function Root() {
  const { ready } = useAccounts();
  const active = useActiveAccount();
  if (!ready) return null;
  return active ? <Shell key={active.id} account={active} /> : <AddSite />;
}

export function App() {
  const prefs = usePrefs();
  const systemDark = useSystemDark();
  const mode = prefs.theme === 'system' ? (systemDark ? 'dark' : 'light') : prefs.theme;
  const locale = normalizeLocale(prefs.locale ?? (typeof navigator !== 'undefined' ? navigator.language : brand.defaultLocale));
  const i18n = useMemo(() => createI18n(locale), []); // eslint-disable-line react-hooks/exhaustive-deps

  useEffect(() => {
    void prefs.load();
    void accountsStore.getState().load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    void i18n.changeLanguage(locale);
    document.documentElement.lang = locale;
  }, [i18n, locale]);

  useEffect(() => {
    const root = document.documentElement;
    for (const [k, v] of Object.entries(toCssVars(mode, brand.colors))) root.style.setProperty(k, v);
    root.dataset.theme = mode;
  }, [mode]);

  return (
    <I18nextProvider i18n={i18n}>
      <QueryClientProvider client={queryClient}>
        <Root />
      </QueryClientProvider>
    </I18nextProvider>
  );
}
