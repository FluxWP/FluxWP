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

const policyBase = (language: string) => language.startsWith('tr') ? 'https://fluxwp.com/tr/' : 'https://fluxwp.com/';
export const privacyUrl = (language: string) => policyBase(language) + 'privacy/';
export const termsUrl = (language: string) => policyBase(language) + 'terms/';
