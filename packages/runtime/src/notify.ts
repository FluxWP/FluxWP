// Notification → account + route (plan §10). Payload: {v:1, site, user, target, params}. A push for a
// site/user that is not signed in on this device is ignored (never opens another account's data).
import type { Account } from '@fluxwp/core';
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

export function routePush(data: PushData, accounts: Account[], env: (a: Account) => ResolveEnv): { account: Account; route: Route } | null {
  const account = accountForPush(data, accounts);
  if (!account) return null;
  const params = Object.fromEntries(Object.entries(data.params ?? {}).map(([k, v]) => [k, String(v)]));
  const route = resolveTarget(data.target ?? 'more', env(account), params);
  return { account, route: route.kind === 'none' ? { kind: 'shell', name: 'more' } : route };
}
