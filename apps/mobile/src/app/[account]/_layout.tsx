// Account scope: API client, per-account persisted cache (own MMKV instance), site theme, drawer.
import { useEffect, useMemo } from 'react';
import { Redirect, useLocalSearchParams } from 'expo-router';
import { Drawer } from 'expo-router/drawer';
import { useStore } from 'zustand';
import { useUnistyles } from 'react-native-unistyles';
import { accountsStore } from '@fluxwp/core';
import { AccountScope, useConfig, type AppInfo } from '@fluxwp/runtime';
import { brandLayer } from '@/brand';
import { APP_VERSION } from '@/platform';
import { accountStore } from '@/platform/storage';
import { useRenderer } from '@/render/renderer';
import { DrawerContent } from '@/shell/DrawerContent';
import { accountIdFromKey } from '@/shell/routes';
import { SimpleHeader } from '@/shell/SimpleHeader';
import { applySiteTheme } from '@/theme/unistyles';

export default function AccountLayout() {
  const { account: key } = useLocalSearchParams<{ account: string }>();
  const id = accountIdFromKey(String(key ?? ''));
  const account = useStore(accountsStore, (s) => s.accounts.find((a) => a.id === id) ?? null);
  const activeId = useStore(accountsStore, (s) => s.activeId);
  const renderer = useRenderer(id);
  const app = useMemo<AppInfo>(() => ({ version: APP_VERSION, brand: brandLayer }), []);

  useEffect(() => {
    if (account && activeId !== account.id) void accountsStore.getState().setActive(account.id);
  }, [account, activeId]);

  if (!account) return <Redirect href="/" />;
  return (
    <AccountScope account={account} renderer={renderer} app={app} store={accountStore(account.id)}>
      <SiteTheme />
      <AccountDrawer />
    </AccountScope>
  );
}

/** The account's site theme (config.theme) replaces the Unistyles themes while this account is open. */
function SiteTheme() {
  const theme = useConfig().theme;
  useEffect(() => {
    applySiteTheme(theme);
    return () => applySiteTheme(undefined);
  }, [theme]);
  return null;
}

function AccountDrawer() {
  const { theme } = useUnistyles();
  return (
    <Drawer
      drawerContent={(p) => <DrawerContent close={() => p.navigation.closeDrawer()} />}
      screenOptions={{ headerShown: false, drawerType: 'front', swipeEdgeWidth: 32, drawerStyle: { backgroundColor: theme.colors.sidebar, width: 304 }, overlayColor: theme.colors.overlay }}
    >
      <Drawer.Screen name="(tabs)" />
      <Drawer.Screen name="accounts" options={{ headerShown: true, header: () => <SimpleHeader titleKey="mobile.switcher.title" />, swipeEnabled: false }} />
      <Drawer.Screen name="settings" options={{ headerShown: true, header: () => <SimpleHeader titleKey="mobile.settings.title" />, swipeEnabled: false }} />
    </Drawer>
  );
}
