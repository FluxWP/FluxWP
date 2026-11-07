import { Trans, useTranslation } from 'react-i18next';
import * as WebBrowser from 'expo-web-browser';
import { StyleSheet } from 'react-native-unistyles';
import { Text } from '@/ui/primitives';
import { privacyUrl, termsUrl } from './index';

/** Policy links explain continued use; optional metrics consent remains in Settings. */
export function ConnectionTerms() {
  const { i18n } = useTranslation();
  return <Text variant="caption" tone="muted" style={styles.copy} testID="connection-terms">
    <Trans i18nKey="mobile.privacy.continueTerms" components={{
      terms: <Text variant="caption" tone="primary" accessibilityRole="link" onPress={() => void WebBrowser.openBrowserAsync(termsUrl(i18n.language))} />,
      privacy: <Text variant="caption" tone="primary" accessibilityRole="link" onPress={() => void WebBrowser.openBrowserAsync(privacyUrl(i18n.language))} />,
    }} />
  </Text>;
}

const styles = StyleSheet.create({ copy: { textAlign: 'center', lineHeight: 19 } });
