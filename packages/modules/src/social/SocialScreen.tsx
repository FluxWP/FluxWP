// Flux Social › Social Media. Same screen in wp-admin (wp-embed) and the FluxWP app; every read and write
// goes through the Flux Social GraphQL operations (plugins/flx-social/graphql/operations).
// Dashboard, posts, approvals, calendar, accounts (connect,
// WhatsApp), settings (apps, networks, auto sharing, log), the composer and the post details.
import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { useQueryClient } from '@tanstack/react-query';
import { createGraphQLClient } from '@fluxwp/graphql';
import { FlxGraphQLProvider, flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { FlxSocialBoot, FlxSocialCounts, FlxSocialTickRun } from '@fluxwp/graphql/operations';
import { Loading, Modal } from '@fluxwp/ui';
import { useHost } from '../host';
import { translator } from './i18n';
import { SocialCtx, type Ctx, type Page, type Post, type Query } from './common';
import { Dashboard } from './Dashboard';
import { PostsPage } from './Posts';
import { CalendarPage } from './Calendar';
import { AccountsPage } from './Accounts';
import { SettingsPage } from './Settings';
import { Composer } from './Composer';
import { PostDetail } from './PostDetail';
import './social.css';

interface Route {
  page: Page;
  query: Query;
}
interface Toast {
  id: number;
  text: string;
  kind: 'ok' | 'error';
}
interface ConfirmState {
  title: string;
  text: string;
  action: string;
  danger?: boolean;
  extra?: ReactNode;
  run: () => void;
}

const PAGES: Page[] = ['dashboard', 'posts', 'approvals', 'calendar', 'accounts', 'settings'];

function parseHash(start: string | undefined): Route {
  const raw = window.location.hash.replace(/^#\/?/, '');
  const [path = '', qs = ''] = raw.split('?');
  const page = (PAGES as string[]).includes(path) ? (path as Page) : (PAGES as string[]).includes(start ?? '') ? (start as Page) : 'dashboard';
  return { page, query: Object.fromEntries(new URLSearchParams(qs)) };
}

export function SocialScreen() {
  const host = useHost();
  const client = useMemo(() => createGraphQLClient({ api: host.client }), [host.client]);
  return (
    <FlxGraphQLProvider client={client} scope={host.scope}>
      <Social />
    </FlxGraphQLProvider>
  );
}

function Social() {
  const host = useHost();
  const { i18n } = useTranslation();
  const t = useMemo(() => translator(i18n.language), [i18n.language]);
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const boot = useFlxQuery(FlxSocialBoot, {}, { refetchOnWindowFocus: false });
  const [route, setRoute] = useState<Route>(() => parseHash(host.startPage));
  const [composer, setComposer] = useState<{ post: Post | null; scheduledAt?: number } | null>(null);
  const [detail, setDetail] = useState<Post | null>(null);
  const [toasts, setToasts] = useState<Toast[]>([]);
  const [confirm, setConfirm] = useState<ConfirmState | null>(null);
  const [menu, setMenu] = useState(false);

  useEffect(() => {
    const on = () => setRoute(parseHash(host.startPage));
    window.addEventListener('hashchange', on);
    return () => window.removeEventListener('hashchange', on);
  }, [host.startPage]);

  const go = useCallback((page: Page, query?: Query) => {
    const qs = query && Object.keys(query).length ? '?' + new URLSearchParams(query).toString() : '';
    window.location.hash = '/' + page + qs;
    setRoute({ page, query: query ?? {} });
    setMenu(false);
  }, []);

  const toast = useCallback((text: string, kind: 'ok' | 'error' = 'ok') => {
    const id = Date.now() + Math.random();
    setToasts((l) => [...l, { id, text, kind }]);
    window.setTimeout(() => setToasts((l) => l.filter((x) => x.id !== id)), kind === 'error' ? 7000 : 3500);
  }, []);

  const refreshPosts = useCallback(() => {
    void qc.invalidateQueries({ queryKey: [scope, 'gql', 'FlxSocialPosts'] });
    void qc.invalidateQueries({ queryKey: [scope, 'gql', 'FlxSocialPost'] });
    void qc.invalidateQueries({ queryKey: [scope, 'gql', 'FlxSocialStats'] });
    void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxSocialCounts, {}) });
    host.refreshCounts?.();
  }, [qc, scope, host]);
  const reloadBoot = useCallback(() => void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxSocialBoot, {}) }), [qc, scope]);

  // Heartbeat: counts every 45 s while the tab is visible; when posts are due the panel runs the scheduler.
  const counts = useFlxQuery(FlxSocialCounts, {}, { refetchInterval: 45_000, refetchIntervalInBackground: false, refetchOnWindowFocus: true });
  const tick = useFlxMutation(FlxSocialTickRun, { onSuccess: (r) => r.flxSocialTickRun && r.flxSocialTickRun.processed > 0 && refreshPosts() });
  const due = counts.data?.flxSocialCounts.due ?? 0;
  useEffect(() => {
    if (due > 0 && !tick.isPending && document.visibilityState === 'visible') tick.mutate({ input: {} });
  }, [due, counts.dataUpdatedAt]); // eslint-disable-line react-hooks/exhaustive-deps

  const data = boot.data;
  const cfg = data?.flxSocialConfig;
  const ctx = useMemo<Ctx | null>(() => {
    if (!data || !cfg) return null;
    const tz = cfg.tzOffset;
    const loc = i18n.language || 'en';
    const fmt: Ctx['fmt'] = (ts, style = 'datetime') => {
      const d = new Date((ts + tz) * 1000);
      const opt: Intl.DateTimeFormatOptions =
        style === 'date' ? { dateStyle: 'medium' } : style === 'time' ? { timeStyle: 'short' } : style === 'day' ? { weekday: 'long', day: 'numeric', month: 'long' } : { dateStyle: 'medium', timeStyle: 'short' };
      return new Intl.DateTimeFormat(loc, { ...opt, timeZone: 'UTC' }).format(d);
    };
    const pad = (n: number) => String(n).padStart(2, '0');
    return {
      t,
      boot: data,
      accounts: data.flxSocialAccounts,
      net: (id) => cfg.networks.find((n) => n.id === id),
      go,
      edit: (post, preset) => setComposer({ post, scheduledAt: preset?.scheduledAt }),
      detail: (p) => setDetail(p),
      toast,
      confirm: (o) => setConfirm(o),
      reloadBoot,
      refreshPosts,
      locale: loc,
      fmt,
      inputToTs: (v) => {
        const m = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/.exec(v);
        return m ? Math.floor(Date.UTC(+m[1]!, +m[2]! - 1, +m[3]!, +m[4]!, +m[5]!) / 1000) - tz : 0;
      },
      tsToInput: (ts) => {
        if (!ts) return '';
        const d = new Date((ts + tz) * 1000);
        return `${d.getUTCFullYear()}-${pad(d.getUTCMonth() + 1)}-${pad(d.getUTCDate())}T${pad(d.getUTCHours())}:${pad(d.getUTCMinutes())}`;
      },
      openUrl: host.openUrl,
      appMode: host.mode === 'app',
    };
  }, [data, cfg, t, go, toast, reloadBoot, refreshPosts, i18n.language, host.openUrl, host.mode]);

  if (boot.isLoading) return <Loading />;
  if (boot.isError || !ctx || !cfg) {
    return (
      <div className="flxsm-app">
        <div className="flxsm-notice is-error">{boot.error?.message || t('Something went wrong.')}</div>
        <button type="button" className="flxsm-btn" onClick={() => void boot.refetch()}>
          {t('Try again')}
        </button>
      </div>
    );
  }

  const c = counts.data?.flxSocialCounts ?? data!.flxSocialCounts;
  const nav: { page: Page; label: string; badge?: number; show: boolean }[] = [
    { page: 'dashboard', label: t('Dashboard'), show: true },
    { page: 'posts', label: t('Posts'), show: true },
    { page: 'approvals', label: t('Awaiting approval'), badge: c.pending, show: cfg.canPublish },
    { page: 'calendar', label: t('Calendar'), show: true },
    { page: 'accounts', label: t('Accounts'), show: true },
    { page: 'settings', label: t('Settings'), show: cfg.canManage },
  ];
  const page = route.page === 'settings' && !cfg.canManage ? 'dashboard' : route.page === 'approvals' && !cfg.canPublish ? 'posts' : route.page;

  return (
    <SocialCtx.Provider value={ctx}>
      <div className="flxsm-app" data-menu={menu ? 'open' : 'closed'}>
        <aside className="flxsm-side">
          <div className="flxsm-brand">
            {host.appIcon && <img src={host.appIcon} alt="" width={24} height={24} />}
            <strong>{t('Social Media')}</strong>
          </div>
          <button type="button" className="flxsm-btn is-primary flxsm-new" onClick={() => ctx.edit(null)}>
            + {t('New post')}
          </button>
          <nav>
            {nav
              .filter((n) => n.show)
              .map((n) => (
                <button key={n.page} type="button" className={'flxsm-nav' + (page === n.page ? ' is-on' : '')} onClick={() => go(n.page)}>
                  <span>{n.label}</span>
                  {!!n.badge && <span className="flxsm-count">{n.badge}</span>}
                </button>
              ))}
          </nav>
          {c.failed > 0 && (
            <button type="button" className="flxsm-nav is-warn" onClick={() => go('posts', { status: 'FAILED,PARTIAL' })}>
              {t('{n} posts with errors', { n: c.failed })}
            </button>
          )}
          <div className="flxsm-side-foot">
            {host.adminUrl && host.mode === 'wp' && (
              <a href={host.adminUrl}>{t('Back to dashboard')}</a>
            )}
          </div>
        </aside>
        <main className="flxsm-main">
          <header className="flxsm-top">
            <button type="button" className="flxsm-burger" aria-label={t('Menu')} onClick={() => setMenu((m) => !m)}>
              ☰
            </button>
            <h1>{nav.find((n) => n.page === page)?.label}</h1>
          </header>
          {page === 'dashboard' && <Dashboard />}
          {(page === 'posts' || page === 'approvals') && <PostsPage key={page + (route.query.status ?? '')} approvals={page === 'approvals'} initial={route.query} />}
          {page === 'calendar' && <CalendarPage />}
          {page === 'accounts' && <AccountsPage query={route.query} />}
          {page === 'settings' && <SettingsPage tab={route.query.tab} />}
        </main>
        {composer && (
          <Composer
            post={composer.post}
            presetAt={composer.scheduledAt}
            onClose={() => setComposer(null)}
            onSaved={() => {
              setComposer(null);
              refreshPosts();
            }}
          />
        )}
        {detail && <PostDetail postId={detail.databaseId} initial={detail} onClose={() => setDetail(null)} />}
        {confirm && (
          <Modal title={confirm.title} onClose={() => setConfirm(null)}>
            <p className="flxsm-confirm">{confirm.text}</p>
            {confirm.extra}
            <div className="flxsm-actions">
              <button type="button" className="flxsm-btn" onClick={() => setConfirm(null)}>
                {t('Cancel')}
              </button>
              <button
                type="button"
                className={'flxsm-btn ' + (confirm.danger ? 'is-danger' : 'is-primary')}
                onClick={() => {
                  const run = confirm.run;
                  setConfirm(null);
                  run();
                }}
              >
                {confirm.action}
              </button>
            </div>
          </Modal>
        )}
        <div className="flxsm-toasts" aria-live="polite">
          {toasts.map((x) => (
            <div key={x.id} className={'flxsm-toast is-' + x.kind}>
              {x.text}
            </div>
          ))}
        </div>
      </div>
    </SocialCtx.Provider>
  );
}
