import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';
import { platform } from '@fluxwp/core';
import { Button, Text } from '@/ui/primitives';

export function ConnectionHelp({ missingPlugin, unreachable, busy, onRetry, onClose }: { missingPlugin?: boolean; unreachable?: boolean; busy?: boolean; onRetry: () => void; onClose: () => void }) {
  const { t } = useTranslation();
  return <View style={styles.card} testID="connection-help">
    <Text variant="label" accessibilityRole="alert">{t(missingPlugin ? 'mobile.addSite.fluxAppTitle' : 'mobile.addSite.connectionTitle')}</Text>
    <Text tone="muted">{t(missingPlugin ? 'mobile.addSite.noFluxApp' : 'mobile.addSite.fluxAppRequired')}</Text>
    {unreachable ? <Text variant="small" tone="muted">{t('errors.unreachable')}</Text> : null}
    <Button kind="secondary" label={t('mobile.addSite.getFluxApp')} icon="external-link" onPress={() => void platform().openExternal('https://fluxwp.com/?r=appstore')} full testID="get-flux-app"/>
    <Button label={t('common.retry')} loading={busy} disabled={busy} onPress={onRetry} full testID="connection-retry"/>
    <Button kind="ghost" label={t('common.close')} onPress={onClose} full testID="connection-help-close"/>
  </View>;
}
const styles = StyleSheet.create(theme => ({
  card: { padding: theme.space[4], gap: theme.space[3], borderRadius: theme.radius.md, backgroundColor: theme.colors.surface2, borderWidth: 1, borderColor: theme.colors.border },
}));
