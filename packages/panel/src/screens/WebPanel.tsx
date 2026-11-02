// Yerel ekranı olmayan menü. Masaüstünde sayfa uygulamanın içine gömülür (panelin üst/sol barı olmadan);
// gömme yoksa (tarayıcı) ayrı sekmede açma düğmesi gösterilir.
import { useCallback, useEffect, useLayoutEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ExternalLink, Loader2, MonitorSmartphone } from 'lucide-react';
import { platform, type EmbeddedView, type NavItem } from '@fluxwp/core';
import { Icon } from '@fluxwp/ui';
import { useActiveAccount, useApi, useMe } from '../state/hooks';
import { useOverlay } from '@fluxwp/ui';

export function WebPanel({ item, sub }: { item: NavItem; sub?: string }) {
  const account = useActiveAccount()!;
  const api = useApi(account);
  const me = useMe(account).data;
  const child = item.children.find((c) => c.id === sub);
  const path = child?.path ?? item.path;
  const title = child?.title ?? item.title;
  const fallbackUrl = child?.url ?? item.url;
  const canEmbed = !!platform().embedWebView;

  const resolveUrl = useCallback(async () => (me?.web_session ? (await api.session(path)).url : fallbackUrl), [me?.web_session, api, path, fallbackUrl]);

  return canEmbed ? (
    <Embedded key={path} title={title} resolveUrl={resolveUrl} fallbackUrl={fallbackUrl} />
  ) : (
    <OpenOutside title={title} parent={child ? item.title : undefined} icon={item.icon} resolveUrl={resolveUrl} />
  );
}

function Embedded({ title, resolveUrl, fallbackUrl }: { title: string; resolveUrl: () => Promise<string>; fallbackUrl: string }) {
  const { t } = useTranslation();
  const host = useRef<HTMLDivElement>(null);
  const view = useRef<EmbeddedView | null>(null);
  const [state, setState] = useState<'loading' | 'ready' | 'error'>('loading');
  const covered = useOverlay((s) => s.overlays > 0);

  const rect = () => {
    const r = host.current!.getBoundingClientRect();
    // Tam piksele yuvarla: kenarlarda 1 px boşluk/taşma kalmasın.
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
        const v = await platform().embedWebView!(url, rect());
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
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  useLayoutEffect(() => {
    if (!host.current) return;
    const ro = new ResizeObserver(() => void view.current?.setRect(rect()));
    ro.observe(host.current);
    const onWin = () => void view.current?.setRect(rect());
    window.addEventListener('resize', onWin);
    return () => (ro.disconnect(), window.removeEventListener('resize', onWin));
  }, []);

  useEffect(() => void view.current?.setVisible(!covered), [covered, state]);

  // ⌘R: sayfayı yenile (üst bar yok, sayfa doğrudan görünür).
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

function OpenOutside({ title, parent, icon, resolveUrl }: { title: string; parent?: string; icon: string; resolveUrl: () => Promise<string> }) {
  const { t } = useTranslation();
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState(false);
  const open = async () => {
    setBusy(true);
    setErr(false);
    try {
      const url = await resolveUrl();
      const p = platform();
      await (p.openWebView ? p.openWebView(url, title) : p.openExternal(url));
    } catch {
      setErr(true);
    } finally {
      setBusy(false);
    }
  };
  return (
    <div className="page">
      <header className="page-head">
        <div className="row">
          <span className="page-icon"><Icon name={icon} size={20} /></span>
          <div>
            <h1>{title}</h1>
            {parent && <p className="muted">{parent}</p>}
          </div>
        </div>
      </header>
      <div className="empty-state">
        <MonitorSmartphone size={36} strokeWidth={1.4} className="muted" />
        <h2>{t('web.title')}</h2>
        <p className="muted">{t('web.body')}</p>
        <button className="btn-primary" onClick={open} disabled={busy}>
          {busy ? <Loader2 className="spin" size={16} /> : <ExternalLink size={16} />}
          {t('web.open')}
        </button>
        {err && <p className="error">{t('errors.server')}</p>}
      </div>
    </div>
  );
}
