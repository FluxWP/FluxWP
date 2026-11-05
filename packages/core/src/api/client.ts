import { platform } from '../platform';
import { trimEnd } from '../text';

export class ApiError extends Error {
  constructor(
    public status: number,
    public code: string,
    message: string,
    public data?: unknown,
  ) {
    super(message);
    this.name = 'ApiError';
  }
}

export interface Credentials {
  /** Erişim belirteci (flxat_), API anahtarı (flxak_) ya da Basic için base64(kullanıcı:uygulama şifresi). */
  token: string;
  /** "basic": WordPress Application Passwords (Flux App olmayan site). */
  scheme?: 'bearer' | 'basic';
}

const platformHeaders = (): Record<string, string> => {
  try {
    return platform().headers?.() ?? {};
  } catch {
    return {};
  }
};

export interface ClientOptions {
  siteUrl: string;
  /** Güncel kimlik; yoksa anonim istek. */
  getCredentials: () => Promise<Credentials | null>;
  /** 401'de bir kez çağrılır; yeni kimlik dönerse istek tekrarlanır. */
  onUnauthorized?: () => Promise<Credentials | null>;
  /** Sunucu Authorization başlığını düşürüyorsa X-FluxWP-Token kullanılır. */
  useHeaderFallback?: boolean;
  fetchImpl?: typeof fetch;
  /** Her isteğe eklenecek başlıklar (WP admin: X-WP-Nonce). */
  extraHeaders?: () => Record<string, string>;
  /** WP admin içinde çerezle kimlik: 'same-origin'. */
  credentials?: RequestCredentials;
}

export interface RequestOptions {
  method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
  query?: Record<string, string | number | boolean | undefined | null>;
  body?: unknown;
  form?: Record<string, string>;
  /** Dosya yükleme (multipart). Content-Type tarayıcıya bırakılır. */
  formData?: FormData;
  /** 'blob': medya gibi ham cevap. */
  as?: 'json' | 'blob';
  signal?: AbortSignal;
  /** Kimlik eklemeden gönder (OAuth token ucu gibi). */
  anonymous?: boolean;
}

export function restBase(siteUrl: string): string {
  return trimEnd(siteUrl) + '/wp-json/';
}

export function buildUrl(siteUrl: string, path: string, query?: RequestOptions['query']): string {
  // Sunucunun verdiği tam adres (ör. medya) yalnız aynı sitedeyse kabul edilir; belirteç başka hosta gitmesin.
  if (/^https?:\/\//i.test(path)) {
    const abs = new URL(path);
    if (abs.origin !== new URL(restBase(siteUrl)).origin) throw new ApiError(0, 'foreign_url', abs.origin);
    for (const [k, v] of Object.entries(query ?? {})) if (v !== undefined && v !== null && v !== '') abs.searchParams.set(k, String(v));
    return abs.toString();
  }
  const u = new URL(path.replace(/^\/+/, ''), restBase(siteUrl));
  u.searchParams.set('_locale', 'user');
  for (const [k, v] of Object.entries(query ?? {})) {
    if (v !== undefined && v !== null && v !== '') u.searchParams.set(k, String(v));
  }
  return u.toString();
}

export type ApiClient = <T>(path: string, opts?: RequestOptions) => Promise<T>;

export function createClient(o: ClientOptions): ApiClient {
  const doFetch = (...a: Parameters<typeof fetch>) => (o.fetchImpl ?? platform().fetch)(...a);

  const send = async (path: string, opts: RequestOptions, cred: Credentials | null) => {
    const headers: Record<string, string> = { Accept: 'application/json', ...platformHeaders(), ...o.extraHeaders?.() };
    let body: BodyInit | undefined;
    if (opts.formData) {
      body = opts.formData;
    } else if (opts.form) {
      headers['Content-Type'] = 'application/x-www-form-urlencoded';
      body = new URLSearchParams(opts.form).toString();
    } else if (opts.body !== undefined) {
      headers['Content-Type'] = 'application/json';
      body = JSON.stringify(opts.body);
    }
    if (cred && !opts.anonymous) {
      if (cred.scheme === 'basic') headers.Authorization = `Basic ${cred.token}`;
      else if (o.useHeaderFallback) headers['X-FluxWP-Token'] = cred.token;
      else headers.Authorization = `Bearer ${cred.token}`;
    }
    return doFetch(buildUrl(o.siteUrl, path, opts.query), {
      method: opts.method ?? (body ? 'POST' : 'GET'),
      headers,
      body,
      signal: opts.signal,
      credentials: o.credentials,
    });
  };

  return async function request<T>(path: string, opts: RequestOptions = {}): Promise<T> {
    let cred = opts.anonymous ? null : await o.getCredentials();
    let res = await send(path, opts, cred);
    if (res.status === 401 && !opts.anonymous && o.onUnauthorized) {
      cred = await o.onUnauthorized();
      if (cred) res = await send(path, opts, cred);
    }
    if (opts.as === 'blob' && res.ok) return (await res.blob()) as T;
    const text = await res.text();
    let json: unknown = null;
    if (text) {
      try {
        json = JSON.parse(text);
      } catch {
        throw new ApiError(res.status, 'invalid_json', text.slice(0, 200));
      }
    }
    if (!res.ok) {
      const j = (json ?? {}) as { code?: string; message?: string; error?: string; error_description?: string; data?: unknown };
      throw new ApiError(res.status, j.code ?? j.error ?? 'http_' + res.status, j.message ?? j.error_description ?? res.statusText, j.data);
    }
    return json as T;
  };
}
