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
  headers: () => ({ 'X-FluxWP-Platform': 'desktop' }),
  visibility: {
    visible: () => document.visibilityState !== 'hidden',
    subscribe(fn) {
      const on = () => fn(document.visibilityState !== 'hidden');
      document.addEventListener('visibilitychange', on);
      return () => document.removeEventListener('visibilitychange', on);
    },
  },
  openExternal: async (url) => void window.open(url, '_blank', 'noopener'),
  prepareOAuthRedirect: async () => {
    throw new Error('oauth_unsupported');
  },
  openWebView: async (url) => void window.open(url, '_blank', 'noopener'),
  openWeb: async (url) => void window.open(url, '_blank', 'noopener'),
  notify: async (n) => {
    if (typeof Notification === 'undefined') return;
    if (Notification.permission === 'default') await Notification.requestPermission();
    if (Notification.permission !== 'granted') return;
    const note = new Notification(n.title, { body: n.body });
    note.onclick = () => window.dispatchEvent(new CustomEvent('flux-notify', { detail: n.data ?? {} }));
  },
};
