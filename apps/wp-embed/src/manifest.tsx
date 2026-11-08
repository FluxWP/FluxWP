// Manifest screens inside wp-admin: same DOM renderer as the desktop panel, cookie + nonce instead of OAuth.
import { useMemo, useState } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { I18nextProvider } from 'react-i18next';
import { createClient, memoryStore, setPlatform, trimEnd, type Account } from '@fluxwp/core';
import type { ScreenType } from '@fluxwp/manifest';
import { AccountScope, appResources, normalizeLocale, type Renderer } from '@fluxwp/runtime';
import i18next from 'i18next';
import { ManifestScreen, RenderOverlays, webUI } from '@fluxwp/render-web';
import '@fluxwp/ui/src/styles.css';
import '@fluxwp/render-web/styles.css';

export interface ManifestMountOptions {
  module: string;
  screen: string;
  params?: Record<string, string>;
  rest: string;
  nonce: string;
  locale?: string;
}

const SCREENS: ReadonlySet<ScreenType> = new Set(['list', 'detail', 'form', 'dashboard', 'settings', 'content', 'tabs', 'native', 'web']);

function siteFromRest(rest: string): string {
  return trimEnd(rest.replace(/\/wp-json\/?$/, '')) || location.origin;
}

function EmbedHost({ o }: { o: ManifestMountOptions }) {
  const [stack, setStack] = useState([{ module: o.module, screen: o.screen, params: o.params ?? {} }]);
  const top = stack[stack.length - 1]!;
  const i18n = useMemo(() => {
    const inst = i18next.createInstance();
    void inst.init({ resources: appResources, lng: normalizeLocale(o.locale ?? document.documentElement.lang), fallbackLng: 'en', interpolation: { escapeValue: false }, initAsync: false });
    return inst;
  }, [o.locale]);
  const site = siteFromRest(o.rest);
  const account = useMemo<Account>(() => ({
    id: 'wp-embed', siteUrl: site, siteName: '', siteIcon: '', userId: 0, userName: '', userAvatar: '', authKind: 'apikey', addedAt: 0,
  }), [site]);
  const api = useMemo(() => createClient({
    siteUrl: site,
    credentials: 'same-origin',
    getCredentials: async () => null,
    extraHeaders: () => ({ 'X-WP-Nonce': o.nonce, 'X-FluxWP-Platform': 'desktop' }),
  }), [site, o.nonce]);
  const renderer = useMemo<Renderer>(() => ({
    screens: SCREENS,
    native: new Set<string>(),
    t: (key, vars) => i18n.t(key, vars ?? {}),
    ui: webUI((key) => i18n.t(key)),
    nav: {
      push: (route) => {
        if (route.kind === 'manifest') setStack((s) => [...s, { module: route.module, screen: route.screen, params: route.params }]);
        else if (route.kind === 'url') window.open(route.url, '_blank', 'noopener');
        else if (route.kind === 'web') location.assign(site.replace(/\/+$/, '') + '/wp-admin/' + route.path.replace(/^\/+/, ''));
      },
      replace: (route) => {
        if (route.kind === 'manifest') setStack((s) => [...s.slice(0, -1), { module: route.module, screen: route.screen, params: route.params }]);
      },
      back: () => setStack((s) => (s.length > 1 ? s.slice(0, -1) : s)),
      openUrl: (url) => void window.open(url, '_blank', 'noopener'),
    },
  }), [i18n, site]);

  return (
    <I18nextProvider i18n={i18n}>
      <AccountScope account={account} api={api} renderer={renderer} app={{ version: '0.3.1' }}>
        <RenderOverlays t={(key) => i18n.t(key)} />
        <div dir={i18n.language === 'ar' ? 'rtl' : 'ltr'}><ManifestScreen module={top.module} screen={top.screen} params={top.params} /></div>
      </AccountScope>
    </I18nextProvider>
  );
}

const roots = new Map<HTMLElement, Root>();

/** Opens one manifest screen in a wp-admin page. Returns an unmount function. */
export function mountManifest(el: HTMLElement, o: ManifestMountOptions): () => void {
  setPlatform({
    kind: 'web',
    fetch: (...a) => fetch(...a),
    secure: memoryStore(),
    storage: memoryStore(),
    headers: () => ({ 'X-FluxWP-Platform': 'desktop' }),
    openExternal: async (u) => void window.open(u, '_blank', 'noopener'),
    prepareOAuthRedirect: async () => { throw new Error('oauth_unsupported'); },
  });
  el.classList.add('flux-root');
  const root = createRoot(el);
  root.render(<EmbedHost o={o} />);
  roots.set(el, root);
  return () => {
    root.unmount();
    roots.delete(el);
  };
}
