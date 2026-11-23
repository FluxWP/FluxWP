// Communication: Settings, Templates, Log and System. Same screen in wp-admin and the FluxWP app.
// Reads and writes go through the Flux Mail GraphQL operations.
import { useCallback, useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { createGraphQLClient } from '@fluxwp/graphql';
import { FlxGraphQLProvider, useFlxViewer } from '@fluxwp/graphql/react';
import { Loading } from '@fluxwp/ui';
import { useHost } from '../host';
import { translator } from './i18n';
import { MailContext, type MailCtx, type Page } from './common';
import { SettingsPage } from './Settings';
import { ConnectionsPage } from './Connections';
import { TemplatesPage } from './Templates';
import { LogsPage } from './Logs';
import { SystemPage } from './System';
import './mail.css';

interface Route {
  page: Page;
  key: string | null;
  status: string;
  template: string;
  campaign: string;
  section: string;
}
interface Toast {
  id: number;
  text: string;
  kind: 'ok' | 'error';
}

const PAGES: Page[] = ['templates', 'logs', 'settings', 'connections', 'system'];

function parseStart(start: string | undefined): Route {
  if (!start) return { page: 'templates', key: null, status: '', template: '', campaign: '', section: '' };
  if (start.startsWith('edit:')) return { page: 'templates', key: start.slice(5), status: '', template: '', campaign: '', section: '' };
  const [path = '', qs = ''] = start.split('?');
  const page = (PAGES as string[]).includes(path) ? (path as Page) : 'templates';
  const params = new URLSearchParams(qs);
  return {
    page,
    key: params.get('key'),
    status: params.get('status') ?? '',
    template: params.get('template') ?? '',
    campaign: params.get('campaign') ?? '',
    section: '',
  };
}

function parseHash(start: string | undefined): Route {
  const raw = window.location.hash.replace(/^#\/?/, '');
  if (!raw) return parseStart(start);
  const [path = '', qs = ''] = raw.split('?');
  if ((PAGES as string[]).includes(path)) {
    const params = new URLSearchParams(qs);
    return {
      page: path as Page,
      key: params.get('key'),
      status: params.get('status') ?? '',
      template: params.get('template') ?? '',
      campaign: params.get('campaign') ?? '',
      section: '',
    };
  }
  const base = parseStart(start);
  if (!window.location.hash.startsWith('#/')) return { ...base, section: raw };
  return base;
}

export function MailScreen() {
  const host = useHost();
  const client = useMemo(() => createGraphQLClient({ api: host.client }), [host.client]);
  return (
    <FlxGraphQLProvider client={client} scope={host.scope}>
      <Mail />
    </FlxGraphQLProvider>
  );
}

function Mail() {
  const host = useHost();
  const { i18n } = useTranslation();
  const t = useMemo(() => translator(i18n.language), [i18n.language]);
  const viewer = useFlxViewer();
  const [route, setRoute] = useState<Route>(() => parseHash(host.startPage));
  const [toasts, setToasts] = useState<Toast[]>([]);
  const [menu, setMenu] = useState(false);

  useEffect(() => {
    const on = () => setRoute(parseHash(host.startPage));
    window.addEventListener('hashchange', on);
    return () => window.removeEventListener('hashchange', on);
  }, [host.startPage]);

  const go = useCallback((page: Page, query?: Record<string, string>) => {
    const params = new URLSearchParams();
    if (query?.key) params.set('key', query.key);
    if (query?.status) params.set('status', query.status);
    if (query?.template) params.set('template', query.template);
    if (query?.campaign) params.set('campaign', query.campaign);
    const qs = params.toString();
    window.location.hash = '/' + page + (qs ? '?' + qs : '');
    setRoute({
      page,
      key: query?.key ?? null,
      status: query?.status ?? '',
      template: query?.template ?? '',
      campaign: query?.campaign ?? '',
      section: '',
    });
    setMenu(false);
  }, []);

  const toast = useCallback((text: string, kind: 'ok' | 'error' = 'ok') => {
    const id = Date.now() + Math.random();
    setToasts((l) => [...l, { id, text, kind }]);
    window.setTimeout(() => setToasts((l) => l.filter((x) => x.id !== id)), kind === 'error' ? 7000 : 3500);
  }, []);

  const ctx = useMemo<MailCtx>(() => ({ t, page: route.page, go, toast, logStatus: route.status }), [t, route.page, route.status, go, toast]);
  const caps = viewer.data?.flxViewer.capabilities;
  const allowed = caps ? caps.includes('flxi_manage') || caps.includes('manage_options') : undefined;

  const classic = useMemo(() => {
    if (!host.classicUrl) return undefined;
    if (!route.key) return host.classicUrl;
    const u = new URL(host.classicUrl, window.location.href);
    if (route.page === 'connections') {
      // The classic page takes edit=<id>|new&type=<type>.
      if (route.key.startsWith('new:')) {
        u.searchParams.set('edit', 'new');
        u.searchParams.set('type', route.key.slice(4));
      } else u.searchParams.set('edit', route.key);
    } else u.searchParams.set('key', route.key);
    return u.toString();
  }, [host.classicUrl, route.key, route.page]);

  const nav: { page: Page; label: string }[] = [
    { page: 'templates', label: t('Emails') },
    { page: 'logs', label: t('Log') },
    { page: 'connections', label: t('Connections') },
    { page: 'settings', label: t('Settings') },
    { page: 'system', label: t('System check') },
  ];

  return (
    <MailContext.Provider value={ctx}>
      <div className={'flxmail-app' + (menu ? ' is-menu' : '')}>
        <aside className="flxmail-side">
          <div className="flxmail-brand">
            {host.appIcon ? <img src={host.appIcon} alt="" width={22} height={22} /> : null}
            <strong>{t('Communication')}</strong>
          </div>
          <nav>
            {nav.map((n) => (
              <button key={n.page} type="button" className={'flxmail-nav' + (route.page === n.page ? ' is-on' : '')} onClick={() => go(n.page)}>
                {n.label}
              </button>
            ))}
          </nav>
          {classic ? (
            <p className="flxmail-side-foot">
              <a href={classic}>{t('Classic view')}</a>
            </p>
          ) : null}
        </aside>
        <main className="flxmail-main">
          <div className="flxmail-top">
            <button type="button" className="flxmail-burger" onClick={() => setMenu((v) => !v)} aria-label={t('Menu')}>
              ☰
            </button>
            <h1>{nav.find((n) => n.page === route.page)?.label}</h1>
          </div>
          {allowed === undefined ? <Loading /> : null}
          {allowed === false ? <p className="flxmail-empty">{t('You cannot open this screen.')}</p> : null}
          {allowed ? (
            <>
              {route.page === 'templates' ? <TemplatesPage editKey={route.key} onEdit={(key) => go('templates', key ? { key } : undefined)} /> : null}
              {route.page === 'logs' ? <LogsPage initialStatus={route.status} initialTemplate={route.template} initialCampaign={route.campaign} /> : null}
              {route.page === 'connections' ? <ConnectionsPage editKey={route.key} onEdit={(key) => go('connections', key ? { key } : undefined)} /> : null}
              {route.page === 'settings' ? <SettingsPage section={route.section} /> : null}
              {route.page === 'system' ? <SystemPage /> : null}
            </>
          ) : null}
        </main>
        <div className="flxmail-toasts">
          {toasts.map((x) => (
            <div key={x.id} className={'flxmail-toast' + (x.kind === 'error' ? ' is-error' : '')}>
              {x.text}
            </div>
          ))}
        </div>
      </div>
    </MailContext.Provider>
  );
}
