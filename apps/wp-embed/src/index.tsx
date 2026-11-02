// WordPress admin gömmesi: eklenti admin sayfası modül ekranını FluxWP ile aynı kodla açar.
//   <div id="flxc-app"></div>
//   FluxModules.mount(document.getElementById('flxc-app'), { module: 'flxc-inbox', rest: '/wp-json/', nonce: '…', locale: 'tr_TR' });
import { StrictMode, useEffect, useState } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { I18nextProvider } from 'react-i18next';
import { brand, createClient, createI18n, setPlatform, memoryStore } from '@fluxwp/core';
import type { BrandColors } from '@fluxwp/tokens';
import { toCssVars } from '@fluxwp/tokens';
import { ModuleHostProvider, moduleScreens, type ModuleHost } from '@fluxwp/modules';
import '@fluxwp/ui/src/styles.css';
import './embed.css';

export interface MountOptions {
  /** flxapp_modules id'si, ör. 'flxc-inbox'. */
  module: string;
  /** REST kökü (rest_url()), ör. https://site.org/wp-json/ */
  rest: string;
  /** wp_create_nonce('wp_rest') */
  nonce: string;
  locale?: string;
  theme?: 'auto' | 'light' | 'dark';
  colors?: Partial<BrandColors>;
  /** Geliştirme/test: gerçek sunucu yerine bu fetch. */
  fetchImpl?: typeof fetch;
}

let nonce = '';
const roots = new Map<HTMLElement, Root>();

function siteFromRest(rest: string): string {
  return rest.replace(/\/wp-json\/?$/, '').replace(/\/+$/, '') || location.origin;
}

function Themed({ el, theme }: { el: HTMLElement; theme: NonNullable<MountOptions['theme']> }) {
  const mq = matchMedia('(prefers-color-scheme: dark)');
  const [dark, setDark] = useState(mq.matches);
  useEffect(() => {
    const on = (e: MediaQueryListEvent) => setDark(e.matches);
    mq.addEventListener('change', on);
    return () => mq.removeEventListener('change', on);
  }, [mq]);
  const mode = theme === 'auto' ? (dark ? 'dark' : 'light') : theme;
  useEffect(() => void (el.dataset.theme = mode), [el, mode]);
  return null;
}

export function mount(el: HTMLElement, o: MountOptions): () => void {
  const Screen = moduleScreens[o.module];
  if (!Screen) throw new Error(`FluxModules: unknown module ${o.module}`);
  nonce = o.nonce;
  const site = siteFromRest(o.rest);
  setPlatform({
    kind: 'web',
    fetch: o.fetchImpl ?? ((...a) => fetch(...a)),
    secure: memoryStore(),
    storage: memoryStore(),
    openExternal: async (u) => void window.open(u, '_blank', 'noopener'),
    prepareOAuthRedirect: async () => {
      throw new Error('oauth_unsupported');
    },
  });
  const client = createClient({
    siteUrl: site,
    fetchImpl: o.fetchImpl,
    credentials: 'same-origin',
    getCredentials: async () => null,
    extraHeaders: () => ({ 'X-WP-Nonce': nonce }),
  });
  const host: ModuleHost = {
    mode: 'wp',
    client,
    siteUrl: site,
    scope: 'wp',
    openUrl: (u) => (u.startsWith(location.origin) ? (location.href = u) : void window.open(u, '_blank', 'noopener')),
    // Test ortamında (sahte fetch) medya da istemciden geçsin; gerçek WP'de çerezli adres doğrudan.
    blobMedia: !!o.fetchImpl,
    setNonce: (n) => (nonce = n),
  };
  const colors = { ...brand.colors, ...o.colors };
  el.classList.add('flux-root');
  const apply = (mode: 'light' | 'dark') => {
    for (const [k, v] of Object.entries(toCssVars(mode, colors))) el.style.setProperty(k, v);
  };
  const obs = new MutationObserver(() => apply(el.dataset.theme === 'dark' ? 'dark' : 'light'));
  obs.observe(el, { attributes: true, attributeFilter: ['data-theme'] });
  apply('light');

  const qc = new QueryClient({ defaultOptions: { queries: { retry: 1, refetchOnWindowFocus: true } } });
  const i18n = createI18n(o.locale ?? document.documentElement.lang);
  const root = createRoot(el);
  root.render(
    <StrictMode>
      <I18nextProvider i18n={i18n}>
        <QueryClientProvider client={qc}>
          <ModuleHostProvider value={host}>
            <Themed el={el} theme={o.theme ?? 'auto'} />
            <Screen />
          </ModuleHostProvider>
        </QueryClientProvider>
      </I18nextProvider>
    </StrictMode>,
  );
  roots.set(el, root);
  return () => {
    obs.disconnect();
    root.unmount();
    roots.delete(el);
  };
}

/** Sunucu yeni nonce verdiğinde (ör. /sync cevabı) güncelle. */
export function setNonce(n: string) {
  nonce = n;
}

export const modules = Object.keys(moduleScreens);
