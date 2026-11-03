// Tauri platformu: CORS'suz HTTP (Rust tarafı), Keychain, sistem tarayıcısı, loopback OAuth, panel penceresi.
import { invoke } from '@tauri-apps/api/core';
import { listen } from '@tauri-apps/api/event';
import { WebviewWindow } from '@tauri-apps/api/webviewWindow';
import { Webview } from '@tauri-apps/api/webview';
import { getCurrentWindow } from '@tauri-apps/api/window';
import { LogicalPosition, LogicalSize } from '@tauri-apps/api/dpi';
import { fetch as tauriFetch } from '@tauri-apps/plugin-http';
import { openUrl } from '@tauri-apps/plugin-opener';
import { check, type Update } from '@tauri-apps/plugin-updater';
import { relaunch } from '@tauri-apps/plugin-process';
import { getVersion } from '@tauri-apps/api/app';
import { brand, createI18n, newerVersion, type AppUpdate, type EmbedRect, type EmbeddedView, type KeyValueStore, type OAuthRedirect, type Platform } from '@fluxwp/core';
import { usePrefs } from '@fluxwp/panel';

const secure: KeyValueStore = {
  get: (key) => invoke<string | null>('secure_get', { key }),
  set: (key, value) => invoke('secure_set', { key, value }),
  remove: (key) => invoke('secure_remove', { key }),
};

const storage: KeyValueStore = {
  get: async (k) => localStorage.getItem('flux.' + k),
  set: async (k, v) => localStorage.setItem('flux.' + k, v),
  remove: async (k) => localStorage.removeItem('flux.' + k),
};

async function prepareOAuthRedirect(): Promise<OAuthRedirect> {
  const t = createI18n(usePrefs.getState().locale ?? navigator.language).t;
  const port = await invoke<number>('oauth_listen', { title: t('oauth.doneTitle'), body: t('oauth.doneBody'), brand: brand.name });
  let unlisten: (() => void) | null = null;
  let reject: ((e: Error) => void) | null = null;
  const result = new Promise<URL>((res, rej) => {
    reject = rej;
    void listen<string>('oauth://callback', (e) => {
      unlisten?.();
      res(new URL(e.payload));
    }).then((u) => (unlisten = u));
  });
  return {
    redirectUri: `http://127.0.0.1:${port}/callback`,
    wait: () => result,
    cancel: () => {
      unlisten?.();
      void invoke('oauth_cancel');
      reject?.(new Error('cancelled'));
    },
  };
}

let panelSeq = 0;
// "FLXApp/" user agent'ı Flux App'nın gömülü görünümünü (panel üst/sol barı gizli) açar.
const embedUA = () => `Mozilla/5.0 (Macintosh) AppleWebKit/605.1.15 FLXApp/0.2.2 ${brand.slug}`;

async function embedWebView(url: string, r: EmbedRect): Promise<EmbeddedView> {
  // Tauri "unstable" özelliği: ana pencereye çocuk webview. wp-admin iframe'e izin vermediği için gerekli.
  const wv = new Webview(getCurrentWindow(), `embed-${++panelSeq}`, {
    url,
    x: r.x,
    y: r.y,
    width: r.width,
    height: r.height,
    userAgent: embedUA(),
  });
  await new Promise<void>((res, rej) => {
    void wv.once('tauri://created', () => res());
    void wv.once('tauri://error', (e) => rej(new Error(String(e.payload))));
  });
  return {
    setRect: async (n) => {
      await wv.setPosition(new LogicalPosition(n.x, n.y));
      await wv.setSize(new LogicalSize(Math.max(1, n.width), Math.max(1, n.height)));
    },
    setVisible: (v) => (v ? wv.show() : wv.hide()),
    reload: async () => {
      // Oturum adresi tek seferlik; yeniden yüklemede webview'in kendi geçmişi kullanılır.
      await invoke('webview_reload', { label: wv.label }).catch(() => wv.setFocus());
    },
    close: () => wv.close(),
  };
}

/**
 * Güncelleme: önce Tauri updater (imzalı .app.tar.gz, arka planda indirilir, yeniden başlatınca kurulur).
 * İmzalı paket yoksa güncelleme sunucusunun /latest ucuna bakılır; yeni sürüm varsa dmg indirme adresi açılır.
 */
async function checkUpdate(): Promise<AppUpdate | null> {
  if (!brand.updateUrl) return null;
  let signedError: unknown = null;
  try {
    const u: Update | null = await check({ timeout: 20_000 });
    if (u) {
      let total = 0;
      let got = 0;
      return {
        version: u.version,
        notes: u.body,
        date: u.date,
        installable: true,
        download: (onProgress) =>
          u.download((e) => {
            if (e.event === 'Started') total = e.data.contentLength ?? 0;
            else if (e.event === 'Progress') {
              got += e.data.chunkLength;
              onProgress?.(total ? got / total : -1);
            } else if (e.event === 'Finished') onProgress?.(1);
          }),
        install: async () => {
          await u.install();
          await relaunch();
        },
      };
    }
  } catch (e) {
    signedError = e;
  }
  const r = await tauriFetch(`${brand.updateUrl.replace(/\/$/, '')}/v1/fluxapp/${brand.slug}/latest`);
  if (!r.ok) throw signedError ?? new Error(`HTTP ${r.status}`);
  const d = (await r.json()) as { version: string | null; notes?: string; released?: string; assets?: Record<string, { url: string } | undefined>; page?: string };
  const cur = await getVersion();
  if (!d.version || !newerVersion(d.version, cur)) return null;
  // İmzalı paket yoksa kurulum dosyası açılır: Windows'ta setup.exe (yoksa msi), macOS'ta dmg.
  const win = /Windows/i.test(navigator.userAgent);
  const url = (win ? d.assets?.exe?.url ?? d.assets?.msi?.url : d.assets?.dmg?.url) ?? d.page ?? '';
  return {
    version: d.version,
    notes: d.notes,
    date: d.released,
    installable: false,
    download: async () => undefined,
    install: () => openUrl(url),
  };
}

export const desktopPlatform: Platform = {
  kind: 'desktop',
  fetch: tauriFetch as typeof fetch,
  secure,
  storage,
  openExternal: (url) => openUrl(url),
  prepareOAuthRedirect,
  embedWebView,
  checkUpdate,
  async openWebView(url, title) {
    // wp-admin iframe'e izin vermez (X-Frame-Options); ayrı pencere açılır.
    // "FLXApp/" user agent'ı Flux App'nın gömülü görünümünü (menüsüz panel) açar.
    const w = new WebviewWindow(`panel-${++panelSeq}`, {
      url,
      title: `${title} · ${brand.name}`,
      width: 1200,
      height: 820,
      userAgent: embedUA(),
    });
    await new Promise<void>((res, rej) => {
      void w.once('tauri://created', () => res());
      void w.once('tauri://error', (e) => rej(new Error(String(e.payload))));
    });
  },
};
