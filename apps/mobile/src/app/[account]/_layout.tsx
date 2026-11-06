// Account scope: API client, per-account persisted cache (own MMKV instance), site theme, drawer.
import { useEffect, useMemo } from 'react';
import { View } from 'react-native';
import { Redirect, router, useLocalSearchParams } from 'expo-router';
import { Drawer } from 'expo-router/drawer';
import { useTranslation } from 'react-i18next';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';
import { useStore } from 'zustand';
import { accountsStore } from '@fluxwp/core';
import { AccountScope, useAccount, useConfig, useDeviceRegistration, useRememberSiteMark, type AppInfo } from '@fluxwp/runtime';
import { brandLayer } from '@/brand';
import { APP_VERSION } from '@/platform';
import { accountStore } from '@/platform/storage';
import { usePushDevice } from '@/push';
import { useRenderer } from '@/render/renderer';
import { DrawerContent } from '@/shell/DrawerContent';
import { accountIdFromKey, accountKey } from '@/shell/routes';
import { SimpleHeader } from '@/shell/SimpleHeader';
import { applySiteTheme } from '@/theme/unistyles';
import { Button, Text } from '@/ui/primitives';

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
      <DeviceSync />
      <View style={styles.frame}>
        <AccountDrawer />
        <SessionGate />
      </View>
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

function DeviceSync() {
  const info = usePushDevice();
  useDeviceRegistration(info);
  useRememberSiteMark();
  return null;
}

/** Dead session: the sign-in button is on screen. The account stays; nothing has to be deleted. */
function SessionGate() {
  const account = useAccount();
  const { t } = useTranslation();
  if (!account.needsLogin || account.authKind === 'demo') return null;
  return (
    <View style={styles.gate}>
      <View style={styles.gateCard}>
        <Text variant="heading">{account.siteName}</Text>
        <Text tone="muted">{t('accounts.needsLogin')}</Text>
        <Button label={t('accounts.relogin')} onPress={() => router.push({ pathname: '/add-site', params: { url: account.siteUrl } })} full />
        <Button kind="ghost" label={t('mobile.switcher.manage')} onPress={() => router.push({ pathname: '/[account]/accounts', params: { account: accountKey(account.id) } })} full />
      </View>
    </View>
  );
}

function AccountDrawer() {
  const { theme } = useUnistyles();
  return (
    <Drawer
      drawerContent={(p) => <DrawerContent close={() => p.navigation.closeDrawer()} />}
      screenOptions={{ headerShown: false, drawerType: 'front', swipeEdgeWidth: 32, drawerStyle: { backgroundColor: theme.colors.sidebar, width: 304, borderTopRightRadius: 22, borderBottomRightRadius: 22, overflow: 'hidden' }, overlayColor: theme.colors.overlay }}
    >
      <Drawer.Screen name="(tabs)" />
      <Drawer.Screen name="accounts" options={{ headerShown: true, header: () => <SimpleHeader titleKey="mobile.switcher.title" />, swipeEnabled: false }} />
      <Drawer.Screen name="settings" options={{ headerShown: true, header: () => <SimpleHeader titleKey="mobile.settings.title" />, swipeEnabled: false }} />
    </Drawer>
  );
}

const styles = StyleSheet.create((theme) => ({
  frame: { flex: 1 },
  gate: { position: 'absolute', top: 0, right: 0, bottom: 0, left: 0, alignItems: 'center', justifyContent: 'center', padding: theme.space[6], backgroundColor: theme.colors.overlay },
  gateCard: { width: '100%', maxWidth: 420, gap: theme.space[3], padding: theme.space[5], borderRadius: theme.radius.lg, backgroundColor: theme.colors.surface, borderWidth: 1, borderColor: theme.colors.border },
}));
