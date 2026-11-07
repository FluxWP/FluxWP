// Webview fallback (plan §9): one-time SSO address from flx-app, "FLXApp/" user agent (flx-app hides
// the admin bar, side menu and footer), navigation capture and the bridge whitelist from the runtime.
import { useCallback, useEffect, useLayoutEffect, useMemo, useRef, useState, type ComponentRef } from 'react';
import { Platform, View } from 'react-native';
import { WebView, type WebViewMessageEvent, type WebViewNavigation } from 'react-native-webview';
import { useNavigation } from 'expo-router';
import { useTranslation } from 'react-i18next';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';
import { DEMO_SITE, WebSessionManager, classifyNavigation, embedUserAgent, parseBridgeMessage, useAccount, useApi, useBootstrap, useConfig, useMe, useRenderer, useResolveEnv } from '@fluxwp/runtime';
import { brand } from '@/brand';
import { APP_VERSION } from '@/platform';
import { EmptyState, IconButton, Spinner, Text } from '@/ui/primitives';
import { demoAdminHtml } from './demo-admin';

export function WebScreen({ path, title, note }: { path: string; title?: string; note?: string }) {
  const { t } = useTranslation();
  const { theme, rt } = useUnistyles();
  const account = useAccount();
  const api = useApi();
  const me = useMe().data;
  const config = useConfig();
  const loginUrl = useBootstrap().data?.auth.login_url ?? null;
  const env = useResolveEnv();
  const renderer = useRenderer();
  const nav = useNavigation();
  const ref = useRef<ComponentRef<typeof WebView>>(null);
  const [src, setSrc] = useState<{ url: string } | { html: string } | null>(null);
  const [failed, setFailed] = useState(false);
  const [pageTitle, setPageTitle] = useState(title ?? '');
  const retried = useRef(false);
  const demo = account.siteUrl === DEMO_SITE;
  const session = useMemo(() => new WebSessionManager(account, api, (config.features?.web_session ?? true) && (me?.web_session ?? true)), [account, api, config.features?.web_session, me?.web_session]);
  const ua = embedUserAgent({ version: APP_VERSION, surface: 'mobile', brand: brand.slug, os: Platform.OS });

  const load = useCallback(async () => {
    setFailed(false);
    try {
      if (demo) return setSrc({ html: demoAdminHtml(path, rt.themeName === 'dark') });
      setSrc({ url: (await session.open(path)).url });
    } catch {
      setFailed(true);
    }
  }, [demo, path, session, rt.themeName]);

  useEffect(() => void Promise.resolve().then(load), [load]);
  useLayoutEffect(() => {
    nav.setOptions({ title: pageTitle || title || '', headerRight: () => <IconButton icon="refresh" label={t('mobile.web.reload')} onPress={() => ref.current?.reload()} /> });
  }, [nav, pageTitle, title, t]);

  const onRequest = (req: WebViewNavigation) => {
    if (demo) return true;
    const d = classifyNavigation(req.url, { siteUrl: account.siteUrl, env, allowHosts: config.webview?.allow_hosts, ssoUrl: 'url' in (src ?? {}) ? (src as { url: string }).url : null, loginUrl });
    switch (d.action) {
      case 'allow':
        return true;
      case 'native':
        renderer.nav.push(d.route);
        return false;
      case 'browser':
      case 'system':
        renderer.nav.openUrl(d.url);
        return false;
      case 'relogin':
        if (!retried.current) {
          retried.current = true;
          renderer.ui.toast(t('mobile.web.relogin'));
          void load();
        } else setFailed(true);
        return false;
      case 'block':
        return false;
    }
  };

  if (failed) return <EmptyState icon="alert" title={t('mobile.web.error')} action={{ label: t('mobile.web.reload'), onPress: () => void load() }} />;
  if (!src) return <Spinner label={t('mobile.web.loading')} />;
  return (
    <View style={styles.screen}>
      {note || demo ? <Text variant="caption" style={styles.note}>{demo ? t('mobile.web.demo') : note}</Text> : null}
      <WebView
        ref={ref}
        source={'html' in src ? { html: src.html, baseUrl: account.siteUrl } : { uri: src.url }}
        userAgent={ua}
        applicationNameForUserAgent={ua}
        sharedCookiesEnabled
        thirdPartyCookiesEnabled={false}
        cacheEnabled
        pullToRefreshEnabled
        allowsBackForwardNavigationGestures
        setSupportMultipleWindows={false}
        originWhitelist={['https://*', 'http://*', 'about:*']}
        onShouldStartLoadWithRequest={onRequest}
        onMessage={(e: WebViewMessageEvent) => {
          const m = parseBridgeMessage(e.nativeEvent.data);
          if (m?.type === 'title') setPageTitle(m.title);
          else if (m?.type === 'toast') renderer.ui.toast(m.text);
          else if (m?.type === 'close') renderer.nav.back();
        }}
        onLoadEnd={(e) => 'code' in e.nativeEvent && setFailed(true)}
        startInLoadingState
        renderLoading={() => <View style={styles.loading}><Spinner /></View>}
        style={{ backgroundColor: theme.colors.bg }}
      />
    </View>
  );
}

const styles = StyleSheet.create((theme) => ({
  screen: { flex: 1, backgroundColor: theme.colors.bg },
  note: { textAlign: 'center', paddingVertical: 6, backgroundColor: theme.colors.surface2 },
  loading: { ...StyleSheet.absoluteFillObject, backgroundColor: theme.colors.bg },
}));
