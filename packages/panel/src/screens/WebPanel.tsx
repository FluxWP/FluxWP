// Embedded wp-admin. SSO, navigation capture and the embed bridge come from the runtime;
// Tauri draws the child webview. The browser panel opens a new tab (no embed).
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ExternalLink, Loader2, MonitorSmartphone } from 'lucide-react';
import { platform, trimEnd, trimStart, type EmbeddedView } from '@fluxwp/core';
import { useOverlay } from '@fluxwp/ui';
import { WebSessionManager, classifyNavigation, parseBridgeMessage, useAccount, useApi, useBootstrap, useConfig, useRenderer, useResolveEnv } from '@fluxwp/runtime';

export function WebPanel({ path, title }: { path: string; title: string }) {
  const account = useAccount();
  const api = useApi();
  const config = useConfig();
  const env = useResolveEnv();
  const boot = useBootstrap().data;
  const { nav, ui, t } = useRenderer();
  const canEmbed = !!platform().embedWebView;
  const mgr = useMemo(() => new WebSessionManager(account, api, config.features?.web_session !== false), [account, api, config.features?.web_session]);
  const sso = useRef<string | null>(null);

  const resolveUrl = useCallback(async () => {
    const opened = await mgr.open(path);
    sso.current = opened.sso ? opened.url : null;
    return opened.url;
  }, [mgr, path]);

  const onNavigate = useCallback((url: string) => {
    const decision = classifyNavigation(url, { siteUrl: account.siteUrl, env, allowHosts: config.webview?.allow_hosts, ssoUrl: sso.current, loginUrl: boot?.auth.login_url });
    if (decision.action === 'native') {
      nav.push(decision.route);
      return false;
    }
    if (decision.action === 'browser' || decision.action === 'system') {
      void platform().openExternal(decision.url);
      return false;
    }
    if (decision.action === 'block') {
      if (decision.reason === 'logout') ui.toast(t('accounts.logout'), 'ok');
      return false;
    }
    if (decision.action === 'relogin') {
      void mgr.open(path).then((next) => {
        sso.current = next.url;
      }).catch(() => ui.toast(t('states.sessionExpired'), 'error'));
      return false;
    }
    return true;
  }, [account.siteUrl, env, config.webview?.allow_hosts, boot?.auth.login_url, nav, mgr, path, ui, t]);

  const onMessage = useCallback((raw: unknown) => {
    const msg = parseBridgeMessage(raw);
    if (!msg) return;
    if (msg.type === 'toast') ui.toast(msg.text, 'ok');
    if (msg.type === 'close') nav.back();
    if (msg.type === 'route') {
      const decision = classifyNavigation(msg.path.startsWith('http') ? msg.path : trimEnd(account.siteUrl) + '/wp-admin/' + trimStart(msg.path), { siteUrl: account.siteUrl, env });
      if (decision.action === 'native') nav.push(decision.route);
    }
  }, [ui, nav, account.siteUrl, env]);

  const fallbackUrl = trimEnd(account.siteUrl) + '/wp-admin/' + trimStart(path);

  return canEmbed ? (
    <Embedded key={path} title={title} resolveUrl={resolveUrl} fallbackUrl={fallbackUrl} onNavigate={onNavigate} onMessage={onMessage} />
  ) : (
    <OpenOutside title={title} resolveUrl={resolveUrl} />
  );
}

function Embedded({ title, resolveUrl, fallbackUrl, onNavigate, onMessage }: {
  title: string;
  resolveUrl: () => Promise<string>;
  fallbackUrl: string;
  onNavigate: (url: string) => boolean | void;
  onMessage: (raw: unknown) => void;
}) {
  const { t } = useTranslation();
  const host = useRef<HTMLDivElement>(null);
  const view = useRef<EmbeddedView | null>(null);
  const [state, setState] = useState<'loading' | 'ready' | 'error'>('loading');
  const covered = useOverlay((s) => s.overlays > 0);
  const navRef = useRef(onNavigate);
  const msgRef = useRef(onMessage);
  navRef.current = onNavigate;
  msgRef.current = onMessage;

  const rect = () => {
    const r = host.current!.getBoundingClientRect();
    const x = Math.round(r.left);
    const y = Math.round(r.top);
    return { x, y, width: Math.round(r.right) - x, height: Math.round(r.bottom) - y };
  };

  useEffect(() => {
    let dead = false;
    (async () => {
      try {
        const url = await resolveUrl();
        if (dead || !host.current) return;
        const v = await platform().embedWebView!(url, rect(), {
          onNavigate: (next) => navRef.current(next),
          onMessage: (raw) => msgRef.current(raw),
        });
        if (dead) return void v.close();
        view.current = v;
        setState('ready');
      } catch {
        if (!dead) setState('error');
      }
    })();
    return () => {
      dead = true;
      void view.current?.close();
      view.current = null;
    };
  }, [resolveUrl]);

  useEffect(() => {
    if (!host.current) return;
    const ro = new ResizeObserver(() => void view.current?.setRect(rect()));
    ro.observe(host.current);
    const onWin = () => void view.current?.setRect(rect());
    window.addEventListener('resize', onWin);
    return () => {
      ro.disconnect();
      window.removeEventListener('resize', onWin);
    };
  }, []);

  useEffect(() => void view.current?.setVisible(!covered), [covered, state]);

  useEffect(() => {
    const k = (e: KeyboardEvent) => {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'r') {
        e.preventDefault();
        void view.current?.reload();
      }
    };
    window.addEventListener('keydown', k);
    return () => window.removeEventListener('keydown', k);
  }, []);

  return (
    <div className="embed" aria-label={title}>
      <div className="embed-host" ref={host}>
        {state === 'loading' && <div className="loading"><Loader2 className="spin" size={20} /></div>}
        {state === 'error' && (
          <div className="empty-state">
            <p className="error">{t('errors.server')}</p>
            <button className="btn-ghost" onClick={() => void platform().openExternal(fallbackUrl)}><ExternalLink size={15} /> {t('web.openBrowser')}</button>
          </div>
        )}
      </div>
    </div>
  );
}

function OpenOutside({ title, resolveUrl }: { title: string; resolveUrl: () => Promise<string> }) {
  const { t } = useTranslation();
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState(false);
  const open = async () => {
    setBusy(true);
    setErr(false);
    try {
      const url = await resolveUrl();
      const p = platform();
      await (p.openWeb ? p.openWeb(url, title) : p.openWebView ? p.openWebView(url, title) : p.openExternal(url));
    } catch {
      setErr(true);
    } finally {
      setBusy(false);
    }
  };
  return (
    <div className="page">
      <header className="page-head"><h1>{title}</h1></header>
      <div className="empty-state">
        <MonitorSmartphone size={36} strokeWidth={1.4} className="muted" />
        <h2>{t('web.title')}</h2>
        <p className="muted">{t('web.body')}</p>
        <button className="btn-primary" onClick={() => void open()} disabled={busy}>
          {busy ? <Loader2 className="spin" size={16} /> : <ExternalLink size={16} />}
          {t('web.open')}
        </button>
        {err && <p className="error">{t('errors.server')}</p>}
      </div>
    </div>
  );
}
