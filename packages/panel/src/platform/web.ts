// Tarayıcı platformu. Geliştirme ve (ileride) sitede servis edilen panel içindir.
// Not: tarayıcıda belirteçler localStorage'da durur; sitede servis edilince çerez + nonce kullanılacak.
import type { KeyValueStore, Platform } from '@fluxwp/core';

const ls = (prefix: string): KeyValueStore => ({
  get: async (k) => {
    try {
      return localStorage.getItem(prefix + k);
    } catch {
      return null;
    }
  },
  set: async (k, v) => {
    try {
      localStorage.setItem(prefix + k, v);
    } catch {
      /* gizli pencere */
    }
  },
  remove: async (k) => {
    try {
      localStorage.removeItem(prefix + k);
    } catch {
      /* yok say */
    }
  },
});

export const webPlatform: Platform = {
  kind: 'web',
  fetch: (...a) => fetch(...a),
  secure: ls('flux.s.'),
  storage: ls('flux.'),
  openExternal: async (url) => void window.open(url, '_blank', 'noopener'),
  prepareOAuthRedirect: async () => {
    throw new Error('oauth_unsupported');
  },
  openWebView: async (url) => void window.open(url, '_blank', 'noopener'),
};
