// Accounts: switch, add, sign in again. Edit mode: clear an account's cache, remove it (revokes the token, ends the web session, deletes the
// account's cache store).
import { useState } from 'react';
import { ScrollView, View } from 'react-native';
import { router } from 'expo-router';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';
import { useStore } from 'zustand';
import { accountsStore, dropClient, loadTokens, revokeToken, type Account } from '@fluxwp/core';
import { dropAccountCache, queryClientFor, signOutRemote } from '@fluxwp/runtime';
import { gate } from '@/brand';
import { accountCacheSize, clearAccountCacheStore, deleteAccountStore } from '@/platform/storage';
import { switchTo } from '@/shell/AccountSwitcher';
import { overlays } from '@/ui/overlays';
import { Avatar, Badge, Button, Card, Text } from '@/ui/primitives';

async function removeAccount(a: Account) {
  const tokens = await loadTokens(a.id);
  const deviceDeleted = await signOutRemote(a);
  if (!deviceDeleted && tokens?.refresh && (a.authKind === 'oauth' || a.authKind === 'otp')) await revokeToken(a.siteUrl, tokens.refresh);
  await accountsStore.getState().remove(a.id);
  dropClient(a.id);
  dropAccountCache(a.id);
  deleteAccountStore(a.id);
}

function formatBytes(n: number): string {
  if (n < 1024) return `${n} B`;
  if (n < 1024 * 1024) return `${(n / 1024).toFixed(n < 10240 ? 1 : 0)} KB`;
  return `${(n / 1024 / 1024).toFixed(1)} MB`;
}

export default function Accounts() {
  const { t } = useTranslation();
  const { accounts, activeId } = useStore(accountsStore);
  const [editing, setEditing] = useState(false);
  const [, refresh] = useState(0);
  const clearCache = (a: Account) => {
    queryClientFor(a.id).clear();
    clearAccountCacheStore(a.id);
    refresh((n) => n + 1);
    overlays.toast(t('mobile.switcher.cacheCleared', { site: a.siteName }));
  };
  return (
    <ScrollView contentContainerStyle={styles.wrap}>
      {accounts.length ? (
        <View style={styles.bar}>
          <Button kind="secondary" label={editing ? t('mobile.switcher.done') : t('mobile.switcher.edit')} onPress={() => setEditing((e) => !e)} />
        </View>
      ) : null}
      {accounts.map((a) => (
        <Card key={a.id} style={styles.card} onPress={editing || a.id === activeId ? undefined : () => switchTo(a)}>
          <Avatar image={a.siteIcon || undefined} text={a.siteName} size={44} square />
          <View style={styles.flex}>
            <Text variant="heading" numberOfLines={1}>{a.siteName}</Text>
            <Text variant="small" tone="muted" numberOfLines={1}>{a.userName} · {a.siteUrl.replace(/^https?:\/\//, '')}</Text>
            <View style={styles.badges}>
              {a.id === activeId ? <Badge text={t('mobile.switcher.current')} tone="accent" /> : null}
              <Badge text={t(`accounts.kind.${a.authKind === 'otp' ? 'oauth' : a.authKind === 'apppass' ? 'apikey' : a.authKind}`)} tone="muted" />
              {a.needsLogin ? <Badge text={t('accounts.needsLogin')} tone="warn" /> : null}
            </View>
            <Text variant="small" tone="muted">{t('mobile.switcher.cache', { size: formatBytes(accountCacheSize(a.id)) })}</Text>
            <Button kind={a.needsLogin ? 'primary' : 'secondary'} label={t('accounts.relogin')} onPress={() => router.push({ pathname: '/add-site', params: { url: a.siteUrl } })} full />
            {editing ? (
              <View style={styles.editRow}>
                <Button kind="secondary" icon="refresh" label={t('mobile.switcher.clearCache')} onPress={() => clearCache(a)} full />
                <Button
                  kind="secondary"
                  icon="trash"
                  label={t('mobile.switcher.remove')}
                  onPress={async () => {
                    if (!(await overlays.confirm({ title: t('mobile.switcher.removeConfirm', { site: a.siteName }), ok: t('mobile.switcher.remove'), tone: 'danger' }))) return;
                    await removeAccount(a);
                    if (!accountsStore.getState().accounts.length) router.replace('/add-site');
                    else if (a.id === activeId) router.replace('/');
                  }}
                  full
                />
              </View>
            ) : null}
          </View>
        </Card>
      ))}
      {!gate.locked ? <Button kind="secondary" icon="plus" label={t('mobile.switcher.add')} onPress={() => router.push('/add-site')} full /> : null}
    </ScrollView>
  );
}

const styles = StyleSheet.create((theme) => ({
  wrap: { padding: theme.space[4], gap: theme.space[3] },
  bar: { alignItems: 'flex-end' },
  editRow: { gap: 8, marginTop: 6 },
  card: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  flex: { flex: 1, gap: 2 },
  badges: { flexDirection: 'row', gap: 6, marginTop: 4, flexWrap: 'wrap' },
}));
