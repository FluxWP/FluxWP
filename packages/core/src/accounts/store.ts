import { createStore } from 'zustand/vanilla';
import { platform } from '../platform';
import type { Account, StoredTokens } from './types';
import { clearTokens, saveTokens } from './tokens';

const STORAGE_KEY = 'accounts';

export interface AccountsState {
  ready: boolean;
  accounts: Account[];
  activeId: string | null;
  load(): Promise<void>;
  add(a: Account, tokens: StoredTokens | null): Promise<void>;
  remove(id: string): Promise<void>;
  setActive(id: string | null): Promise<void>;
  update(id: string, patch: Partial<Account>): Promise<void>;
}

export const accountsStore = createStore<AccountsState>((set, get) => {
  const persist = () => {
    const { accounts, activeId } = get();
    return platform().storage.set(STORAGE_KEY, JSON.stringify({ accounts, activeId }));
  };
  return {
    ready: false,
    accounts: [],
    activeId: null,
    async load() {
      const raw = await platform().storage.get(STORAGE_KEY);
      let data: { accounts?: Account[]; activeId?: string | null } = {};
      try {
        data = raw ? JSON.parse(raw) : {};
      } catch {
        /* bozuk kayıt: temiz başla */
      }
      const accounts = Array.isArray(data.accounts) ? data.accounts : [];
      const activeId = accounts.some((a) => a.id === data.activeId) ? (data.activeId ?? null) : (accounts[0]?.id ?? null);
      set({ ready: true, accounts, activeId });
    },
    async add(a, tokens) {
      if (tokens) await saveTokens(a.id, tokens);
      const rest = get().accounts.filter((x) => x.id !== a.id);
      set({ accounts: [...rest, { ...a, needsLogin: false }], activeId: a.id });
      await persist();
    },
    async remove(id) {
      await clearTokens(id);
      const accounts = get().accounts.filter((x) => x.id !== id);
      const activeId = get().activeId === id ? (accounts[0]?.id ?? null) : get().activeId;
      set({ accounts, activeId });
      await persist();
    },
    async setActive(id) {
      set({ activeId: id });
      await persist();
    },
    async update(id, patch) {
      set({ accounts: get().accounts.map((x) => (x.id === id ? { ...x, ...patch } : x)) });
      await persist();
    },
  };
});

export const activeAccount = (): Account | null => {
  const s = accountsStore.getState();
  return s.accounts.find((a) => a.id === s.activeId) ?? null;
};
