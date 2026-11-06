// Device settings: theme mode, language, cache; about/support.
import { ScrollView, Switch, View } from 'react-native';
import { useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';
import { useRenderer, useSavePrefs, useUserPrefs } from '@fluxwp/runtime';
import { brand } from '@/brand';
import { setLanguage } from '@/i18n';
import { APP_VERSION } from '@/platform';
import { usePrefs, type ThemePref } from '@/theme/prefs';
import { applyThemeMode } from '@/theme/unistyles';
import { overlays } from '@/ui/overlays';
import { Button, Card, Segmented, Text } from '@/ui/primitives';
import { PrivacyNotice } from '@/privacy/PrivacyNotice';

export default function Settings() {
  const { t } = useTranslation();
  const { theme, locale, setTheme, setLocale } = usePrefs();
  const client = useQueryClient();
  const renderer = useRenderer();
  const prefs = useUserPrefs().data;
  const save = useSavePrefs();
  const { theme: ui } = useUnistyles();
  const events = prefs?.events ?? [];
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
      {events.length ? (
        <Card style={styles.card}>
          <Text variant="label">{t('mobile.settings.notifications')}</Text>
          {events.map((e) => (
            <View key={e.id} style={styles.row}>
              <Text style={styles.flex}>{e.title}</Text>
              <Switch
                value={prefs?.push?.[e.id] ?? e.on ?? e.default ?? true}
                onValueChange={(on) => save.mutate({ push: { ...(prefs?.push ?? {}), [e.id]: on } })}
                trackColor={{ true: ui.colors.primary }}
                accessibilityLabel={e.title}
              />
            </View>
          ))}
        </Card>
      ) : null}
      <Button kind="secondary" icon="refresh" label={t('mobile.settings.cache')} onPress={() => (client.clear(), overlays.toast(t('mobile.settings.cacheCleared')))} full />
      <Card style={styles.card}><PrivacyNotice /></Card>
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
  row: { flexDirection: 'row', alignItems: 'center', minHeight: 44, gap: 8 },
  flex: { flex: 1 },
  about: { alignItems: 'center', gap: 4, marginTop: theme.space[4] },
}));
