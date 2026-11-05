// Add a site: address → discovery (+ white-label gate) → sign-in method: OAuth in the in-app browser
// (default), code (OTP), API key, or Application Passwords when the site has no Flux App (limited mode).
import { useEffect, useState } from 'react';
import { KeyboardAvoidingView, Platform, Pressable, ScrollView, View } from 'react-native';
import { router } from 'expo-router';
import { SvgXml } from 'react-native-svg';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useTranslation } from 'react-i18next';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';
import { ApiError, AppPasswordError, DiscoveryError, OAuthError, loginApiKey, loginAppPassword, loginDemo, loginOAuth, loginOtp, otpStart, type Account, type SiteInfo } from '@fluxwp/core';
import type { Bootstrap } from '@fluxwp/manifest';
import { SiteNotAllowedError, loadBootstrap } from '@fluxwp/runtime';
import { LOGO, LOGO_DARK, brand, gate } from '@/brand';
import { accountKey } from '@/shell/routes';
import { Avatar, Button, Field, IconButton, Text } from '@/ui/primitives';

type Step = 'url' | 'method' | 'otp' | 'apikey';
type Busy = null | 'discover' | 'oauth' | 'oauth2' | 'otp' | 'verify' | 'key' | 'apppass' | 'demo';

function hostOf(input: string): string {
  try {
    const s = /^https?:\/\//i.test(input) ? input : `https://${input}`;
    return new URL(s).host.toLowerCase();
  } catch {
    return '';
  }
}

function errorKey(e: unknown): string {
  if (e instanceof SiteNotAllowedError) return 'mobile.addSite.notAllowed';
  if (e instanceof DiscoveryError) return `errors.${e.reason}`;
  if (e instanceof OAuthError) return e.code === 'cancelled' ? 'mobile.addSite.cancelled' : e.code === 'access_denied' ? 'errors.access_denied' : 'errors.oauth_failed';
  if (e instanceof AppPasswordError) return e.code === 'insecure' ? 'mobile.addSite.insecure' : e.code === 'cancelled' ? 'mobile.addSite.cancelled' : 'errors.oauth_failed';
  if (e instanceof ApiError) return e.status === 401 || e.status === 403 ? 'errors.bad_credentials' : 'errors.server';
  return 'errors.unknown';
}

export default function AddSite() {
  const { t } = useTranslation();
  const { theme } = useUnistyles();
  const insets = useSafeAreaInsets();
  const [url, setUrl] = useState(brand.defaultSite ?? '');
  const [site, setSite] = useState<SiteInfo | null>(null);
  const [step, setStep] = useState<Step>('url');
  const [busy, setBusy] = useState<Busy>(null);
  const [error, setError] = useState<string | null>(null);
  const [login, setLogin] = useState('');
  const [code, setCode] = useState('');
  const [sentVia, setSentVia] = useState<string | null>(null);
  const [key, setKey] = useState('');
  const [boot, setBoot] = useState<Bootstrap | null>(null);
  // Brand methods ∩ what the site offers (bootstrap, flx-app 0.4.0+; older sites: brand methods).
  const methods = brand.loginMethods.filter((m) => !boot || boot.auth.methods.includes(m));
  const clientOff = !!boot && !boot.auth.clients?.mobile && boot.apps?.generic === false;
  const restricted = brand.allowedSites.length > 0;
  const picks = brand.allowedSites.filter((s) => !s.trim().startsWith('*.'));
  const wildcards = brand.allowedSites.filter((s) => s.trim().startsWith('*.')).map((s) => s.trim().slice(2));
  const currentHost = hostOf(url);

  const run = async (kind: NonNullable<Busy>, fn: () => Promise<unknown>) => {
    setBusy(kind);
    setError(null);
    try {
      await fn();
    } catch (e) {
      setError(errorKey(e));
    } finally {
      setBusy(null);
    }
  };
  const done = (a: Account) => router.replace({ pathname: '/[account]', params: { account: accountKey(a.id) } });
  const discover = () =>
    run('discover', async () => {
      const s = await gate.findSite(url);
      setBoot(s.hasFluxApp ? await loadBootstrap(s.url).catch(() => null) : null);
      setSite(s);
      setStep('method');
    });

  // Single-site brand: connect to its site right away.
  useEffect(() => {
    if (gate.locked && brand.defaultSite) void Promise.resolve().then(discover);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  return (
    <KeyboardAvoidingView style={styles.screen} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <ScrollView contentContainerStyle={[styles.wrap, { paddingTop: insets.top + 24, paddingBottom: insets.bottom + 24 }]} keyboardShouldPersistTaps="handled">
        {router.canGoBack() ? <View style={styles.close}><IconButton icon="x" label={t('common.close')} onPress={() => router.back()} /></View> : null}
        <SvgXml xml={theme.mode === 'dark' ? LOGO_DARK : LOGO} width={170} height={50} accessibilityLabel={brand.name} />
        {step === 'url' ? (
          <View style={styles.block}>
            <Text variant="title">{gate.locked ? t('auth.welcome', { name: brand.name }) : t('mobile.addSite.title')}</Text>
            <Text tone="muted">{restricted ? t('mobile.addSite.allowedHint') : t('mobile.addSite.hint')}</Text>
            {!gate.locked ? (
              <>
                {picks.length ? (
                  <View style={styles.sites}>
                    {picks.map((host) => {
                      const on = currentHost === host.toLowerCase();
                      return (
                        <Pressable key={host} testID={`allowed-site-${host}`} accessibilityRole="button" accessibilityState={{ selected: on }} onPress={() => setUrl(host.includes('://') ? host : `https://${host}`)} style={[styles.sitePick, on && styles.sitePickOn]}>
                          <Text variant="small" tone={on ? 'primary' : undefined}>{host}</Text>
                        </Pressable>
                      );
                    })}
                  </View>
                ) : null}
                {wildcards.map((host) => <Text key={host} variant="small" tone="muted">{t('mobile.addSite.wildcard', { host })}</Text>)}
                <Field label={t('auth.siteUrl')} value={url} onChangeText={setUrl} placeholder={t('auth.siteUrlPlaceholder')} autoCapitalize="none" autoCorrect={false} keyboardType="url" returnKeyType="go" onSubmitEditing={() => url.trim() && void discover()} testID="site-url" />
                <Button label={t('mobile.addSite.continue')} onPress={() => void discover()} loading={busy === 'discover'} disabled={!url.trim() || !!busy} full testID="continue" />
              </>
            ) : (
              <Button label={t('common.retry')} onPress={() => void discover()} loading={busy === 'discover'} full />
            )}
          </View>
        ) : site ? (
          <View style={styles.block}>
            <View style={styles.siteChip}>
              <Avatar image={site.icon || undefined} text={site.name} size={44} square />
              <View style={styles.flex}>
                <Text variant="heading" numberOfLines={1}>{site.name}</Text>
                <Text variant="small" tone="muted" numberOfLines={1}>{site.url.replace(/^https?:\/\//, '')}</Text>
              </View>
              {!gate.locked ? <Pressable onPress={() => (setStep('url'), setSite(null), setError(null))} hitSlop={8}><Text tone="primary">{t('auth.change')}</Text></Pressable> : null}
            </View>

            {step === 'method' && site.hasFluxApp && clientOff ? <Text tone="danger">{t('mobile.addSite.appNotAllowed')}</Text> : null}
            {step === 'method' && site.hasFluxApp && !clientOff ? (
              <>
                {methods.includes('oauth') ? (
                  <>
                    <Button label={busy === 'oauth' ? t('auth.waitingBrowser') : t('auth.loginBrowser')} icon="lock" loading={busy === 'oauth'} disabled={!!busy} onPress={() => void run('oauth', async () => done(await loginOAuth(site, { scope: 'flx offline_access' })))} full testID="oauth" />
                    <Text variant="small" tone="muted" style={styles.center}>{t('mobile.addSite.browserHint')}</Text>
                  </>
                ) : null}
                {methods.includes('otp') ? <Button kind="secondary" label={t('mobile.addSite.otp')} icon="phone" disabled={!!busy} onPress={() => setStep('otp')} full /> : null}
                {methods.includes('apikey') ? <Button kind="ghost" label={t('auth.useApiKey')} icon="key" disabled={!!busy} onPress={() => setStep('apikey')} full /> : null}
                {methods.includes('oauth') ? <Button kind="ghost" label={t('mobile.addSite.secondAccount')} disabled={!!busy} loading={busy === 'oauth2'} onPress={() => void run('oauth2', async () => done(await loginOAuth(site, { scope: 'flx offline_access', prompt: 'login' })))} full /> : null}
              </>
            ) : null}

            {step === 'method' && !site.hasFluxApp ? (
              <>
                <Text tone="muted">{t('mobile.addSite.noFluxApp')}</Text>
                <Button label={t('mobile.addSite.appPassword')} icon="key" loading={busy === 'apppass'} disabled={!!busy || !site.appPasswordsUrl} onPress={() => void run('apppass', async () => done(await loginAppPassword(site, brand.name)))} full />
              </>
            ) : null}

            {step === 'otp' ? (
              <>
                <Text variant="heading">{t('mobile.otp.title')}</Text>
                <Field label={t('mobile.otp.login')} value={login} onChangeText={setLogin} autoCapitalize="none" keyboardType="email-address" />
                {sentVia ? (
                  <>
                    <Text variant="small" tone="muted">{t('mobile.otp.sent', { via: sentVia })}</Text>
                    <Field label={t('mobile.otp.code')} value={code} onChangeText={setCode} keyboardType="number-pad" maxLength={6} textContentType="oneTimeCode" autoComplete="one-time-code" />
                    <Button label={t('mobile.otp.verify')} loading={busy === 'verify'} disabled={code.trim().length !== 6 || !!busy} onPress={() => void run('verify', async () => done(await loginOtp(site, login, code)))} full />
                    <Button kind="ghost" label={t('mobile.otp.resend')} disabled={!!busy} onPress={() => void run('otp', async () => setSentVia((await otpStart(site.url, login)).via_label))} full />
                  </>
                ) : (
                  <Button label={t('mobile.otp.send')} loading={busy === 'otp'} disabled={!login.trim() || !!busy} onPress={() => void run('otp', async () => setSentVia((await otpStart(site.url, login)).via_label))} full />
                )}
                <Button kind="ghost" label={t('common.back')} onPress={() => setStep('method')} full />
              </>
            ) : null}

            {step === 'apikey' ? (
              <>
                <Text variant="heading">{t('mobile.apikey.title')}</Text>
                <Field label={t('auth.apiKey')} value={key} onChangeText={setKey} placeholder="flxak_…" autoCapitalize="none" autoCorrect={false} secureTextEntry help={t('auth.apiKeyHint')} />
                <Button label={t('auth.login')} loading={busy === 'key'} disabled={!key.trim() || !!busy} onPress={() => void run('key', async () => done(await loginApiKey(site, key)))} full />
                <Button kind="ghost" label={t('common.back')} onPress={() => setStep('method')} full />
              </>
            ) : null}
          </View>
        ) : null}

        {error ? <Text tone="danger" accessibilityRole="alert" style={styles.center}>{t(error)}</Text> : null}
        {!restricted && !gate.locked && step === 'url' ? (
          <Button kind="ghost" label={t('mobile.addSite.demo')} loading={busy === 'demo'} disabled={!!busy} onPress={() => void run('demo', async () => done(await loginDemo()))} testID="demo" />
        ) : null}
        <Text variant="caption" style={styles.center}>{t('auth.footer', { name: brand.name })}</Text>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create((theme) => ({
  screen: { flex: 1, backgroundColor: theme.colors.bg },
  wrap: { flexGrow: 1, paddingHorizontal: theme.space[6], gap: theme.space[5], maxWidth: 520, width: '100%', alignSelf: 'center' },
  close: { position: 'absolute', right: 8, top: 8 },
  block: { gap: theme.space[3] },
  sites: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  sitePick: { paddingHorizontal: 12, minHeight: 44, justifyContent: 'center', borderRadius: theme.radius.full, borderWidth: 1, borderColor: theme.colors.border, backgroundColor: theme.colors.surface },
  sitePickOn: { borderColor: theme.colors.primary, backgroundColor: theme.colors.primarySoft },
  siteChip: { flexDirection: 'row', alignItems: 'center', gap: 12, padding: 12, borderRadius: theme.radius.lg, backgroundColor: theme.colors.surface, borderWidth: 1, borderColor: theme.colors.border },
  flex: { flex: 1 },
  center: { textAlign: 'center' },
}));
