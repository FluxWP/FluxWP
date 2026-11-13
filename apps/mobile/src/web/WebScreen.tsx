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
import { EmptyState, IconButton, Skeleton, Text } from '@/ui/primitives';
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
  /** Shown under the error so a screenshot tells what broke (API error or WebKit/Chromium code). */
  const [reason, setReason] = useState('');
  const [pageTitle, setPageTitle] = useState(title ?? '');
  const retried = useRef(false);
  const demo = account.siteUrl === DEMO_SITE;
  const session = useMemo(() => new WebSessionManager(account, api, (config.features?.web_session ?? true) && (me?.web_session ?? true)), [account, api, config.features?.web_session, me?.web_session]);
  const ua = embedUserAgent({ version: APP_VERSION, surface: 'mobile', brand: brand.slug, os: Platform.OS });

  const load = useCallback(async () => {
    setFailed(false);
    setReason('');
    try {
      if (demo) return setSrc({ html: demoAdminHtml(path, rt.themeName === 'dark') });
      setSrc({ url: (await session.open(path)).url });
    } catch (e) {
      setReason(e instanceof Error ? e.message : String(e));
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
        } else {
          setReason(t('mobile.web.sessionLost'));
          setFailed(true);
        }
        return false;
      case 'block':
        return false;
    }
  };

  if (failed) return <EmptyState icon="alert" title={t('mobile.web.error')} body={reason || undefined} action={{ label: t('mobile.web.reload'), onPress: () => (retried.current = false, void load()) }} />;
  if (!src) return <Skeleton variant="page" rows={4} />;
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
        // A load we cancelled ourselves (native screen, browser, relogin) ends with "cancelled" /
        // "frame load interrupted": that is not a broken page.
        onError={(e) => {
          const { code, description } = e.nativeEvent;
          if (isCancelledLoad(code, description)) return;
          setReason(`${description || t('mobile.web.error')} (${code})`);
          setFailed(true);
        }}
        onHttpError={(e) => {
          if (e.nativeEvent.statusCode < 500 || !e.nativeEvent.url.startsWith(account.siteUrl.replace(/\/$/, ''))) return;
          setReason(`HTTP ${e.nativeEvent.statusCode}`);
          setFailed(true);
        }}
        startInLoadingState
        renderLoading={() => <View style={styles.loading}><Skeleton variant="page" rows={4} /></View>}
        style={{ backgroundColor: theme.colors.bg }}
      />
    </View>
  );
}

/** NSURLErrorCancelled (-999), WebKit "frame load interrupted" (102), Chromium ERR_ABORTED (-3). */
function isCancelledLoad(code: number, description = ''): boolean {
  return code === -999 || code === 102 || code === -3 || /cancel|interrupted|aborted/i.test(description);
}

const styles = StyleSheet.create((theme) => ({
  screen: { flex: 1, backgroundColor: theme.colors.bg },
  note: { textAlign: 'center', paddingVertical: 6, backgroundColor: theme.colors.surface2 },
  loading: { ...StyleSheet.absoluteFillObject, backgroundColor: theme.colors.bg },
}));
