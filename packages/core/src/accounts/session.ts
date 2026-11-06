// Hesap başına API istemcisi: süresi dolmak üzere olan belirteci yeniler, 401'de bir kez dener.
import { createClient, type ApiClient, type Credentials } from '../api/client';
import { refreshTokens } from '../auth/oauth';
import { demoFetch } from '../demo/fetch';
import type { Account } from './types';
import { fromTokenResponse, loadTokens, saveTokens } from './tokens';
import { accountsStore } from './store';

const SKEW = 60_000;
const clients = new Map<string, ApiClient>();
const inflight = new Map<string, Promise<Credentials | null>>();

async function markNeedsLogin(id: string): Promise<void> {
  const cur = accountsStore.getState().accounts.find((x) => x.id === id);
  if (cur && !cur.needsLogin) await accountsStore.getState().update(id, { needsLogin: true });
}

async function refresh(a: Account): Promise<Credentials | null> {
  const running = inflight.get(a.id);
  if (running) return running;
  const p = (async () => {
    const t = await loadTokens(a.id);
    if (!t?.refresh || (a.authKind !== 'oauth' && a.authKind !== 'otp')) {
      await markNeedsLogin(a.id);
      return null;
    }
    try {
      const next = fromTokenResponse(await refreshTokens(a.siteUrl, t.refresh));
      await saveTokens(a.id, next);
      return { token: next.access };
    } catch {
      await markNeedsLogin(a.id);
      return null;
    }
  })().finally(() => inflight.delete(a.id));
  inflight.set(a.id, p);
  return p;
}

export function clientFor(a: Account): ApiClient {
  const hit = clients.get(a.id);
  if (hit) return hit;
  const c = createClient({
    siteUrl: a.siteUrl,
    fetchImpl: a.authKind === 'demo' ? demoFetch : undefined,
    getCredentials: async () => {
      if (a.authKind === 'demo') return { token: 'demo' };
      const t = await loadTokens(a.id);
      if (!t) {
        await markNeedsLogin(a.id);
        return null;
      }
      if (t.expiresAt && t.expiresAt - SKEW < Date.now()) return refresh(a);
      return { token: t.access, scheme: a.authKind === 'apppass' ? 'basic' : 'bearer' };
    },
    onUnauthorized: async () => {
      if (a.authKind === 'demo') return null;
      if (a.authKind === 'oauth' || a.authKind === 'otp') {
        const next = await refresh(a);
        if (next) return next;
      }
      await markNeedsLogin(a.id);
      return null;
    },
  });
  clients.set(a.id, c);
  return c;
}

export const dropClient = (id: string) => clients.delete(id);
