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

async function refresh(a: Account): Promise<Credentials | null> {
  const running = inflight.get(a.id);
  if (running) return running;
  const p = (async () => {
    const t = await loadTokens(a.id);
    if (!t?.refresh) return null;
    try {
      const next = fromTokenResponse(await refreshTokens(a.siteUrl, t.refresh));
      await saveTokens(a.id, next);
      return { token: next.access };
    } catch {
      await accountsStore.getState().update(a.id, { needsLogin: true });
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
      if (!t) return null;
      if (t.expiresAt && t.expiresAt - SKEW < Date.now() && t.refresh) return refresh(a);
      return { token: t.access };
    },
    onUnauthorized: a.authKind === 'oauth' ? () => refresh(a) : undefined,
  });
  clients.set(a.id, c);
  return c;
}

export const dropClient = (id: string) => clients.delete(id);
