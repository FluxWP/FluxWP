// @fluxwp/core Platform for React Native: SecureStore tokens, MMKV storage, AppState visibility,
// in-app auth session (ASWebAuthenticationSession / Custom Tabs) for OAuth and Application Passwords.
import { AppState, Platform as RN } from 'react-native';
import * as Linking from 'expo-linking';
import * as WebBrowser from 'expo-web-browser';
import Constants from 'expo-constants';
import { OAuthError, setPlatform, type OAuthRedirect, type Platform, type Visibility } from '@fluxwp/core';
import { brand } from '@/brand';
import { secure } from './secure';
import { storage } from './storage';
import { usageHeaders } from '@/privacy';
import './crypto';

WebBrowser.maybeCompleteAuthSession();

export const APP_VERSION = Constants.expoConfig?.version ?? '0.0.0';
const OS = RN.OS === 'ios' ? 'ios' : RN.OS === 'android' ? 'android' : 'web';

let pending: { redirectUri: string; resolve: (u: URL) => void; reject: (e: Error) => void } | null = null;

/** Resolves a pending sign-in from a deep link (oauth/callback route), when the auth session did not. */
export function completeAuthRedirect(url: string): boolean {
  if (!pending || !url.startsWith(pending.redirectUri.split('?')[0]!)) return false;
  pending.resolve(new URL(url));
  return true;
}

async function prepareOAuthRedirect(path = 'oauth/callback'): Promise<OAuthRedirect> {
  pending?.reject(new OAuthError('cancelled'));
  const redirectUri = Linking.createURL(path, { scheme: brand.scheme });
  let resolve!: (u: URL) => void;
  let reject!: (e: Error) => void;
  const result = new Promise<URL>((res, rej) => ((resolve = res), (reject = rej)));
  result.catch(() => undefined);
  pending = { redirectUri, resolve, reject };
  return {
    redirectUri,
    wait: () => result.finally(() => (pending = null)),
    cancel: () => {
      WebBrowser.dismissAuthSession();
      reject(new OAuthError('cancelled'));
    },
  };
}

/** Core opens the authorize page right after prepareOAuthRedirect(): that page runs as an auth session. */
async function openExternal(url: string): Promise<void> {
  const auth = pending;
  if (auth && /action=flxapp_authorize|authorize-application\.php/.test(url)) {
    const r = await WebBrowser.openAuthSessionAsync(url, auth.redirectUri, { preferEphemeralSession: /prompt=login/.test(url) });
    if (r.type === 'success') auth.resolve(new URL(r.url));
    else auth.reject(new OAuthError('cancelled'));
    return;
  }
  if (/^https?:/i.test(url)) await WebBrowser.openBrowserAsync(url);
  else await Linking.openURL(url);
}

const visibility: Visibility = {
  visible: () => AppState.currentState === 'active',
  subscribe: (fn) => {
    const sub = AppState.addEventListener('change', (s) => fn(s === 'active'));
    return () => sub.remove();
  },
};

export const mobilePlatform: Platform = {
  kind: 'mobile',
  fetch: (...a) => fetch(...a),
  secure,
  storage,
  openExternal,
  prepareOAuthRedirect,
  visibility,
  // flx-app maps ios/android to the mobile shell; the web preview asks for it by name.
  headers: () => ({ 'X-FluxWP-Platform': OS === 'web' ? 'mobile' : OS, ...usageHeaders() }),
  // Local notifications arrive with push (a6); until then nothing to show outside the app.
  notify: async () => undefined,
};

setPlatform(mobilePlatform);
