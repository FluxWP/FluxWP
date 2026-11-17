// Per-account query cache store. IndexedDB first: localStorage is about 5 MB for the whole app in WebKit,
// so a large cache (many modules, several sites) failed to save and every page loaded from the network again.
// localStorage stays as the fallback (private mode, old WebViews) and is migrated once.
import type { PersistStore } from '@fluxwp/runtime';

const DB = 'flux-cache';
const STORE = 'kv';

let dbp: Promise<IDBDatabase | null> | null = null;

function db(): Promise<IDBDatabase | null> {
  if (dbp) return dbp;
  dbp = new Promise((resolve) => {
    try {
      if (typeof indexedDB === 'undefined') return resolve(null);
      const req = indexedDB.open(DB, 1);
      req.onupgradeneeded = () => req.result.createObjectStore(STORE);
      req.onsuccess = () => resolve(req.result);
      req.onerror = () => resolve(null);
      req.onblocked = () => resolve(null);
    } catch {
      resolve(null);
    }
  });
  return dbp;
}

function run<T>(mode: IDBTransactionMode, fn: (s: IDBObjectStore) => IDBRequest<T> | void): Promise<T | undefined> {
  return db().then((d) => new Promise<T | undefined>((resolve, reject) => {
    if (!d) return reject(new Error('no idb'));
    const tx = d.transaction(STORE, mode);
    const req = fn(tx.objectStore(STORE));
    tx.oncomplete = () => resolve(req ? req.result : undefined);
    tx.onerror = () => reject(tx.error);
    tx.onabort = () => reject(tx.error);
  }));
}

const ls = {
  get: (k: string) => { try { return localStorage.getItem(k); } catch { return null; } },
  set: (k: string, v: string) => { try { localStorage.setItem(k, v); } catch { /* full or private mode */ } },
  remove: (k: string) => { try { localStorage.removeItem(k); } catch { /* private mode */ } },
};

export function accountCacheStore(accountId: string): PersistStore {
  const prefix = `flux.acc.${accountId}.`;
  return {
    getItem: async (k) => {
      try {
        const v = await run<string>('readonly', (s) => s.get(prefix + k));
        if (typeof v === 'string') return v;
        // First run after the switch: move the old localStorage copy over.
        const old = ls.get(prefix + k);
        if (old !== null) {
          await run('readwrite', (s) => void s.put(old, prefix + k));
          ls.remove(prefix + k);
        }
        return old;
      } catch {
        return ls.get(prefix + k);
      }
    },
    setItem: async (k, v) => {
      try {
        await run('readwrite', (s) => void s.put(v, prefix + k));
      } catch {
        ls.set(prefix + k, v);
      }
    },
    removeItem: async (k) => {
      ls.remove(prefix + k);
      try {
        await run('readwrite', (s) => void s.delete(prefix + k));
      } catch { /* nothing stored */ }
    },
  };
}

/** Is there a saved cache for this account (decides between the full loading screen and a quick one)? */
export async function hasAccountCache(accountId: string): Promise<boolean> {
  const v = await accountCacheStore(accountId).getItem('query-cache');
  return typeof v === 'string' && v.length > 2;
}
