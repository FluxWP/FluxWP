import { siteBasePath } from '../text';

export type AuthKind = 'oauth' | 'otp' | 'apikey' | 'apppass' | 'demo';

export interface Account {
  /** `host#userId` or `host/path#userId` (subdirectory). Two installs on one host stay separate. */
  id: string;
  siteUrl: string;
  siteName: string;
  siteIcon: string;
  userId: number;
  userName: string;
  userAvatar: string;
  authKind: AuthKind;
  addedAt: number;
  /** Yenileme başarısız: kullanıcı yeniden giriş yapmalı. */
  needsLogin?: boolean;
}

export interface StoredTokens {
  access: string;
  refresh?: string;
  /** ms epoch; API anahtarında yok. */
  expiresAt?: number;
}

export interface ParsedAccountId {
  host: string;
  /** `""` at the root, otherwise `/blog`. */
  basePath: string;
  userId: number;
}

/** `host#userId` at the root, `host/path#userId` for a subdirectory install. */
export function accountId(siteUrl: string, userId: number): string {
  const host = new URL(siteUrl).host;
  const path = siteBasePath(siteUrl);
  return `${host}${path}#${userId}`;
}

/**
 * Reads an account id. Old root ids (`host#userId`) still parse.
 * Anything else returns null; callers must not throw on a stored id.
 */
export function parseAccountId(id: string): ParsedAccountId | null {
  const hash = id.indexOf('#');
  if (hash <= 0 || hash !== id.lastIndexOf('#')) return null;
  const userRaw = id.slice(hash + 1);
  if (!/^[0-9]+$/.test(userRaw)) return null;
  const left = id.slice(0, hash);
  const slash = left.indexOf('/');
  const host = slash < 0 ? left : left.slice(0, slash);
  const basePath = slash < 0 ? '' : left.slice(slash);
  if (!host || /[\s#]/.test(host) || basePath === '/' || basePath.endsWith('/') || basePath.includes('//')) return null;
  return { host, basePath, userId: Number(userRaw) };
}

/** One path segment. Root stays `host@userId`; a subdirectory encodes the slash. */
export function accountRouteKey(id: string): string {
  const parsed = parseAccountId(id);
  if (!parsed) return encodeURIComponent(id);
  if (!parsed.basePath) return `${parsed.host}@${parsed.userId}`;
  return `${encodeURIComponent(parsed.host + parsed.basePath)}@${parsed.userId}`;
}

/** Inverse of `accountRouteKey`. A bad key is returned unchanged. */
export function accountIdFromRouteKey(key: string): string {
  try {
    const at = key.lastIndexOf('@');
    if (at <= 0) return decodeURIComponent(key);
    const user = key.slice(at + 1);
    if (!/^[0-9]+$/.test(user)) return decodeURIComponent(key);
    return `${decodeURIComponent(key.slice(0, at))}#${user}`;
  } catch {
    return key;
  }
}
