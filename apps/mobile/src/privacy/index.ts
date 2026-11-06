import { create } from 'zustand';
import { device } from '@/platform/storage';

const KEY = 'privacy.usage.v1';

/** Explicit device choice; a site's administrator cannot opt a mobile user in. */
export const usePrivacy = create<{ usage: boolean; setUsage(value: boolean): void }>((set) => ({
  usage: device.getBoolean(KEY) === true,
  setUsage: (usage) => {
    device.set(KEY, usage);
    set({ usage });
  },
}));

export function usageHeaders(): Record<string, string> {
  return { 'X-FluxWP-Usage': usePrivacy.getState().usage ? '1' : '0' };
}

export const APP_PRIVACY_URL = 'https://github.com/FluxWP/FluxWP/blob/main/docs/fluxapp/APP-PRIVACY.md';
export const APP_TERMS_URL = 'https://fluxwp.com/terms/';
