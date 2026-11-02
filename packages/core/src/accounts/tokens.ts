import { platform } from '../platform';
import type { TokenResponse } from '../api/types';
import type { StoredTokens } from './types';

const key = (id: string) => `tokens:${id}`;

export async function loadTokens(id: string): Promise<StoredTokens | null> {
  const raw = await platform().secure.get(key(id));
  if (!raw) return null;
  try {
    return JSON.parse(raw) as StoredTokens;
  } catch {
    return null;
  }
}

export const saveTokens = (id: string, t: StoredTokens) => platform().secure.set(key(id), JSON.stringify(t));
export const clearTokens = (id: string) => platform().secure.remove(key(id));

export const fromTokenResponse = (r: TokenResponse, now = Date.now()): StoredTokens => ({
  access: r.access_token,
  refresh: r.refresh_token,
  expiresAt: r.expires_in ? now + r.expires_in * 1000 : undefined,
});
