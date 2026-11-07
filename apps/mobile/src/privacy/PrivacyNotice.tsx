import { Switch, View } from 'react-native';
import * as WebBrowser from 'expo-web-browser';
import { useTranslation } from 'react-i18next';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';
import { Button, Text } from '@/ui/primitives';
import { privacyUrl, termsUrl, usePrivacy } from './index';

/** Available before sign-in and in Settings; declining metrics never blocks connection. */
export function PrivacyNotice({ introduction = false }: { introduction?: boolean }) {
  const { t, i18n } = useTranslation();
  const { theme } = useUnistyles();
  const { usage, setUsage } = usePrivacy();
  return (
    <View style={styles.wrap} testID="privacy-notice">
      {introduction ? <Text variant="small" tone="muted">{t('mobile.privacy.connection')}</Text> : null}
      <View style={styles.row}>
        <Text variant="small" style={styles.flex}>{t('mobile.privacy.share')}</Text>
        <Switch value={usage} onValueChange={setUsage} trackColor={{ true: theme.colors.primary }} accessibilityLabel={t('mobile.privacy.share')} testID="usage-consent" />
      </View>
      <Text variant="caption" tone="muted">{t('mobile.privacy.usage')}</Text>
      <View style={styles.links}>
        <Button kind="ghost" label={t('mobile.privacy.policy')} onPress={() => void WebBrowser.openBrowserAsync(privacyUrl(i18n.language))} />
        <Button kind="ghost" label={t('mobile.privacy.terms')} onPress={() => void WebBrowser.openBrowserAsync(termsUrl(i18n.language))} />
      </View>
    </View>
  );
}

const styles = StyleSheet.create((theme) => ({
  wrap: { gap: theme.space[2] },
  row: { flexDirection: 'row', alignItems: 'center', gap: theme.space[3], minHeight: 44 },
  flex: { flex: 1 },
  links: { flexDirection: 'row', flexWrap: 'wrap', justifyContent: 'center' },
}));
