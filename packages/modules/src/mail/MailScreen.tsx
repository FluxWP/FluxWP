// Communication: Settings, Templates, Log and System. Same screen in wp-admin and the FluxWP app.
// Reads and writes go through the Flux Mail GraphQL operations. In wp-admin, Send, Queue, Blocklist and
// Connections are PHP forms the page prints hidden ("island", flux-ui.php): the screen shows the open one.
import { useCallback, useEffect, useLayoutEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { createGraphQLClient } from '@fluxwp/graphql';
import { FlxGraphQLProvider, useFlxQuery, useFlxViewer } from '@fluxwp/graphql/react';
import { FlxMailTemplate } from '@fluxwp/graphql/operations';
import { Loading, Screen } from '@fluxwp/ui';
import { useHost } from '../host';
import { translator } from './i18n';
import { ISLANDS, MailContext, type MailCtx, type Page } from './common';
import { SettingsPage } from './Settings';
import { TemplatesPage } from './Templates';
import { LogsPage } from './Logs';
import { SystemPage } from './System';
import './mail.css';
import './flxi.css';

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

const PAGES: Page[] = ['templates', 'send', 'queue', 'logs', 'blocklist', 'connections', 'settings', 'system'];

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

  useEffect(() => {
    const on = () => setRoute(parseHash(host.startPage));
    window.addEventListener('hashchange', on);
    return () => window.removeEventListener('hashchange', on);
  }, [host.startPage]);

  const go = useCallback(
    (page: Page, query?: Record<string, string>) => {
      const slug = ISLANDS[page];
      if (slug) {
        // The island arrives with its page only.
        host.openUrl((host.adminUrl ?? '/wp-admin/') + 'admin.php?page=' + slug);
        return;
      }
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
    },
    [host],
  );

  const toast = useCallback((text: string, kind: 'ok' | 'error' = 'ok') => {
    const id = Date.now() + Math.random();
    setToasts((l) => [...l, { id, text, kind }]);
    window.setTimeout(() => setToasts((l) => l.filter((x) => x.id !== id)), kind === 'error' ? 7000 : 3500);
  }, []);

  const ctx = useMemo<MailCtx>(() => ({ t, page: route.page, go, toast, logStatus: route.status }), [t, route.page, route.status, go, toast]);
  const caps = viewer.data?.flxViewer.capabilities;
  const allowed = caps ? caps.includes('flxi_manage') || caps.includes('manage_options') : undefined;

  const wp = host.mode === 'wp';
  const nav: { page: Page; label: string }[] = [
    { page: 'templates', label: t('Emails') },
    ...(wp
      ? [
          { page: 'send' as Page, label: t('Send') },
          { page: 'queue' as Page, label: t('Queue') },
        ]
      : []),
    { page: 'logs', label: t('Log') },
    ...(wp
      ? [
          { page: 'blocklist' as Page, label: t('Blocklist') },
          { page: 'connections' as Page, label: t('Connections') },
        ]
      : []),
    { page: 'settings', label: t('Settings') },
    { page: 'system', label: t('System check') },
  ];
  // Classic page heads: each tab has its own title and line.
  const HEADS: Record<Page, [string, string]> = {
    templates: [t('Emails'), t('All emails sent by the site. You can turn them on or off, edit their content, and choose who they are sent from.')],
    send: [t('Send'), t('Send an email, WhatsApp or SMS message to one person or a list using the branded template. Bulk sends are processed in the background, one after another.')],
    queue: [t('Delivery queue'), t('Live email, WhatsApp and SMS progress. Acceptance by a provider is not proof of delivery.')],
    logs: [t('Log'), t('Every email, WhatsApp and SMS message sent from the site. Click a row to see its content.')],
    blocklist: [
      t('Blocklist'),
      t(
        'People who asked not to be messaged. Blocked: nothing is sent on any channel. No promotions: they replied STOP, so only promotional messages stop on that channel; information messages and chats they start still go through.',
      ),
    ],
    connections: [t('Connections'), t('WhatsApp and SMS accounts in one place. Flux Certificates, Flux Chat and other plugins use these connections. Email settings are on the Settings tab.')],
    settings: [t('Settings'), t('Sender name and addresses, sending method, and email appearance.')],
    system: [t('System check'), t('Rows related to email, WhatsApp and SMS. Checks for all plugins are on the system screen.')],
  };
  const head: [string, ReactNode] = [...(HEADS[route.page] ?? HEADS.templates)];
  // The template editor shows the email's name with a way back, as the classic page did.
  const tpl = useFlxQuery(FlxMailTemplate, { key: route.key ?? '' }, { enabled: route.page === 'templates' && !!route.key && allowed === true });
  const editing = route.page === 'templates' && route.key ? tpl.data?.flxMailTemplate : undefined;
  if (editing) {
    const all = (host.adminUrl ?? '/wp-admin/') + 'admin.php?page=flxi';
    head[0] = editing.label;
    head[1] = (
      <>
        <a
          href={all}
          onClick={(e) => {
            if (e.metaKey || e.ctrlKey) return;
            e.preventDefault();
            go('templates');
          }}
        >
          ← {t('All emails')}
        </a>
        {editing.group ? ' · ' + editing.group : ''}
      </>
    );
  }
  const island = ISLANDS[route.page];
  // Opened in place from another screen: the island is not on this page, load it.
  useEffect(() => {
    if (island && wp && !document.querySelector(`[data-flxi-island="${route.page}"]`)) location.assign((host.adminUrl ?? '/wp-admin/') + 'admin.php?page=' + island);
  }, [island, wp, route.page, host.adminUrl]);

  return (
    <MailContext.Provider value={ctx}>
      <Screen<Page> className="flxmail-app flxi-wrap" width="wide" icon={host.appIcon} title={head[0]} subtitle={head[1]} tabs={nav.map((n) => ({ id: n.page, label: n.label }))} tab={route.page} onTab={(p) => go(p)}>
        {island ? (
          <IslandNode id={route.page} />
        ) : (
          <>
            {allowed === undefined ? <Loading /> : null}
            {allowed === false ? <p className="flxmail-empty">{t('You cannot open this screen.')}</p> : null}
            {allowed ? (
              <>
                {route.page === 'templates' ? <TemplatesPage editKey={route.key} onEdit={(key) => go('templates', key ? { key } : undefined)} /> : null}
                {route.page === 'logs' ? <LogsPage initialStatus={route.status} initialTemplate={route.template} initialCampaign={route.campaign} /> : null}
                {route.page === 'settings' ? <SettingsPage section={route.section} /> : null}
                {route.page === 'system' ? <SystemPage /> : null}
              </>
            ) : null}
          </>
        )}
      </Screen>
      <div className="flxmail-toasts">
        {toasts.map((x) => (
          <div key={x.id} className={'flxmail-toast' + (x.kind === 'error' ? ' is-error' : '')}>
            {x.text}
          </div>
        ))}
      </div>
    </MailContext.Provider>
  );
}

/** Moves the page's hidden island (flux-ui.php) into the screen while it is open. */
function IslandNode({ id }: { id: string }) {
  const ref = useRef<HTMLDivElement>(null);
  useLayoutEffect(() => {
    const el = document.querySelector<HTMLElement>(`[data-flxi-island="${CSS.escape(id)}"]`);
    const home = el?.parentElement ?? null;
    const box = ref.current;
    if (!el || !box) return;
    box.appendChild(el);
    // TinyMCE started while the island was hidden and kept its 100px minimum: give it the height of its textarea rows.
    const fit = () =>
      el.querySelectorAll<HTMLTextAreaElement>('textarea.wp-editor-area').forEach((ta) => {
        const frame = el.querySelector<HTMLIFrameElement>('#' + CSS.escape(ta.id) + '_ifr');
        const want = Math.round((ta.rows || 10) * 19.5 + 14);
        if (frame && frame.offsetHeight < want) frame.style.height = want + 'px';
      });
    fit();
    const late = window.setTimeout(fit, 400);
    return () => {
      window.clearTimeout(late);
      if (home && el.parentElement === box) home.appendChild(el);
    };
  }, [id]);
  return <div ref={ref} className="flxmail-islandslot" />;
}
