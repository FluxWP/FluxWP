// Tokens → Keychain / Android Keystore. AFTER_FIRST_UNLOCK so a push can wake the app and read them.
import * as SecureStore from 'expo-secure-store';
import type { KeyValueStore } from '@fluxwp/core';

// SecureStore keys allow only [A-Za-z0-9._-]; core uses keys like "tokens:host#1".
const safeKey = (k: string) => 'flx_' + k.replace(/[^A-Za-z0-9._-]/g, (c) => '-' + c.charCodeAt(0).toString(16) + '-');
const opts = { keychainAccessible: SecureStore.AFTER_FIRST_UNLOCK };

export const secure: KeyValueStore = {
  get: (k) => SecureStore.getItemAsync(safeKey(k), opts),
  set: (k, v) => SecureStore.setItemAsync(safeKey(k), v, opts),
  remove: (k) => SecureStore.deleteItemAsync(safeKey(k), opts),
};
