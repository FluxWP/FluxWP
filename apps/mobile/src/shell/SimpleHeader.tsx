import { View } from 'react-native';
import { router } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';
import { GlassButton } from '@/ui/Glass';
import { Text } from '@/ui/primitives';

/** Header for account-level pages outside the tabs (accounts, settings). */
export function SimpleHeader({ titleKey }: { titleKey: string }) {
  const { t } = useTranslation();
  const insets = useSafeAreaInsets();
  return (
    <View style={[styles.bar, { paddingTop: insets.top + 4 }]}>
      <View style={styles.row}>
        <GlassButton icon="back" label={t('mobile.shell.back')} onPress={() => (router.canGoBack() ? router.back() : router.replace('/'))} />
        <Text variant="heading" accessibilityRole="header">{t(titleKey)}</Text>
      </View>
    </View>
  );
}

const styles = StyleSheet.create((theme) => ({
  bar: { backgroundColor: theme.colors.bg, paddingBottom: 6 },
  row: { minHeight: 52, flexDirection: 'row', alignItems: 'center', paddingHorizontal: 14, gap: 12 },
}));
