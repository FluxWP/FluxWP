// Flux Speed › Performance. Same screen in wp-admin (wp-embed) and the FluxWP app; every read and write
// goes through the Flux Speed GraphQL operations (plugins/flx-speed/graphql/operations). It is the
// only admin page (no classic page): Summary, Records (+ one record), Server, Database, Autoload, Cron, System check,
// PageSpeed, Cache and Settings (with the admin login address).
import { useCallback, useEffect, useMemo, useState } from 'react';
import { createGraphQLClient } from '@fluxwp/graphql';
import { FlxGraphQLProvider, useFlxQuery } from '@fluxwp/graphql/react';
import { FlxSpeedConfig } from '@fluxwp/graphql/operations';
import { Loading } from '@fluxwp/ui';
import { useHost } from '../host';
import { AutoloadTab, CronTab, DatabaseTab, ServerTab, SystemTab } from './tabs-system';
import { CacheTab, PageSpeedTab, SettingsTab } from './tabs-settings';
import { RecordDetail, RecordsTab, SummaryTab } from './tabs-monitor';
import { ErrorBox, SpeedProvider, emptyFilter, useT, type RecordFilter, type SpeedCtx } from './ui';
import './speed.css';

interface View {
  tab: string;
  id: number;
  filter: RecordFilter;
}

function initialView(wp: boolean, start?: string): View {
  let tab = start || 'summary';
  let id = 0;
  if (wp && typeof window !== 'undefined') {
    const p = new URLSearchParams(window.location.search);
    tab = p.get('tab') || tab;
    id = Number(p.get('id')) || 0;
  }
  return { tab, id, filter: emptyFilter };
}

export function SpeedScreen() {
  const host = useHost();
  const client = useMemo(() => createGraphQLClient({ api: host.client }), [host.client]);
  return (
    <FlxGraphQLProvider client={client} scope={host.scope}>
      <Speed />
    </FlxGraphQLProvider>
  );
}

function Speed() {
  const host = useHost();
  const { t } = useT();
  const q = useFlxQuery(FlxSpeedConfig, {});
  const wp = host.mode === 'wp';
  const [view, setView] = useState<View>(() => initialView(wp, host.startPage));
  const [hours, setHours] = useState(24);
  const [notice, setNotice] = useState<{ kind: 'success' | 'error'; text: string } | null>(null);

  const go = useCallback<SpeedCtx['go']>(
    (tab, extra) => {
      setNotice(null);
      setView((v) => ({ tab, id: extra?.id ?? 0, filter: extra?.filter ? { ...emptyFilter, ...extra.filter } : v.filter }));
      if (extra?.hours) setHours(extra.hours);
      window.scrollTo?.({ top: 0 });
    },
    [],
  );

  // Keep the address in step (WordPress admin): ?page=flx-speed&tab=…&id=…
  useEffect(() => {
    if (!wp) return;
    const u = new URL(window.location.href);
    u.searchParams.delete('tab');
    u.searchParams.delete('id');
    if (view.id) u.searchParams.set('id', String(view.id));
    else if (view.tab !== 'summary') u.searchParams.set('tab', view.tab);
    if (u.href !== window.location.href) window.history.replaceState(null, '', u);
  }, [wp, view.tab, view.id]);

  const ctx = useMemo(
    () =>
      q.data
        ? ({
            config: q.data.flxSpeedConfig,
            go,
            notify: (kind, text) => {
              setNotice({ kind, text });
              window.scrollTo?.({ top: 0, behavior: 'smooth' });
            },
            hours,
            setHours,
          } satisfies SpeedCtx)
        : null,
    [q.data, go, hours],
  );

  if (q.isLoading) return <Loading />;
  if (q.isError || !q.data || !ctx) return <div className="flxpf-app"><ErrorBox error={q.error} fallback={t('The action could not be completed.')} /></div>;
  const cfg = q.data.flxSpeedConfig;
  const tab = cfg.tabs.some((x) => x.key === view.tab) ? view.tab : 'summary';

  let body;
  if (view.id) body = <RecordDetail id={view.id} />;
  else
    switch (tab) {
      case 'records':
        body = <RecordsTab filter={view.filter} setFilter={(f) => setView((v) => ({ ...v, filter: f }))} />;
        break;
      case 'server':
        body = <ServerTab />;
        break;
      case 'database':
        body = <DatabaseTab />;
        break;
      case 'autoload':
        body = <AutoloadTab />;
        break;
      case 'cron':
        body = <CronTab />;
        break;
      case 'system':
        body = <SystemTab />;
        break;
      case 'pagespeed':
        body = <PageSpeedTab />;
        break;
      case 'cache':
        body = <CacheTab />;
        break;
      case 'settings':
        body = <SettingsTab />;
        break;
      default:
        body = <SummaryTab />;
    }

  return (
    <SpeedProvider value={ctx}>
      <div className="flxpf-app">
        <div className="flxpf-head">
          <h1>{t('Flux Performance')}</h1>
        </div>
        {notice && (
          <div className={'flxpf-notice is-' + notice.kind} role={notice.kind === 'error' ? 'alert' : 'status'}>
            <span>{notice.text}</span>
            <button type="button" className="flxpf-x" aria-label={t('Close')} onClick={() => setNotice(null)}>
              ×
            </button>
          </div>
        )}
        <nav className="flxpf-tabs" aria-label={t('Flux Performance')}>
          {cfg.tabs.map((x) => (
            <button key={x.key} type="button" className={'flxpf-tab' + (x.key === tab && !view.id ? ' is-active' : '')} onClick={() => go(x.key)}>
              {x.label}
            </button>
          ))}
        </nav>
        {body}
      </div>
    </SpeedProvider>
  );
}
