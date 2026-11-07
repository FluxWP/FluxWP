import { useEffect, useState } from 'react';
import { BackHandler, Platform, Pressable, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';
import { Button, IconButton, Text } from '@/ui/primitives';

/** Dismissal changes only this notice; expired credentials remain invalid on the server. */
export function SessionExpiredNotice({ siteName, onRelogin, onManage }: { siteName: string; onRelogin: () => void; onManage: () => void }) {
  const { t } = useTranslation();
  const [open, setOpen] = useState(true);
  useEffect(() => {
    if (!open || Platform.OS !== 'android') return;
    const back = BackHandler.addEventListener('hardwareBackPress', () => { setOpen(false); return true; });
    return () => back.remove();
  }, [open]);
  const close = () => setOpen(false);
  const act = (next: () => void) => { close(); next(); };
  if (!open) return null;
  return <View style={styles.gate} testID="session-expired-notice">
    <Pressable accessible={false} style={styles.backdrop} onPress={close}/>
    <View style={styles.card} accessibilityViewIsModal>
      <View style={styles.heading}><Text variant="heading" style={styles.title}>{siteName}</Text><IconButton icon="x" label={t('common.close')} onPress={close}/></View>
      <Text tone="muted" accessibilityRole="alert">{t('accounts.needsLogin')}</Text>
      <Button label={t('accounts.relogin')} onPress={() => act(onRelogin)} full testID="session-relogin"/>
      <Button kind="ghost" label={t('mobile.switcher.manage')} onPress={() => act(onManage)} full testID="session-manage"/>
      <Button kind="ghost" label={t('common.close')} onPress={close} full testID="session-close"/>
    </View>
  </View>;
}
const styles = StyleSheet.create(theme => ({
  gate: { position: 'absolute', top: 0, right: 0, bottom: 0, left: 0, alignItems: 'center', justifyContent: 'center', padding: theme.space[6], backgroundColor: theme.colors.overlay },
  backdrop: { position: 'absolute', top: 0, right: 0, bottom: 0, left: 0 },
  card: { width: '100%', maxWidth: 420, gap: theme.space[3], padding: theme.space[5], borderRadius: theme.radius.lg, backgroundColor: theme.colors.surface, borderWidth: 1, borderColor: theme.colors.border },
  heading: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  title: { flex: 1 },
}));
