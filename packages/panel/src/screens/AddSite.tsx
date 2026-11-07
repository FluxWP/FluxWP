import { LegalLinks } from '../components/LegalLinks';
import { useEffect, useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { ArrowLeft, KeyRound, Loader2, LogIn, Shield } from 'lucide-react';
import {
  ApiError, accountsStore, brand, discoverSite, DiscoveryError, loginApiKey, loginAppPassword, loginDemo, loginOAuth, loginOtp, OAuthError, otpStart, platform, type SiteInfo,
} from '@fluxwp/core';
import type { Bootstrap } from '@fluxwp/manifest';
import { brandGate, loadBootstrap, siteMark, SiteNotAllowedError } from '@fluxwp/runtime';
import { Avatar } from '@fluxwp/ui';
import { BrandLogo } from '../components/BrandLogo';

function errorKey(e: unknown): string {
  if (e instanceof SiteNotAllowedError) return 'addSite.notAllowed';
  if (e instanceof DiscoveryError) return `errors.${e.reason}`;
  if (e instanceof OAuthError) return e.code === 'access_denied' ? 'errors.access_denied' : 'errors.oauth_failed';
  if (e instanceof ApiError) return e.status === 401 || e.status === 403 ? 'errors.bad_credentials' : 'errors.server';
  if (e instanceof Error && (e.message === 'oauth_unsupported' || e.message === 'cancelled')) return e.message === 'cancelled' ? 'addSite.cancelled' : 'errors.oauth_unsupported';
  return 'errors.unknown';
}

const sameHost = (siteUrl: string) => {
  let host = '';
  try { host = new URL(siteUrl).host; } catch { return false; }
  return accountsStore.getState().accounts.some((a) => { try { return new URL(a.siteUrl).host === host; } catch { return false; } });
};

export function AddSite({ onCancel, initialUrl }: { onCancel?: () => void; initialUrl?: string }) {
  const { t } = useTranslation();
  const [url, setUrl] = useState(initialUrl || brand.defaultSite || '');
  const [site, setSite] = useState<SiteInfo | null>(null);
  const [boot, setBoot] = useState<Bootstrap | null>(null);
  const [busy, setBusy] = useState<null | 'discover' | 'oauth' | 'key' | 'demo' | 'otp' | 'apppass'>(null);
  const [error, setError] = useState<string | null>(null);
  const [keyMode, setKeyMode] = useState(!brand.loginMethods.includes('oauth'));
  const [key, setKey] = useState('');
  const [otpLogin, setOtpLogin] = useState('');
  const [otpCode, setOtpCode] = useState('');
  const [otpSent, setOtpSent] = useState(false);

  const run = async (kind: NonNullable<typeof busy>, fn: () => Promise<unknown>) => {
    setBusy(kind);
    setError(null);
    try { await fn(); } catch (e) { setError(errorKey(e)); } finally { setBusy(null); }
  };

  const discover = (e?: FormEvent) => {
    e?.preventDefault();
    void run('discover', async () => {
      const found = brand.allowedSites?.length || brand.lockSite ? await brandGate(brand).findSite(url) : await discoverSite(url);
      const next = await loadBootstrap(found.url).catch(() => null);
      const mark = siteMark(found.icon, next?.brand?.icon, next?.site?.icon, next?.brand?.logo, next?.brand?.logo_dark);
      setSite(mark ? { ...found, icon: mark } : found);
      setBoot(next);
    });
  };

  useEffect(() => {
    if (initialUrl || (brand.lockSite && brand.defaultSite)) discover();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const methods = new Set(brand.loginMethods);
  if (!brand.lockSite) for (const m of boot?.auth.methods ?? []) methods.add(m);
  const flux = !!(site && (site.hasFluxApp || site.hasYtApp));
  if (site && !flux && site.appPasswordsUrl) methods.add('apppass');
  const allowOAuth = flux && methods.has('oauth');
  const allowKey = flux && methods.has('apikey');
  const allowOtp = flux && methods.has('otp');
  const allowPass = methods.has('apppass') && !!site?.appPasswordsUrl;
  const canBrowser = platform().kind !== 'web';

  return (
    <div className="auth">
      <div className="auth-card">
        {onCancel && (
          <button className="btn-ghost auth-back" onClick={onCancel}>
            <ArrowLeft size={16} /> {t('common.back')}
          </button>
        )}
        <BrandLogo className="auth-logo" />
        {!site ? (
          <>
            <h1>{brand.lockSite ? t('auth.welcome', { name: brand.name }) : t('auth.addSiteTitle')}</h1>
            <p className="muted">{brand.lockSite ? t('auth.connecting') : t('auth.addSiteHint')}</p>
            {!brand.lockSite && (
              <form onSubmit={discover} className="stack">
                <label className="field">
                  <span>{t('auth.siteUrl')}</span>
                  <input autoFocus value={url} onChange={(e) => setUrl(e.target.value)} placeholder={t('auth.siteUrlPlaceholder')} inputMode="url" autoCapitalize="off" spellCheck={false} />
                </label>
                <button className="btn-primary" disabled={!url.trim() || busy !== null}>
                  {busy === 'discover' ? <Loader2 className="spin" size={16} /> : null}
                  {t('auth.continue')}
                </button>
              </form>
            )}
            {brand.lockSite && busy === 'discover' && <Loader2 className="spin center" size={22} />}
            {brand.lockSite && error && <button className="btn-primary" onClick={() => discover()}>{t('common.retry')}</button>}
          </>
        ) : (
          <>
            <div className="site-chip">
              <Avatar src={site.icon} name={site.name} size={40} square />
              <div>
                <strong>{site.name}</strong>
                <span className="muted">{site.url.replace(/^https?:\/\//, '')}</span>
              </div>
              {!brand.lockSite && <button className="btn-link" onClick={() => (setSite(null), setBoot(null), setError(null), setOtpSent(false))}>{t('auth.change')}</button>}
            </div>
            {!flux && <p className="notice">{t('auth.noYtApp')}</p>}
            {allowOAuth && !keyMode && !otpSent && (
              <div className="stack">
                <button className="btn-primary" disabled={!canBrowser || busy !== null} onClick={() => run('oauth', () => loginOAuth(site, sameHost(site.url) ? { prompt: 'login' } : undefined))}>
                  {busy === 'oauth' ? <Loader2 className="spin" size={16} /> : <LogIn size={16} />}
                  {busy === 'oauth' ? t('auth.waitingBrowser') : t('auth.loginBrowser')}
                </button>
                {allowOAuth && !canBrowser && <p className="muted small">{t('errors.oauth_unsupported')}</p>}
                {allowKey && <button className="btn-ghost" onClick={() => setKeyMode(true)}><KeyRound size={16} /> {t('auth.useApiKey')}</button>}
                {allowOtp && <button type="button" className="btn-ghost" onClick={() => { setKeyMode(false); setOtpSent(true); }}><Shield size={16} /> {t('auth.otpLogin')}</button>}
              </div>
            )}
            {allowOtp && otpSent && (
              <form className="stack" onSubmit={(e) => { e.preventDefault(); void run('otp', async () => { if (!otpCode) { await otpStart(site.url, otpLogin); setOtpCode(''); return; } await loginOtp(site, otpLogin, otpCode); }); }}>
                <p className="muted small">{otpCode ? t('auth.codeSent') : t('auth.otpHint')}</p>
                <label className="field">
                  <span>{t('auth.otpLogin')}</span>
                  <input value={otpLogin} onChange={(e) => setOtpLogin(e.target.value)} autoComplete="username" />
                </label>
                <label className="field">
                  <span>{t('auth.otpCode')}</span>
                  <input value={otpCode} onChange={(e) => setOtpCode(e.target.value)} inputMode="numeric" autoComplete="one-time-code" />
                </label>
                <button className="btn-primary" disabled={!otpLogin.trim() || busy !== null}>
                  {busy === 'otp' ? <Loader2 className="spin" size={16} /> : null}
                  {otpCode.trim() ? t('auth.verify') : t('auth.sendCode')}
                </button>
                <button type="button" className="btn-ghost" onClick={() => setOtpSent(false)}>{t('common.back')}</button>
              </form>
            )}
            {flux && keyMode && (
              <form className="stack" onSubmit={(e) => (e.preventDefault(), void run('key', () => loginApiKey(site, key)))}>
                <label className="field">
                  <span>{t('auth.apiKey')}</span>
                  <input autoFocus value={key} onChange={(e) => setKey(e.target.value)} placeholder="flxak_…" spellCheck={false} autoCapitalize="off" />
                  <small className="muted">{t('auth.apiKeyHint')}</small>
                </label>
                <button className="btn-primary" disabled={!key.trim() || busy !== null}>{busy === 'key' ? <Loader2 className="spin" size={16} /> : null}{t('auth.login')}</button>
                {allowOAuth && <button type="button" className="btn-ghost" onClick={() => setKeyMode(false)}>{t('auth.loginBrowser')}</button>}
              </form>
            )}
            {allowPass && !keyMode && !otpSent && (
              <button className="btn-ghost" disabled={!canBrowser || busy !== null} onClick={() => run('apppass', () => loginAppPassword(site, brand.name))}>
                {busy === 'apppass' ? <Loader2 className="spin" size={16} /> : <KeyRound size={16} />} {t('auth.appPassword')}
              </button>
            )}
          </>
        )}
        {error && <p className="error" role="alert">{t(error)}</p>}
        {!brand.lockSite && !site && (
          <button className="btn-link demo-link" disabled={busy !== null} onClick={() => run('demo', loginDemo)}>
            {busy === 'demo' ? <Loader2 className="spin" size={14} /> : null}
            {t('auth.tryDemo')}
          </button>
        )}
      </div>
      <p className="auth-foot muted small">{t('auth.footer', { name: brand.name })}</p>
      <LegalLinks />
    </div>
  );
}
