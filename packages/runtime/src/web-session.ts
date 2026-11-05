// Webview fallback decisions (plan §9): SSO address, which account owns the WP cookie of a host,
// navigation classification and the bridge message whitelist. Mobile (react-native-webview) and
// desktop (Tauri embedWebView) apply these decisions; neither decides on its own.
import { adminPath, flxapp, type Account, type ApiClient } from '@fluxwp/core';
import { safeAdminPath } from '@fluxwp/manifest';
import { routeForAdminPath, type ResolveEnv, type Route } from './resolve';

/** `FLXApp/<version> (mobile; <brand>; ios)`: flx-app hides the admin chrome for this UA (embed.php). */
export function embedUserAgent(p: { version: string; surface: 'mobile' | 'desktop'; brand: string; os: string }): string {
  return `FLXApp/${p.version} (${p.surface}; ${p.brand}; ${p.os})`;
}

const owners = new Map<string, string>();

export class WebSessionManager {
  constructor(private account: Account, private api: ApiClient, private webSession: boolean) {}

  private get host(): string {
    return new URL(this.account.siteUrl).host;
  }

  /** Full admin URL for a wp-admin relative path. */
  adminUrl(path: string): string {
    return this.account.siteUrl.replace(/\/+$/, '') + '/wp-admin/' + path.replace(/^\/+/, '');
  }

  /**
   * Address to load. With web_session on, every open gets a one-time SSO URL (60 s), which also
   * re-points the host's WP cookie to this account when another account on the same host used it last.
   */
  async open(path: string): Promise<{ url: string; sso: boolean }> {
    const p = safeAdminPath(path) ?? 'index.php';
    if (!this.webSession || this.account.authKind === 'apppass') return { url: this.adminUrl(p), sso: false };
    const { url } = await flxapp(this.api).session(p);
    owners.set(this.host, this.account.id);
    return { url, sso: true };
  }

  /** Another account signed in to this host in the webview after us: the next open must SSO again. */
  ownsCookie(): boolean {
    return owners.get(this.host) === this.account.id;
  }

  async end(): Promise<void> {
    if (owners.get(this.host) === this.account.id) owners.delete(this.host);
    try {
      await this.api('flxapp/v1/session/end', { method: 'POST' });
    } catch {
      /* older flx-app: nothing to end server-side */
    }
  }
}

export type NavDecision =
  | { action: 'allow' }
  | { action: 'native'; route: Route }
  | { action: 'browser'; url: string }
  | { action: 'system'; url: string }
  | { action: 'relogin' }
  | { action: 'block'; reason: 'logout' | 'scheme' };

const SYSTEM = /^(tel|mailto|sms|whatsapp|geo|maps):/i;

const SSO_ACTIONS = new Set(['flxapp_sso', 'flxapp_session']);

export interface NavOptions {
  siteUrl: string;
  env: ResolveEnv;
  allowHosts?: string[];
  /** The one-time SSO address just loaded (always allowed). */
  ssoUrl?: string | null;
  /** bootstrap `auth.login_url`: custom login page (Flux Speed /fluxadmin/). */
  loginUrl?: string | null;
}

/** Login page hit outside an SSO hand-off → the WP cookie session dropped. */
function isLoginPage(u: URL, loginUrl?: string | null): boolean {
  if (/\/wp-login\.php$/.test(u.pathname)) return true;
  if (!loginUrl) return false;
  try {
    const l = new URL(loginUrl);
    return l.origin === u.origin && u.pathname.replace(/\/+$/, '') === l.pathname.replace(/\/+$/, '');
  } catch {
    return false;
  }
}

/** What to do when the webview is about to load `url` (onShouldStartLoadWithRequest / on_navigation). */
export function classifyNavigation(url: string, opts: NavOptions): NavDecision {
  if (SYSTEM.test(url) || /^https:\/\/(wa\.me|api\.whatsapp\.com)\//i.test(url)) return { action: 'system', url };
  if (/^(about:blank|data:|blob:)/i.test(url)) return { action: 'allow' };
  let u: URL;
  try {
    u = new URL(url);
  } catch {
    return { action: 'block', reason: 'scheme' };
  }
  if (u.protocol !== 'https:' && u.protocol !== 'http:') return { action: 'block', reason: 'scheme' };
  const site = new URL(opts.siteUrl);
  const sameSite = u.origin === site.origin || (opts.allowHosts ?? []).includes(u.host);
  if (!sameSite) return { action: 'browser', url };
  if (opts.ssoUrl && url === opts.ssoUrl) return { action: 'allow' };
  if (isLoginPage(u, opts.loginUrl)) {
    const action = u.searchParams.get('action') ?? '';
    if (action === 'logout') return { action: 'block', reason: 'logout' };
    if (SSO_ACTIONS.has(action)) return { action: 'allow' };
    return { action: 'relogin' };
  }
  if (u.pathname.includes('/wp-admin/')) {
    const route = routeForAdminPath(adminPath(u.pathname + u.search), opts.env);
    if (route) return { action: 'native', route };
  }
  return { action: 'allow' };
}

export type BridgeMessage =
  | { type: 'title'; title: string }
  | { type: 'route'; path: string }
  | { type: 'toast'; text: string }
  | { type: 'counts'; counts: Record<string, number> }
  | { type: 'close' };

/** Whitelisted postMessage payloads from flx-app's embed bridge; anything else is ignored. */
export function parseBridgeMessage(raw: unknown): BridgeMessage | null {
  let m: unknown = raw;
  if (typeof raw === 'string') {
    if (raw.length > 10_000) return null;
    try {
      m = JSON.parse(raw);
    } catch {
      return null;
    }
  }
  if (!m || typeof m !== 'object') return null;
  const o = m as Record<string, unknown>;
  const s = (x: unknown, max = 300) => (typeof x === 'string' ? x.slice(0, max) : null);
  switch (o.type) {
    case 'title':
      return s(o.title) !== null ? { type: 'title', title: s(o.title)! } : null;
    case 'route':
      return s(o.path, 500) !== null ? { type: 'route', path: s(o.path, 500)! } : null;
    case 'toast':
      return s(o.text) !== null ? { type: 'toast', text: s(o.text)! } : null;
    case 'counts': {
      if (!o.counts || typeof o.counts !== 'object') return null;
      const counts: Record<string, number> = {};
      for (const [k, x] of Object.entries(o.counts as Record<string, unknown>).slice(0, 100)) if (typeof x === 'number' && Number.isFinite(x)) counts[k.slice(0, 64)] = x;
      return { type: 'counts', counts };
    }
    case 'close':
      return { type: 'close' };
    default:
      return null;
  }
}
