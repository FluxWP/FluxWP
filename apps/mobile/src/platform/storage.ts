// Plain (non-secret) storage on MMKV: device store for the account list and prefs, one instance per
// account for its query cache (deleted with the account, plan §5). Web preview: MMKV's localStorage backend.
import { createMMKV, deleteMMKV, type MMKV } from 'react-native-mmkv';
import type { KeyValueStore } from '@fluxwp/core';
import type { PersistStore } from '@fluxwp/runtime';

export const device: MMKV = createMMKV({ id: 'fluxwp' });

export const storage: KeyValueStore = {
  get: async (k) => device.getString(k) ?? null,
  set: async (k, v) => device.set(k, v),
  remove: async (k) => void device.remove(k),
};

const accountStoreId = (accountId: string) => 'acc-' + accountId.replace(/[^\w.-]/g, '_');
const stores = new Map<string, PersistStore>();
const mmkvs = new Map<string, MMKV>();

function accountMmkv(accountId: string): MMKV {
  let m = mmkvs.get(accountId);
  if (!m) mmkvs.set(accountId, (m = createMMKV({ id: accountStoreId(accountId) })));
  return m;
}

/** Bytes the account's persisted query cache takes on this device. */
export function accountCacheSize(accountId: string): number {
  return accountMmkv(accountId).size;
}

/** Empties the account's persisted cache (the account and its sign-in stay). */
export function clearAccountCacheStore(accountId: string): void {
  accountMmkv(accountId).clearAll();
}

export function accountStore(accountId: string): PersistStore {
  let s = stores.get(accountId);
  if (!s) {
    const m = accountMmkv(accountId);
    s = { getItem: (k) => m.getString(k) ?? null, setItem: (k, v) => m.set(k, v), removeItem: (k) => void m.remove(k) };
    stores.set(accountId, s);
  }
  return s;
}

export function deleteAccountStore(accountId: string): void {
  stores.delete(accountId);
  mmkvs.delete(accountId);
  deleteMMKV(accountStoreId(accountId));
}
