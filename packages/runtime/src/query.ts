// One QueryClient per account (caches never mix, plan §5) with a persister port: the platform gives a
// key-value store per account (MMKV instance on mobile, Tauri store / localStorage on desktop).
import { QueryClient } from '@tanstack/react-query';
import { createAsyncStoragePersister } from '@tanstack/query-async-storage-persister';
import { ApiError } from '@fluxwp/core';

export interface PersistStore {
  getItem(key: string): string | null | Promise<string | null>;
  setItem(key: string, value: string): void | Promise<void>;
  removeItem(key: string): void | Promise<void>;
}

const DAY = 86_400_000;
export const CACHE_MAX_AGE = 7 * DAY;
/** Bump when cached data shapes change; old caches are dropped on restore. */
export const CACHE_BUSTER = 'rt-1';

export function createQueryClient(): QueryClient {
  return new QueryClient({
    defaultOptions: {
      queries: {
        staleTime: 30_000,
        gcTime: CACHE_MAX_AGE,
        // Cache first: render persisted data, refresh in the background, read offline (plan §1.4).
        networkMode: 'offlineFirst',
        retry: (n, e) => !(e instanceof ApiError && e.status >= 400 && e.status < 500) && n < 2,
      },
      mutations: { networkMode: 'online' },
    },
  });
}

export function createPersister(store: PersistStore) {
  return createAsyncStoragePersister({
    key: 'query-cache',
    throttleTime: 1500,
    storage: {
      getItem: async (k) => store.getItem(k),
      setItem: async (k, v) => store.setItem(k, v),
      removeItem: async (k) => store.removeItem(k),
    },
  });
}

/** Query keys. Each account has its own client, so keys do not repeat the account id. */
export const qk = {
  me: () => ['me'] as const,
  config: () => ['config'] as const,
  manifest: () => ['manifest'] as const,
  sync: () => ['sync'] as const,
  dashboard: () => ['dashboard'] as const,
  /** Source data; `invalidate: ["contacts"]` targets every key that starts with [src, module, "contacts"]. */
  source: (module: string, source: string, path: string, query: unknown) => ['src', module, source, path, query] as const,
  sourceRoot: (module: string, source: string) => ['src', module, source] as const,
};
