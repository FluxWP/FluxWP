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
  /** İsteğe ek başlıklar (ör. `If-None-Match`). Kimlik başlığının üstüne yazamaz. */
  headers?: Record<string, string>;
  /** Multipart yüklemede 0..1. Platform `XMLHttpRequest` verirse ara değerler gelir. */
  onUploadProgress?: (ratio: number) => void;
  /** 'blob': medya gibi ham cevap. */
  as?: 'json' | 'blob';
  /**
   * true: gövdeyle birlikte durum ve yanıt başlıkları (`ETag`, `X-WP-TotalPages`).
   * 304 gövdesiz başarıdır (`data: null`), hata değildir.
   */
  raw?: boolean;
  signal?: AbortSignal;
  /** Kimlik eklemeden gönder (OAuth token ucu gibi). */
  anonymous?: boolean;
}

/** Case-insensitive response headers (Fetch `Headers#get`). */
export interface ResponseHeaders {
  get(name: string): string | null;
}

/** `raw: true` cevabı. `etag` başlıktan okunmuş hâli (yoksa null). */
export interface ApiResult<T> {
  data: T | null;
  status: number;
  etag: string | null;
  headers: ResponseHeaders;
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

export interface ApiClient {
  <T>(path: string, opts: RequestOptions & { raw: true }): Promise<ApiResult<T>>;
  <T>(path: string, opts?: RequestOptions): Promise<T>;
}

function responseHeaders(res: Response): ResponseHeaders {
  const map = new Map<string, string>();
  res.headers.forEach((value, key) => map.set(key.toLowerCase(), value));
  return { get: (name) => map.get(name.toLowerCase()) ?? null };
}

/** Upload progress needs XHR; fetch has none. Falls back to fetch when XHR is missing (tests, some hosts). */
function xhrSend(url: string, method: string, headers: Record<string, string>, body: BodyInit | undefined, signal: AbortSignal | undefined, onProgress: (ratio: number) => void): Promise<Response> {
  const XHR = globalThis.XMLHttpRequest;
  if (!XHR) return Promise.reject(new ApiError(0, 'no_xhr', 'upload progress unavailable'));
  return new Promise((resolve, reject) => {
    const xhr = new XHR();
    xhr.open(method, url);
    for (const [key, value] of Object.entries(headers)) xhr.setRequestHeader(key, value);
    xhr.upload.onprogress = (e) => {
      if (e.lengthComputable && e.total > 0) onProgress(e.loaded / e.total);
    };
    const done = () => {
      const h = new Headers();
      for (const line of xhr.getAllResponseHeaders().trim().split(/[\r\n]+/)) {
        const i = line.indexOf(':');
        if (i > 0) h.append(line.slice(0, i).trim(), line.slice(i + 1).trim());
      }
      resolve(new Response(xhr.responseText, { status: xhr.status, statusText: xhr.statusText, headers: h }));
    };
    xhr.onload = done;
    xhr.onerror = () => reject(new ApiError(0, 'network', 'network'));
    xhr.onabort = () => reject(new ApiError(0, 'aborted', 'aborted'));
    if (signal) {
      if (signal.aborted) xhr.abort();
      else signal.addEventListener('abort', () => xhr.abort(), { once: true });
    }
    xhr.send((body ?? null) as XMLHttpRequestBodyInit | null);
  });
}

export function createClient(o: ClientOptions): ApiClient {
  const doFetch = (...a: Parameters<typeof fetch>) => (o.fetchImpl ?? platform().fetch)(...a);

  const send = async (path: string, opts: RequestOptions, cred: Credentials | null) => {
    const headers: Record<string, string> = { Accept: 'application/json', ...platformHeaders(), ...o.extraHeaders?.(), ...opts.headers };
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
    const method = opts.method ?? (body ? 'POST' : 'GET');
    const url = buildUrl(o.siteUrl, path, opts.query);
    if (opts.onUploadProgress && typeof XMLHttpRequest !== 'undefined') return xhrSend(url, method, headers, body, opts.signal, opts.onUploadProgress);
    return doFetch(url, { method, headers, body, signal: opts.signal, credentials: o.credentials });
  };

  async function request<T>(path: string, opts: RequestOptions = {}): Promise<T | ApiResult<T>> {
    let cred = opts.anonymous ? null : await o.getCredentials();
    let res = await send(path, opts, cred);
    if (res.status === 401 && !opts.anonymous && o.onUnauthorized) {
      cred = await o.onUnauthorized();
      if (cred) res = await send(path, opts, cred);
    }
    const headers = responseHeaders(res);
    const etag = headers.get('etag');
    // 304 Not Modified: empty body, cache stays. Not an error.
    if (res.status === 304) {
      const result: ApiResult<T> = { data: null, status: 304, etag, headers };
      return opts.raw ? result : (null as T);
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
    if (opts.raw) return { data: json as T, status: res.status, etag, headers };
    return json as T;
  }

  return request as ApiClient;
}
