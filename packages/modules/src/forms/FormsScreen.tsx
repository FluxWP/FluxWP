// Forms: Dashboard, Forms and Responses. Same screen in wp-admin and the FluxWP app; data over the flxForms*
// GraphQL operations. In wp-admin the builder, the panel, Payments, Automations, System check and Add-ons are
// still their own pages: their tabs open them (includes/flx/flux-ui.php lists the ones this user has).
import { useCallback, useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { createGraphQLClient } from '@fluxwp/graphql';
import { FlxGraphQLProvider, useFlxViewer } from '@fluxwp/graphql/react';
import { Loading, Screen } from '@fluxwp/ui';
import { useHost } from '../host';
import { translator } from './i18n';
import { FormsContext, ISLANDS, REACT_PAGES, type FormsCtx, type Page, type Route } from './common';
import { DashboardPage } from './Dashboard';
import { FormsPage } from './FormsList';
import { ResponsesPage } from './Responses';
import { SettingsPage } from './Settings';
import './forms.css';

interface Toast {
  id: number;
  text: string;
  kind: 'ok' | 'error';
}

const ALL: Page[] = ['dashboard', 'forms', 'responses', 'settings', 'create', 'panel', 'payments', 'automations', 'system', 'addons'];

function parse(raw: string): Route | null {
  const [path = '', qs = ''] = raw.split('?');
  if (!(ALL as string[]).includes(path)) return null;
  const p = new URLSearchParams(qs);
  return { page: path as Page, form: Number(p.get('form') ?? 0) || 0, entry: Number(p.get('entry') ?? 0) || 0 };
}

function current(start: string | undefined): Route {
  const hash = window.location.hash.replace(/^#\/?/, '');
  return (hash && parse(hash)) || parse(start ?? '') || { page: 'dashboard', form: 0, entry: 0 };
}

export function FormsScreen() {
  const host = useHost();
  const client = useMemo(() => createGraphQLClient({ api: host.client }), [host.client]);
  return (
    <FlxGraphQLProvider client={client} scope={host.scope}>
      <Forms />
    </FlxGraphQLProvider>
  );
}

function Forms() {
  const host = useHost();
  const { i18n } = useTranslation();
  const t = useMemo(() => translator(i18n.language), [i18n.language]);
  const viewer = useFlxViewer();
  const [route, setRoute] = useState<Route>(() => current(host.startPage));
  const [toasts, setToasts] = useState<Toast[]>([]);
  const wp = host.mode === 'wp';
  const admin = host.adminUrl ?? '/wp-admin/';
  // The other pages this user may open: PHP lists them on #flxf-islands (data-tabs).
  const islands = useMemo<Page[]>(() => {
    const raw = typeof document !== 'undefined' ? document.getElementById('flxf-islands')?.dataset.tabs : undefined;
    return (raw ?? '').split(',').filter((x): x is Page => x in ISLANDS);
  }, []);

  useEffect(() => {
    const on = () => setRoute(current(host.startPage));
    window.addEventListener('hashchange', on);
    return () => window.removeEventListener('hashchange', on);
  }, [host.startPage]);

  const go = useCallback(
    (page: Page, opts?: { form?: number; entry?: number }) => {
      const slug = ISLANDS[page];
      if (slug) {
        host.openUrl(admin + 'admin.php?page=' + slug);
        return;
      }
      const params = new URLSearchParams();
      if (opts?.form) params.set('form', String(opts.form));
      if (opts?.entry) params.set('entry', String(opts.entry));
      const qs = params.toString();
      window.location.hash = '/' + page + (qs ? '?' + qs : '');
      setRoute({ page, form: opts?.form ?? 0, entry: opts?.entry ?? 0 });
    },
    [host, admin],
  );

  const toast = useCallback((text: string, kind: 'ok' | 'error' = 'ok') => {
    const id = Date.now() + Math.random();
    setToasts((l) => [...l, { id, text, kind }]);
    window.setTimeout(() => setToasts((l) => l.filter((x) => x.id !== id)), kind === 'error' ? 7000 : 3500);
  }, []);

  const num = useCallback((n: number) => new Intl.NumberFormat(i18n.language || undefined).format(n), [i18n.language]);
  const ctx = useMemo<FormsCtx>(() => ({ t, wp, go, toast, num }), [t, wp, go, toast, num]);

  const v = viewer.data?.flxViewer;
  const allowed = v ? v.isAdmin || v.capabilities.includes('Emsfb') : undefined;
  const island = ISLANDS[route.page];

  // A link to one of the other pages (#/panel …): open it.
  useEffect(() => {
    if (island) host.openUrl(admin + 'admin.php?page=' + island);
  }, [island, host, admin]);

  const tabs: { id: Page; label: string }[] = [
    { id: 'dashboard', label: t('Dashboard') },
    { id: 'forms', label: t('Forms') },
    { id: 'responses', label: t('Responses') },
    { id: 'settings', label: t('Settings') },
  ];
  if (wp) {
    const extra: [Page, string][] = [
      ['create', t('New form')],
      ['panel', t('Panel')],
      ['payments', t('Payments')],
      ['automations', t('Automations')],
      ['system', t('System check')],
      ['addons', t('Add-ons')],
    ];
    for (const [id, label] of extra) if (islands.includes(id)) tabs.push({ id, label });
  }

  return (
    <FormsContext.Provider value={ctx}>
      <Screen<Page>
        className="flxfrm-app"
        width="wide"
        icon={host.appIcon}
        title={t('Forms')}
        tabs={tabs}
        tab={route.page}
        onTab={(p) => go(p)}
      >
        {island ? (
          <Loading />
        ) : (
          <>
            {allowed === undefined ? <Loading /> : null}
            {allowed === false ? <p className="flxfrm-empty">{t('You cannot open this screen.')}</p> : null}
            {allowed && REACT_PAGES.includes(route.page) ? (
              <>
                {route.page === 'dashboard' ? <DashboardPage /> : null}
                {route.page === 'forms' ? <FormsPage /> : null}
                {route.page === 'responses' ? <ResponsesPage form={route.form} entry={route.entry} /> : null}
                {route.page === 'settings' ? <SettingsPage /> : null}
              </>
            ) : null}
          </>
        )}
      </Screen>
      <div className="flxfrm-toasts" aria-live="polite">
        {toasts.map((x) => (
          <div key={x.id} className={'flxfrm-toast' + (x.kind === 'error' ? ' is-error' : '')}>
            {x.text}
          </div>
        ))}
      </div>
    </FormsContext.Provider>
  );
}
