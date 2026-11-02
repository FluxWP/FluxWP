import { useMemo } from 'react';
import { useStore } from 'zustand';
import { useQuery } from '@tanstack/react-query';
import { accountsStore, clientFor, layoutStore, mergeLayout, flxapp, type Account, type NavItem } from '@fluxwp/core';
import { builtinModules, implemented } from '../modules/registry';

export const useAccounts = () => useStore(accountsStore);

export function useActiveAccount(): Account | null {
  const { accounts, activeId } = useAccounts();
  return accounts.find((a) => a.id === activeId) ?? null;
}

export const useApi = (a: Account) => useMemo(() => flxapp(clientFor(a)), [a.id]);

export function useMe(a: Account) {
  const api = useApi(a);
  return useQuery({ queryKey: [a.id, 'me'], queryFn: api.me, staleTime: 60_000, refetchInterval: 5 * 60_000 });
}

export function useCounts(a: Account) {
  const api = useApi(a);
  return useQuery({ queryKey: [a.id, 'counts'], queryFn: api.counts, refetchInterval: 30_000, refetchIntervalInBackground: false });
}

export function useLayoutPrefs(a: Account) {
  return useStore(layoutStore, (s) => s.byAccount[a.id]);
}

export function useNav(a: Account): NavItem[] {
  const me = useMe(a).data;
  const counts = useCounts(a).data;
  const prefs = useLayoutPrefs(a);
  return useMemo(() => {
    if (!me) return [];
    const menu = me.menu.map((m) => ({ ...m, badge: counts?.[m.slug] ?? m.badge }));
    return mergeLayout({ menu, modules: { ...builtinModules, ...me.modules }, implemented, user: prefs });
  }, [me, counts, prefs]);
}
