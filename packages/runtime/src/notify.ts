// Notification → account + route (plan §10). Payload: {v:1, site, user, target, params}. A push for a
// site/user that is not signed in on this device is ignored (never opens another account's data).
import type { Account } from '@fluxwp/core';
import { parseTarget } from '@fluxwp/manifest';
import { resolveTarget, type ResolveEnv, type Route } from './resolve';

export interface PushData {
  v?: number;
  site?: string;
  user?: number;
  target?: string;
  params?: Record<string, string>;
}

const host = (u: string | undefined) => {
  try {
    return u ? new URL(u).host.toLowerCase() : null;
  } catch {
    return null;
  }
};

export function accountForPush(data: PushData, accounts: Account[]): Account | null {
  const h = host(data.site);
  if (!h) return null;
  return accounts.find((a) => host(a.siteUrl) === h && (data.user === undefined || a.userId === Number(data.user))) ?? null;
}

/** Expo (and other) payloads: data may be an object or a JSON string; `user` may be a string. */
export function pushDataOf(raw: unknown): PushData | null {
  let body = raw;
  if (typeof body === 'string') {
    try {
      body = JSON.parse(body);
    } catch {
      return null;
    }
  }
  if (!body || typeof body !== 'object') return null;
  const o = body as Record<string, unknown>;
  if (typeof o.site !== 'string' || !o.site) return null;
  const user = typeof o.user === 'number' ? o.user : typeof o.user === 'string' && o.user !== '' ? Number(o.user) : undefined;
  const params = o.params && typeof o.params === 'object'
    ? Object.fromEntries(Object.entries(o.params as Record<string, unknown>).filter(([, v]) => v !== undefined && v !== null).map(([k, v]) => [k, String(v)]))
    : undefined;
  return { v: typeof o.v === 'number' ? o.v : undefined, site: o.site, user: Number.isFinite(user) ? user : undefined, target: typeof o.target === 'string' ? o.target : undefined, params };
}

/** A screen/web/native target still opens when this device has not loaded that account's manifests yet. */
function unresolved(target: string | undefined, params: Record<string, string>): Route {
  const t = target ? parseTarget(target) : null;
  if (!t) return { kind: 'shell', name: 'more' };
  if (t.kind === 'screen') return { kind: 'manifest', module: t.module, screen: t.screen, params };
  if (t.kind === 'web') return { kind: 'web', path: t.path };
  if (t.kind === 'native') return { kind: 'native', component: t.component, params };
  if (t.kind === 'shell') return { kind: 'shell', name: t.name };
  if (t.kind === 'url') return { kind: 'url', url: t.url };
  return { kind: 'shell', name: 'more' };
}

export function routePush(data: PushData, accounts: Account[], env: (a: Account) => ResolveEnv): { account: Account; route: Route } | null {
  const account = accountForPush(data, accounts);
  if (!account) return null;
  const params = Object.fromEntries(Object.entries(data.params ?? {}).map(([k, v]) => [k, String(v)]));
  const route = resolveTarget(data.target ?? 'more', env(account), params);
  return { account, route: route.kind === 'none' ? unresolved(data.target, params) : route };
}
