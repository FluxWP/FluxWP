import { useMemo } from 'react';
import { useStore } from 'zustand';
import { accountsStore, clientFor, layoutStore, mergeLayout, flxapp, type Account, type NavItem } from '@fluxwp/core';
import { useMe, useSync } from '@fluxwp/runtime';
import { builtinModules, implemented } from '../modules/registry';

export const useAccounts = () => useStore(accountsStore);

export function useActiveAccount(): Account | null {
  const { accounts, activeId } = useAccounts();
  return accounts.find((a) => a.id === activeId) ?? null;
}

export const useApi = (a: Account) => useMemo(() => flxapp(clientFor(a)), [a.id]);

export function useLayoutPrefs(a: Account) {
  return useStore(layoutStore, (s) => s.byAccount[a.id]);
}

/** Menu from the shared /me and /sync queries. Does not start its own requests. */
export function useNav(a: Account): NavItem[] {
  const me = useMe().data;
  const counts = useSync(!!me).data?.counts;
  const prefs = useLayoutPrefs(a);
  return useMemo(() => {
    if (!me) return [];
    const menu = me.menu.map((m) => ({ ...m, badge: counts?.[m.slug] ?? m.badge }));
    return mergeLayout({ menu, modules: { ...builtinModules, ...me.modules }, implemented, user: prefs });
  }, [me, counts, prefs]);
}
