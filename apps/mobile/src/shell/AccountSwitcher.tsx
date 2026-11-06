// Quick site/account switcher (top bar avatar, drawer header). Switching = replace to the other
// account's route segment; each account keeps its own cache and theme.
import { Pressable, ScrollView, View } from 'react-native';
import { router } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useTranslation } from 'react-i18next';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';
import { create, useStore } from 'zustand';
import { accountsStore, type Account } from '@fluxwp/core';
import { gate } from '@/brand';
import { Icon } from '@/ui/Icon';
import { Avatar, Badge, Text } from '@/ui/primitives';
import { Sheet } from '@/ui/Sheet';
import { accountKey } from './routes';

const useSwitcher = create<{ open: boolean }>(() => ({ open: false }));
export const openSwitcher = () => useSwitcher.setState({ open: true });
const close = () => useSwitcher.setState({ open: false });

export function switchTo(a: Account) {
  void accountsStore.getState().setActive(a.id);
  router.replace({ pathname: '/[account]', params: { account: accountKey(a.id) } });
}

export function AccountSwitcherHost() {
  const open = useSwitcher((s) => s.open);
  const { accounts, activeId } = useStore(accountsStore);
  const { t } = useTranslation();
  const { theme } = useUnistyles();
  const insets = useSafeAreaInsets();
  return (
    <Sheet visible={open} onDismiss={close}>
      <View style={{ paddingBottom: insets.bottom }}>
          <Text variant="heading" style={styles.title}>{t('mobile.switcher.title')}</Text>
          <ScrollView style={styles.list}>
            {accounts.map((a) => (
              <Pressable key={a.id} accessibilityRole="button" accessibilityState={{ selected: a.id === activeId }} onPress={() => { close(); if (a.needsLogin) router.push({ pathname: '/add-site', params: { url: a.siteUrl } }); else switchTo(a); }} style={({ pressed }) => [styles.row, pressed && styles.pressed]}>
                <Avatar image={a.siteIcon || undefined} text={a.siteName} size={40} square />
                <View style={styles.flex}>
                  <Text numberOfLines={1} style={styles.name}>{a.siteName}</Text>
                  <Text variant="small" tone="muted" numberOfLines={1}>{a.userName} · {a.siteUrl.replace(/^https?:\/\//, '')}</Text>
                </View>
                {a.needsLogin ? <Badge text={t('mobile.switcher.relogin')} tone="warn" /> : a.authKind === 'apppass' ? <Badge text={t('mobile.switcher.limited')} tone="muted" /> : a.authKind === 'demo' ? <Badge text={t('shell.demo')} tone="info" /> : null}
                {a.id === activeId ? <Icon name="check" size={20} color={theme.colors.primary} /> : null}
              </Pressable>
            ))}
          </ScrollView>
          {!gate.locked ? (
            <Pressable accessibilityRole="button" onPress={() => (close(), router.push('/add-site'))} style={({ pressed }) => [styles.row, pressed && styles.pressed]}>
              <View style={styles.add}><Icon name="plus" size={20} color={theme.colors.primary} /></View>
              <Text tone="primary" style={styles.name}>{t('mobile.switcher.add')}</Text>
            </Pressable>
          ) : null}
          {activeId ? (
            <Pressable accessibilityRole="button" onPress={() => (close(), router.push({ pathname: '/[account]/accounts', params: { account: accountKey(activeId) } }))} style={({ pressed }) => [styles.row, pressed && styles.pressed]}>
              <View style={styles.add}><Icon name="settings" size={20} color={theme.colors.textMuted} /></View>
              <Text style={styles.name}>{t('mobile.switcher.manage')}</Text>
            </Pressable>
          ) : null}
      </View>
    </Sheet>
  );
}

const styles = StyleSheet.create((theme) => ({
  title: { paddingHorizontal: 12, marginBottom: 8 },
  list: { flexGrow: 0 },
  row: { flexDirection: 'row', alignItems: 'center', gap: 12, padding: 12, borderRadius: theme.radius.md, minHeight: 56 },
  pressed: { backgroundColor: theme.colors.surface2 },
  flex: { flex: 1 },
  name: { fontWeight: '600' },
  add: { width: 40, height: 40, borderRadius: theme.radius.md, backgroundColor: theme.colors.primarySoft, alignItems: 'center', justifyContent: 'center' },
}));
