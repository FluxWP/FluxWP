// Accounts: switch, add, sign in again, remove (revokes the token, ends the web session, deletes the
// account's cache store).
import { ScrollView, View } from 'react-native';
import { router } from 'expo-router';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';
import { useStore } from 'zustand';
import { accountsStore, dropClient, loadTokens, revokeToken, type Account } from '@fluxwp/core';
import { dropAccountCache, signOutRemote } from '@fluxwp/runtime';
import { gate } from '@/brand';
import { deleteAccountStore } from '@/platform/storage';
import { switchTo } from '@/shell/AccountSwitcher';
import { overlays } from '@/ui/overlays';
import { Avatar, Badge, Button, Card, IconButton, Text } from '@/ui/primitives';

async function removeAccount(a: Account) {
  const tokens = await loadTokens(a.id);
  const deviceDeleted = await signOutRemote(a);
  if (!deviceDeleted && tokens?.refresh && (a.authKind === 'oauth' || a.authKind === 'otp')) await revokeToken(a.siteUrl, tokens.refresh);
  await accountsStore.getState().remove(a.id);
  dropClient(a.id);
  dropAccountCache(a.id);
  deleteAccountStore(a.id);
}

export default function Accounts() {
  const { t } = useTranslation();
  const { accounts, activeId } = useStore(accountsStore);
  return (
    <ScrollView contentContainerStyle={styles.wrap}>
      {accounts.map((a) => (
        <Card key={a.id} style={styles.card} onPress={a.id === activeId ? undefined : () => switchTo(a)}>
          <Avatar image={a.siteIcon || undefined} text={a.siteName} size={44} square />
          <View style={styles.flex}>
            <Text variant="heading" numberOfLines={1}>{a.siteName}</Text>
            <Text variant="small" tone="muted" numberOfLines={1}>{a.userName} · {a.siteUrl.replace(/^https?:\/\//, '')}</Text>
            <View style={styles.badges}>
              {a.id === activeId ? <Badge text={t('mobile.switcher.current')} tone="accent" /> : null}
              <Badge text={t(`accounts.kind.${a.authKind === 'otp' ? 'oauth' : a.authKind === 'apppass' ? 'apikey' : a.authKind}`)} tone="muted" />
              {a.needsLogin ? <Badge text={t('accounts.needsLogin')} tone="warn" /> : null}
            </View>
          </View>
          <IconButton
            icon="trash"
            label={t('mobile.switcher.remove')}
            onPress={async () => {
              if (!(await overlays.confirm({ title: t('mobile.switcher.removeConfirm', { site: a.siteName }), ok: t('mobile.switcher.remove'), tone: 'danger' }))) return;
              await removeAccount(a);
              if (!accountsStore.getState().accounts.length) router.replace('/add-site');
              else if (a.id === activeId) router.replace('/');
            }}
          />
        </Card>
      ))}
      {!gate.locked ? <Button kind="secondary" icon="plus" label={t('mobile.switcher.add')} onPress={() => router.push('/add-site')} full /> : null}
    </ScrollView>
  );
}

const styles = StyleSheet.create((theme) => ({
  wrap: { padding: theme.space[4], gap: theme.space[3] },
  card: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  flex: { flex: 1, gap: 2 },
  badges: { flexDirection: 'row', gap: 6, marginTop: 4, flexWrap: 'wrap' },
}));
