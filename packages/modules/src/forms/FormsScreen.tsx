// Forms: Dashboard, Forms, Responses, Settings, New form (the builder), Payments, Automations, System check and
// Add-ons. Same screen in wp-admin and the FluxWP app; data over the flxForms* GraphQL operations. The builder needs
// the block editor, which only its wp-admin page (Forms › New form) loads: elsewhere its tab opens that page.
// includes/flx/flux-ui.php lists the tabs this user has.
import { useCallback, useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { createGraphQLClient } from '@fluxwp/graphql';
import { FlxGraphQLProvider, useFlxViewer } from '@fluxwp/graphql/react';
import { Loading, Screen } from '@fluxwp/ui';
import { useHost } from '../host';
import { translator } from './i18n';
import { BUILDER_SLUG, FormsContext, MENU_PAGES, REACT_PAGES, type FormsCtx, type Page, type Route, type RouteOpts } from './common';
import { DashboardPage } from './Dashboard';
import { FormsPage } from './FormsList';
import { ResponsesPage } from './Responses';
import { SettingsPage } from './Settings';
import { PaymentsPage } from './Payments';
import { AutomationsPage } from './Automations';
import { SystemPage } from './System';
import { AddonsPage } from './Addons';
import { BuilderPage, builderHere } from './Builder';
import './forms.css';

interface Toast {
  id: number;
  text: string;
  kind: 'ok' | 'error';
}

const ALL: Page[] = ['dashboard', 'forms', 'responses', 'settings', 'create', 'payments', 'automations', 'system', 'addons'];

function parse(raw: string): Route | null {
  const [path = '', qs = ''] = raw.split('?');
  if (!(ALL as string[]).includes(path)) return null;
  const p = new URLSearchParams(qs);
  const flow = p.get('flow');
  return {
    page: path as Page,
    form: Number(p.get('form') ?? 0) || 0,
    entry: Number(p.get('entry') ?? 0) || 0,
    flow: flow === 'new' ? -1 : Number(flow ?? 0) || 0,
    tab: p.get('tab') ?? '',
  };
}

function current(start: string | undefined): Route {
  const hash = window.location.hash.replace(/^#\/?/, '');
  return (hash && parse(hash)) || parse(start ?? '') || { page: 'dashboard', form: 0, entry: 0, flow: 0, tab: '' };
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
    return (raw ?? '').split(',').filter((x): x is Page => (MENU_PAGES as string[]).includes(x));
  }, []);

  useEffect(() => {
    const on = () => setRoute(current(host.startPage));
    window.addEventListener('hashchange', on);
    return () => window.removeEventListener('hashchange', on);
  }, [host.startPage]);

  const go = useCallback(
    (page: Page, opts?: RouteOpts) => {
      if (page === 'create' && !builderHere()) {
        host.openUrl(admin + 'admin.php?page=' + BUILDER_SLUG + (opts?.form ? '&form=' + opts.form : ''));
        return;
      }
      const params = new URLSearchParams();
      if (opts?.form) params.set('form', String(opts.form));
      if (opts?.entry) params.set('entry', String(opts.entry));
      if (opts?.flow) params.set('flow', opts.flow < 0 ? 'new' : String(opts.flow));
      if (opts?.tab) params.set('tab', opts.tab);
      const qs = params.toString();
      window.location.hash = '/' + page + (qs ? '?' + qs : '');
      setRoute({ page, form: opts?.form ?? 0, entry: opts?.entry ?? 0, flow: opts?.flow ?? 0, tab: opts?.tab ?? '' });
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
  // wp-admin: a link to the builder (#/create) on another Forms page opens the builder's page.
  const island = wp && route.page === 'create' && !builderHere();
  useEffect(() => {
    if (island) host.openUrl(admin + 'admin.php?page=' + BUILDER_SLUG + (route.form ? '&form=' + route.form : ''));
  }, [island, host, admin, route.form]);

  const tabs: { id: Page; label: string }[] = [
    { id: 'dashboard', label: t('Dashboard') },
    { id: 'forms', label: t('Forms') },
    { id: 'responses', label: t('Responses') },
    { id: 'settings', label: t('Settings') },
  ];
  const extra: [Page, string][] = [
    ['create', t('New form')],
    ['payments', t('Payments')],
    ['automations', t('Automations')],
    ['system', t('System check')],
    ['addons', t('Add-ons')],
  ];
  // wp-admin: the pages in this user's Forms menu. The app: the React ones an administrator may open.
  const appPages: Page[] = v?.isAdmin ? ['payments', 'automations', 'addons'] : [];
  for (const [id, label] of extra) if (wp ? islands.includes(id) : appPages.includes(id)) tabs.push({ id, label });

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
                {route.page === 'create' ? <BuilderPage key={route.form} form={route.form} /> : null}
                {route.page === 'payments' ? <PaymentsPage /> : null}
                {route.page === 'automations' ? <AutomationsPage flow={route.flow} tab={route.tab} /> : null}
                {route.page === 'system' ? <SystemPage /> : null}
                {route.page === 'addons' ? <AddonsPage /> : null}
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
