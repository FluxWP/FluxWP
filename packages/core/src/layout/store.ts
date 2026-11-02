// Kullanıcının menü düzeni (hesap başına). 0.1.0: yerelde; sunucu senkronu flxapp/v1/prefs gelince.
import { createStore } from 'zustand/vanilla';
import { platform } from '../platform';
import type { LayoutPrefs } from './merge';

export interface LayoutState {
  byAccount: Record<string, LayoutPrefs>;
  load(accountId: string): Promise<void>;
  set(accountId: string, patch: Partial<LayoutPrefs>): Promise<void>;
  reset(accountId: string): Promise<void>;
}

const key = (id: string) => `layout:${id}`;

export const layoutStore = createStore<LayoutState>((set, get) => ({
  byAccount: {},
  async load(id) {
    const raw = await platform().storage.get(key(id));
    let prefs: LayoutPrefs = {};
    try {
      prefs = raw ? JSON.parse(raw) : {};
    } catch {
      /* yok say */
    }
    set({ byAccount: { ...get().byAccount, [id]: prefs } });
  },
  async set(id, patch) {
    const prefs = { ...get().byAccount[id], ...patch };
    set({ byAccount: { ...get().byAccount, [id]: prefs } });
    await platform().storage.set(key(id), JSON.stringify(prefs));
  },
  async reset(id) {
    set({ byAccount: { ...get().byAccount, [id]: {} } });
    await platform().storage.remove(key(id));
  },
}));
