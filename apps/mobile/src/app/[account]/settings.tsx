// Device settings: theme mode, language, cache; about/support.
import { ScrollView, View } from 'react-native';
import { useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';
import { useRenderer } from '@fluxwp/runtime';
import { brand } from '@/brand';
import { setLanguage } from '@/i18n';
import { APP_VERSION } from '@/platform';
import { usePrefs, type ThemePref } from '@/theme/prefs';
import { applyThemeMode } from '@/theme/unistyles';
import { overlays } from '@/ui/overlays';
import { Button, Card, Segmented, Text } from '@/ui/primitives';

export default function Settings() {
  const { t } = useTranslation();
  const { theme, locale, setTheme, setLocale } = usePrefs();
  const client = useQueryClient();
  const renderer = useRenderer();
  return (
    <ScrollView contentContainerStyle={styles.wrap}>
      <Card style={styles.card}>
        <Text variant="label">{t('mobile.settings.theme')}</Text>
        <Segmented<ThemePref>
          options={[
            { value: 'system', label: t('settings.themes.system') },
            { value: 'light', label: t('settings.themes.light') },
            { value: 'dark', label: t('settings.themes.dark') },
          ]}
          value={theme}
          onChange={(m) => (setTheme(m), applyThemeMode(m))}
        />
      </Card>
      <Card style={styles.card}>
        <Text variant="label">{t('mobile.settings.language')}</Text>
        <Segmented<string>
          options={[{ value: '', label: t('mobile.settings.languageDevice') }, ...brand.locales.map((l) => ({ value: l, label: t(`languages.${l}`) }))]}
          value={locale ?? ''}
          onChange={(l) => (setLocale(l || null), setLanguage(l || null))}
        />
      </Card>
      <Button kind="secondary" icon="refresh" label={t('mobile.settings.cache')} onPress={() => (client.clear(), overlays.toast(t('mobile.settings.cacheCleared')))} full />
      <View style={styles.about}>
        <Text variant="caption">{brand.name} · {t('mobile.settings.version', { version: APP_VERSION })}</Text>
        {brand.supportUrl ? <Button kind="ghost" label={t('mobile.settings.support')} onPress={() => renderer.nav.openUrl(brand.supportUrl!)} /> : null}
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create((theme) => ({
  wrap: { padding: theme.space[4], gap: theme.space[3] },
  card: { gap: theme.space[2] },
  about: { alignItems: 'center', gap: 4, marginTop: theme.space[4] },
}));
