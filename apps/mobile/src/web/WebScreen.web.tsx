// Web preview of the webview fallback. wp-admin refuses to be framed (X-Frame-Options), so real sites
// open in a new tab with the one-time SSO address; the demo account renders its sample page in an iframe.
import { useLayoutEffect, useMemo, useState } from 'react';
import { View } from 'react-native';
import { useNavigation } from 'expo-router';
import { useTranslation } from 'react-i18next';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';
import { DEMO_SITE, WebSessionManager, useAccount, useApi, useMe } from '@fluxwp/runtime';
import { EmptyState, Text } from '@/ui/primitives';
import { demoAdminHtml } from './demo-admin';

export function WebScreen({ path, title, note }: { path: string; title?: string; note?: string }) {
  const { t } = useTranslation();
  const { rt } = useUnistyles();
  const account = useAccount();
  const api = useApi();
  const me = useMe().data;
  const nav = useNavigation();
  const demo = account.siteUrl === DEMO_SITE;
  const session = useMemo(() => new WebSessionManager(account, api, me?.web_session ?? true), [account, api, me?.web_session]);
  const [busy, setBusy] = useState(false);
  useLayoutEffect(() => nav.setOptions({ title: title ?? '' }), [nav, title]);

  if (demo) {
    return (
      <View style={styles.screen}>
        <Text variant="caption" style={styles.note}>{t('mobile.web.demo')}</Text>
        <iframe title={path} srcDoc={demoAdminHtml(path, rt.themeName === 'dark')} style={{ border: 0, flex: 1, width: '100%', height: '100%' }} />
      </View>
    );
  }
  return (
    <EmptyState
      icon="external"
      title={t('web.title')}
      body={note ?? t('web.body')}
      action={{
        label: busy ? t('mobile.web.loading') : t('web.open'),
        onPress: () => {
          setBusy(true);
          void session.open(path).then(({ url }) => window.open(url, '_blank', 'noopener')).finally(() => setBusy(false));
        },
      }}
    />
  );
}

const styles = StyleSheet.create((theme) => ({
  screen: { flex: 1, backgroundColor: theme.colors.bg },
  note: { textAlign: 'center', paddingVertical: 6, backgroundColor: theme.colors.surface2 },
}));
