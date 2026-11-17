// The wp-admin mount shared by the FluxWP screens bundle (index.tsx) and the shared runtime (runtime.tsx):
// one React root per screen element, cookie + nonce client, Flux Admin theme, one React Query cache per bundle.
import { Component, StrictMode, useEffect, useState, type ComponentType, type ReactNode } from 'react';
import { createRoot } from 'react-dom/client';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { I18nextProvider } from 'react-i18next';
import { brand, createClient, createI18n, setPlatform, memoryStore, trimEnd } from '@fluxwp/core';
import type { BrandColors } from '@fluxwp/tokens';
import { toCssVars } from '@fluxwp/tokens';
import { ModuleHostProvider, type ModuleHost } from '@fluxwp/modules/src/host';
import { flxStoreRecord } from '@fluxwp/graphql';

/** Where the mount finds screens: FluxWP's own (index.tsx) or the ones plugins register (runtime.tsx). */
export interface ScreenSource {
  get: (id: string) => ComponentType | undefined;
  preload: (id: string) => Promise<void>;
  /** Called once for every i18next instance the mount creates (runtime.tsx adds plugin translations). */
  i18n?: (inst: ReturnType<typeof createI18n>) => void;
}

export interface EmbedApi {
  mount: (el: HTMLElement, o: MountOptions) => () => void;
  unmount: (el: HTMLElement) => boolean;
  preload: (module: string) => Promise<void>;
  setNonce: (nonce: string) => void;
}

export interface MountOptions {
  /** flxapp_modules id'si, ör. 'flxc-inbox'. */
  module: string;
  /** REST kökü (rest_url()), ör. https://site.org/wp-json/ */
  rest: string;
  /** wp_create_nonce('wp_rest') */
  nonce: string;
  locale?: string;
  /** 'admin': follows Flux Admin's light / dark switch (html.flxa-dark), also when it changes on the page. */
  theme?: 'auto' | 'light' | 'dark' | 'admin';
  colors?: Partial<BrandColors>;
  /** Geliştirme/test: gerçek sunucu yerine bu fetch. */
  fetchImpl?: typeof fetch;
  /** Ekran başlığı ve geri dönüş için (ModuleHost'a aynen geçer). */
  siteName?: string;
  adminUrl?: string;
  appIcon?: string;
  /** Eklentinin vurgu rengi (--brand). */
  accent?: string;
  /** Flux AI ortak sohbeti sayfada yüklü. */
  aiEmbed?: boolean;
  /** İlk sayfa (WP alt menüsü), ör. 'dashboard'. */
  startPage?: string;
  /** Eski: klasik sayfası henüz kalkmamış paketlerin geçiş bağlantısı. Klasik ekranı kalkan paket vermez. */
  classicUrl?: string;
  /**
   * wp-admin screen shell (Flux GraphQL): the queries the server preloaded for this screen. The screen's
   * first queries are recorded; when they differ, they are sent to `url` so the next load preloads them.
   */
  learn?: { url: string; key: string; known: string[] };
}

/** Queries a screen sends in its first moments count as its first queries. */
const LEARN_MS = 2500;

const FAIL_TEXT: Record<string, { title: string; body: string; reload: string }> = {
  tr: {
    title: 'Bu ekran yüklenemedi',
    body: 'Eklenti yeni güncellendiyse tarayıcı ya da site önbelleği eski dosyaları tutuyor olabilir. Sayfayı sert yenileyin (Ctrl/Cmd + Shift + R); sürerse site önbelleğini temizleyin.',
    reload: 'Sayfayı yenile',
  },
  en: {
    title: 'This screen could not be loaded',
    body: 'If the plugin was just updated, the browser or site cache may still hold old files. Hard-reload the page (Ctrl/Cmd + Shift + R); if it persists, purge the site cache.',
    reload: 'Reload page',
  },
};

/**
 * A screen whose code cannot load (stale cached file, failed chunk, runtime error) used to leave a blank page.
 * This shows what happened and the way out instead: reload. There is no classic screen to fall back to.
 * (A bundle that cannot load at all is caught by flx-graphql flx_admin_screen_failsafe() with the same text.)
 */
class ScreenGuard extends Component<{ lang: string; children: ReactNode }, { failed: boolean }> {
  state = { failed: false };
  static getDerivedStateFromError() {
    return { failed: true };
  }
  componentDidCatch(error: unknown) {
    console.error('[FluxWP] screen failed to load', error);
  }
  render() {
    if (!this.state.failed) return this.props.children;
    const t = FAIL_TEXT[this.props.lang.slice(0, 2)] ?? FAIL_TEXT.en!;
    const btn = { padding: '8px 14px', borderRadius: 8, border: '1px solid currentColor', background: 'transparent', color: 'inherit', cursor: 'pointer', font: 'inherit', textDecoration: 'none' } as const;
    return (
      <div role="alert" style={{ maxWidth: 520, margin: '48px auto', padding: 24, textAlign: 'center', color: 'var(--flux-text, inherit)' }}>
        <h2 style={{ margin: '0 0 8px', font: 'inherit', fontSize: 18, fontWeight: 600 }}>{t.title}</h2>
        <p style={{ margin: '0 0 16px', opacity: 0.8 }}>{t.body}</p>
        <div style={{ display: 'flex', gap: 8, justifyContent: 'center', flexWrap: 'wrap' }}>
          <button type="button" style={btn} onClick={() => location.reload()}>
            {t.reload}
          </button>
        </div>
      </div>
    );
  }
}

export function createEmbed(screens: ScreenSource): EmbedApi {
  let nonce = '';
  const roots = new Map<HTMLElement, () => void>();

  /**
   * One React Query cache per bundle, kept across mounts: going from one screen to another in the same tab
   * (the Flux GraphQL screen navigator) shows what was already read at once. Reads younger than the stale
   * time are not sent again; older ones show and refresh in the background.
   */
  let sharedQc: QueryClient | null = null;
  function queryClient(): QueryClient {
    sharedQc ??= new QueryClient({
      defaultOptions: { queries: { retry: 1, refetchOnWindowFocus: true, staleTime: 30_000, gcTime: 15 * 60_000 } },
    });
    return sharedQc;
  }

  const i18ns = new Map<string, ReturnType<typeof createI18n>>();
  function i18nFor(locale: string) {
    let i = i18ns.get(locale);
    if (!i) {
      i = createI18n(locale);
      screens.i18n?.(i);
      i18ns.set(locale, i);
    }
    return i;
  }

  /** The wp-admin screen navigator (Flux GraphQL, assets/js/screens.js), when the page has it. */
  interface FlxScreensNav {
    go: (url: string) => boolean;
    setNonce?: (nonce: string) => void;
    nonce?: string;
  }
  const screensNav = (): FlxScreensNav | undefined => (window as { FlxScreens?: FlxScreensNav }).FlxScreens;

  function siteFromRest(rest: string): string {
    return trimEnd(rest.replace(/\/wp-json\/?$/, '')) || location.origin;
  }

  function Themed({ el, theme }: { el: HTMLElement; theme: NonNullable<MountOptions['theme']> }) {
    const mq = matchMedia('(prefers-color-scheme: dark)');
    const [dark, setDark] = useState(mq.matches);
    useEffect(() => {
      const on = (e: MediaQueryListEvent) => setDark(e.matches);
      mq.addEventListener('change', on);
      return () => mq.removeEventListener('change', on);
    }, [mq]);
    const html = document.documentElement;
    const [adminDark, setAdminDark] = useState(html.classList.contains('flxa-dark'));
    useEffect(() => {
      if (theme !== 'admin') return;
      const obs = new MutationObserver(() => setAdminDark(html.classList.contains('flxa-dark')));
      obs.observe(html, { attributes: true, attributeFilter: ['class'] });
      return () => obs.disconnect();
    }, [html, theme]);
    const mode = theme === 'auto' ? (dark ? 'dark' : 'light') : theme === 'admin' ? (adminDark ? 'dark' : 'light') : theme;
    useEffect(() => void (el.dataset.theme = mode), [el, mode]);
    return null;
  }

  function learnFirstQueries(o: MountOptions): () => void {
    const l = o.learn;
    if (!l?.url || !l.key) return () => undefined;
    const stop = flxStoreRecord();
    let sent = false;
    // Sent after LEARN_MS, or earlier when the user leaves the screen (another page, the navigator).
    const flush = () => {
      if (sent) return;
      sent = true;
      window.clearTimeout(timer);
      window.removeEventListener('pagehide', flush);
      const keys = stop().slice(0, 10);
      const known = new Set(l.known);
      if (!keys.length || (keys.length === known.size && keys.every((k) => known.has(k)))) return;
      const body = new FormData();
      body.append('action', 'flx_screen_learn');
      body.append('_wpnonce', nonce);
      body.append('key', l.key);
      for (const k of keys) body.append('keys[]', k);
      if (navigator.sendBeacon?.(l.url, body)) return;
      void fetch(l.url, { method: 'POST', body, credentials: 'same-origin', keepalive: true }).catch(() => undefined);
    };
    const timer = window.setTimeout(flush, LEARN_MS);
    window.addEventListener('pagehide', flush);
    return flush;
  }

  function mount(el: HTMLElement, o: MountOptions): () => void {
    const Screen = screens.get(o.module);
    if (!Screen) throw new Error(`FluxModules: unknown module ${o.module}`);
    // The navigator holds the newest nonce of the page (a screen may have renewed it).
    nonce = screensNav()?.nonce || o.nonce;
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
      // Another Flux screen opens in place (navigator); other wp-admin pages load as usual.
      openUrl: (u) => {
        if (screensNav()?.go(u)) return;
        if (u.startsWith(location.origin)) location.href = u;
        else window.open(u, '_blank', 'noopener');
      },
      // Test ortamında (sahte fetch) medya da istemciden geçsin; gerçek WP'de çerezli adres doğrudan.
      blobMedia: !!o.fetchImpl,
      setNonce: (n) => {
        nonce = n;
        screensNav()?.setNonce?.(n);
      },
      siteName: o.siteName,
      adminUrl: o.adminUrl,
      appIcon: o.appIcon,
      aiEmbed: o.aiEmbed,
      startPage: o.startPage || undefined,
      classicUrl: o.classicUrl,
    };
    const colors = { ...brand.colors, ...o.colors };
    el.classList.add('flux-root');
    if (o.accent) el.style.setProperty('--brand', o.accent);
    const apply = (mode: 'light' | 'dark') => {
      for (const [k, v] of Object.entries(toCssVars(mode, colors))) el.style.setProperty(k, v);
    };
    const obs = new MutationObserver(() => apply(el.dataset.theme === 'dark' ? 'dark' : 'light'));
    obs.observe(el, { attributes: true, attributeFilter: ['data-theme'] });
    apply('light');

    const qc = queryClient();
    const i18n = i18nFor(o.locale ?? document.documentElement.lang);
    el.dir = i18n.language === 'ar' ? 'rtl' : 'ltr';
    const unlearn = learnFirstQueries(o);
    const root = createRoot(el);
    root.render(
      <StrictMode>
        <I18nextProvider i18n={i18n}>
          <QueryClientProvider client={qc}>
            <ModuleHostProvider value={host}>
              <Themed el={el} theme={o.theme ?? 'auto'} />
              <ScreenGuard lang={i18n.language}>
                <Screen />
              </ScreenGuard>
            </ModuleHostProvider>
          </QueryClientProvider>
        </I18nextProvider>
      </StrictMode>,
    );
    const stop = () => {
      unlearn();
      obs.disconnect();
      root.unmount();
      roots.delete(el);
    };
    roots.set(el, stop);
    return stop;
  }

  /** Loads a screen's code ahead of mount(): the screen then paints without a loading fallback. */
  function preload(module: string): Promise<void> {
    return screens.preload(module);
  }

  /** Unmounts the screen in `el` (the navigator, before it shows the next screen). */
  function unmount(el: HTMLElement): boolean {
    const stop = roots.get(el);
    if (!stop) return false;
    stop();
    return true;
  }

  /** Sunucu yeni nonce verdiğinde (ör. /sync cevabı) güncelle. */
  function setNonce(n: string) {
    nonce = n;
  }

  return { mount, unmount, preload, setNonce };
}
