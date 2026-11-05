// The web build is a development preview only (no Keychain): tokens stay in localStorage like the
// desktop panel's browser mode. Never ship the web build to users with real accounts.
import type { KeyValueStore } from '@fluxwp/core';

const has = typeof localStorage !== 'undefined';
export const secure: KeyValueStore = {
  get: async (k) => (has ? localStorage.getItem('flux.secure.' + k) : null),
  set: async (k, v) => void (has && localStorage.setItem('flux.secure.' + k, v)),
  remove: async (k) => void (has && localStorage.removeItem('flux.secure.' + k)),
};
