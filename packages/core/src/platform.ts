// Platforma özgü işler (Tauri, tarayıcı, React Native) bu arayüzle core'a verilir.

export interface KeyValueStore {
  get(key: string): Promise<string | null>;
  set(key: string, value: string): Promise<void>;
  remove(key: string): Promise<void>;
}

export interface OAuthRedirect {
  /** Yetkilendirme isteğinde kullanılacak redirect_uri. */
  redirectUri: string;
  /** Tarayıcı dönüşünü bekler, gelen tam adresi verir. */
  wait(): Promise<URL>;
  cancel(): void;
}

export interface Platform {
  kind: 'web' | 'desktop' | 'mobile';
  /** CORS'suz fetch (Tauri: plugin-http). Yoksa global fetch. */
  fetch: typeof fetch;
  /** Belirteçler: Keychain / Credential Manager / SecureStore. */
  secure: KeyValueStore;
  /** Gizli olmayan ayarlar (hesap listesi, düzen, tema). */
  storage: KeyValueStore;
  openExternal(url: string): Promise<void>;
  /** OAuth dönüşü için loopback sunucu ya da deep link hazırlar. `path` (mobil): "oauth/callback" | "appauth/callback". */
  prepareOAuthRedirect(path?: string): Promise<OAuthRedirect>;
  /** Yerel ekranı olmayan panel sayfasını ayrı pencerede açar. */
  openWebView?(url: string, title: string): Promise<void>;
  /** `openWebView` ile aynı kapı; başlık isteğe bağlı (runtime `openWeb`). */
  openWeb?(url: string, title?: string): Promise<void>;
  /**
   * Panel sayfasını uygulama penceresinin içine, verilen alana gömer (masaüstü).
   * Sayfa "FLXApp/" user agent'ıyla açılır; Flux App panelin üst/sol barını gizler.
   * `hooks` (masaüstü): gezinti ve köprü mesajı. Eski çağrılar yalnız url + rect verir.
   */
  embedWebView?(url: string, rect: EmbedRect, hooks?: EmbedHooks): Promise<EmbeddedView>;
  /** Uygulama güncellemesi (masaüstü). Yeni sürüm yoksa null. */
  checkUpdate?(): Promise<AppUpdate | null>;
  /** Uygulama ön planda mı (polling yalnız görünürken). Yoksa hep görünür sayılır. */
  visibility?: Visibility;
  /** Yerel bildirim (masaüstü /sync olayları; mobilde push dışı uyarılar). */
  notify?(n: { title: string; body?: string; data?: Record<string, unknown> }): Promise<void>;
  /** Her isteğe eklenecek platform başlıkları (ör. X-FluxWP-Platform). */
  headers?(): Record<string, string>;
}

export interface Visibility {
  visible(): boolean;
  /** Dinleyiciyi kaldıran fonksiyon döner. */
  subscribe(fn: (visible: boolean) => void): () => void;
}

export interface AppUpdate {
  version: string;
  notes?: string;
  date?: string;
  /** true: uygulama içinde indirilip kurulur; false: yalnız indirme adresi açılır (imzalı paket yok). */
  installable: boolean;
  /** Arka planda indirir (installable). p: 0..1, boyut bilinmiyorsa -1. */
  download(onProgress?: (p: number) => void): Promise<void>;
  /** İndirileni kurup uygulamayı yeniden başlatır; installable değilse indirme sayfasını açar. */
  install(): Promise<void>;
}

/** "1.2.10" > "1.2.9"; "-" sonrası (beta) sürüm aynı çekirdekten küçüktür. */
export function newerVersion(a: string, b: string): boolean {
  const [ca, pa = ''] = a.split('-', 2);
  const [cb, pb = ''] = b.split('-', 2);
  const x = ca!.split('.').map(Number);
  const y = cb!.split('.').map(Number);
  for (let i = 0; i < Math.max(x.length, y.length); i++) {
    const d = (x[i] ?? 0) - (y[i] ?? 0);
    if (d) return d > 0;
  }
  if (pa === pb) return false;
  if (!pa) return true;
  if (!pb) return false;
  return pa.localeCompare(pb, 'en', { numeric: true }) > 0;
}

export interface EmbedRect { x: number; y: number; width: number; height: number }

/** Optional embed callbacks. `onNavigate` fires before the load is shown; return false to cancel when the host can. */
export interface EmbedHooks {
  onNavigate?(url: string): boolean | void;
  onMessage?(raw: unknown): void;
}

export interface EmbeddedView {
  setRect(r: EmbedRect): Promise<void>;
  setVisible(v: boolean): Promise<void>;
  reload(): Promise<void>;
  close(): Promise<void>;
}

let current: Platform | null = null;

export function setPlatform(p: Platform): void {
  current = p;
}

export function platform(): Platform {
  if (!current) throw new Error('platform not set');
  return current;
}

/** Bellek içi depo: testler ve demo için. */
export function memoryStore(seed: Record<string, string> = {}): KeyValueStore {
  const m = new Map(Object.entries(seed));
  return {
    get: async (k) => m.get(k) ?? null,
    set: async (k, v) => void m.set(k, v),
    remove: async (k) => void m.delete(k),
  };
}
