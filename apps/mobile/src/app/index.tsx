import { Redirect } from 'expo-router';
import { useStore } from 'zustand';
import { accountsStore } from '@fluxwp/core';
import { accountKey } from '@/shell/routes';

/** No account → add a site; otherwise open the active account. */
export default function Index() {
  const activeId = useStore(accountsStore, (s) => s.activeId);
  if (!activeId) return <Redirect href="/add-site" />;
  return <Redirect href={{ pathname: '/[account]', params: { account: accountKey(activeId) } }} />;
}
