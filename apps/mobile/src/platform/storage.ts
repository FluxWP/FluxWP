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

export function accountStore(accountId: string): PersistStore {
  let s = stores.get(accountId);
  if (!s) {
    const m = createMMKV({ id: accountStoreId(accountId) });
    s = { getItem: (k) => m.getString(k) ?? null, setItem: (k, v) => m.set(k, v), removeItem: (k) => void m.remove(k) };
    stores.set(accountId, s);
  }
  return s;
}

export function deleteAccountStore(accountId: string): void {
  stores.delete(accountId);
  deleteMMKV(accountStoreId(accountId));
}
