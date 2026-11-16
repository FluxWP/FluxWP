// Flux Analytics › Statistics. Same screen in wp-admin (wp-embed) and the FluxWP app; every read and write
// goes through the Flux Analytics GraphQL operations. It has everything the classic page has: range and
// visitor filters, sticky headline cards, chart, live panel (5 s), world map, funnel, referral links,
// channels, roles, pages, referrers, device/browser/os/language, campaigns, events, 404s, lists and page details.
import { useEffect, useMemo, useRef, useState, type FormEvent, type ReactNode } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import {
  FlxAnalyticsBreakdown,
  FlxAnalyticsEvents,
  FlxAnalyticsFastTest,
  FlxAnalyticsFunnel,
  FlxAnalyticsPage,
  FlxAnalyticsPages,
  FlxAnalyticsRealtime,
  FlxAnalyticsRefSave,
  FlxAnalyticsReferrers,
  FlxAnalyticsReport,
} from '@fluxwp/graphql/operations';
import type {
  FlxAnalyticsFilterInput,
  FlxAnalyticsFunnelBy,
  FlxAnalyticsReportQuery,
  FlxAnalyticsDimKind,
} from '@fluxwp/graphql/types';
import { Modal } from '@fluxwp/ui';
import {
  BarChart,
  BarTable,
  ConfirmDialog,
  CopyButton,
  FilterBar,
  Flag,
  Kpi,
  KpiSkeleton,
  Panel,
  PromptDialog,
  Shell,
  SkRows,
  Tabs,
  fillDays,
  filterInput,
  useConfig,
  useDebounced,
  useFilters,
  useFmt,
  withAnalytics,
} from './lib';

type Report = FlxAnalyticsReportQuery['flxAnalyticsReport'];
type PageRow = Report['pages']['items'][number];
type RefRow = Report['refs'][number];
type Funnel = Report['funnel'];
type Realtime = Report['realtime'];
type DimKind = FlxAnalyticsDimKind;

const KIND_KEY: Record<string, DimKind> = {
  channel: 'CHANNEL',
  device: 'DEVICE',
  browser: 'BROWSER',
  os: 'OS',
  lang: 'LANG',
  country: 'COUNTRY',
  utm_source: 'UTM_SOURCE',
  utm_medium: 'UTM_MEDIUM',
  utm_campaign: 'UTM_CAMPAIGN',
};

const slug = (v: string) =>
  v
    .toLocaleLowerCase('tr')
    .replace(/ı/g, 'i')
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .replace(/[^a-z0-9_-]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 32);

/* ------------------------------------------------------------------ the screen */

function Stats() {
  const { t, n, pf, dur, day } = useFmt();
  const cfg = useConfig();
  const { f, set } = useFilters();
  const filter = useMemo(() => filterInput(f), [f]);
  const [visible, setVisible] = useState(!document.hidden);
  useEffect(() => {
    const on = () => setVisible(!document.hidden);
    document.addEventListener('visibilitychange', on);
    return () => document.removeEventListener('visibilitychange', on);
  }, []);

  // This browser is not counted when the user's role is excluded (the classic screen sets the same flag).
  useEffect(() => {
    try {
      if (cfg.ignoreBrowser) localStorage.setItem('flxan_ignore', '1');
      else localStorage.removeItem('flxan_ignore');
    } catch {
      /* storage can be blocked */
    }
  }, [cfg.ignoreBrowser]);

  const q = useFlxQuery(FlxAnalyticsReport, { filter }, { refetchInterval: visible ? 60_000 : false, refetchOnWindowFocus: false });
  const rt = useFlxQuery(
    FlxAnalyticsRealtime,
    { filter, minutes: 30, kpi: true },
    { refetchInterval: visible ? 5_000 : false, enabled: !!q.data, retry: false },
  );
  const [listCfg, setListCfg] = useState<ListCfg | null>(null);
  const [pageOpen, setPageOpen] = useState<{ target: string; title: string } | null>(null);
  const [seg, setSeg] = useState<Record<string, DimKind>>({ tech: 'DEVICE', camp: 'UTM_SOURCE' });
  const [eventName, setEventName] = useState('');

  // #page=/path/ opens a page's details (the classic screen's shared links).
  useEffect(() => {
    const p = new URLSearchParams(window.location.hash.slice(1)).get('page');
    if (p) setPageOpen({ target: p, title: p });
  }, []);

  const d = q.data?.flxAnalyticsReport;
  const live: Realtime | undefined = rt.data?.flxAnalyticsRealtime ?? d?.realtime;
  const kpi = rt.data?.flxAnalyticsRealtime.kpi ?? d?.kpi;
  const refs = d?.refs ?? [];
  const filtered = f.seg !== 'all' || !!f.rc;
  const empty = !!d && !filtered && !d.kpi.totals.pageviews && !d.kpi.prev.pageviews && !d.realtime.pageviews;

  const filterNote = (() => {
    if (!filtered) return null;
    const parts: string[] = [];
    if (f.seg !== 'all') parts.push(cfg.segments.find((s) => s.value === f.seg)?.label ?? f.seg);
    if (f.rc) parts.push(f.rc === '*' ? t('Referral links only') : (refs.find((r) => r.code === f.rc)?.label ?? f.rc));
    return parts.join(' · ');
  })();

  const exportHref = () => {
    const p = new URLSearchParams({ type: 'site', range: f.range });
    if (f.range === 'custom') {
      p.set('start', f.start);
      p.set('end', f.end);
    }
    if (f.seg !== 'all') p.set('seg', f.seg);
    if (f.rc) p.set('rc', f.rc);
    return cfg.exportUrl + (cfg.exportUrl.includes('?') ? '&' : '?') + p.toString();
  };

  const openPage = (it: { path: string; postId?: number; title?: string }) => setPageOpen({ target: it.postId ? String(it.postId) : it.path, title: it.title || it.path });
  const rangeTitle = d ? d.range.label + ' · ' + day(d.range.start, { day: 'numeric', month: 'long', year: 'numeric' }) + ' – ' + day(d.range.end, { day: 'numeric', month: 'long', year: 'numeric' }) : '';

  return (
    <Shell
      title={t('Statistics')}
      busy={q.isFetching && !q.data}
      head={
        <div className="flxan-tools">
          <FilterBar f={f} set={set} refs={refs.map((r) => ({ code: r.code, label: r.label }))} />
          <a className="flxan-btn" href={exportHref()}>
            {t('Download CSV')}
          </a>
        </div>
      }
    >
      <FastTest />
      {empty && (
        <div className="flxan-banner">
          <p>
            <b>{t('No data yet.')}</b>
          </p>
          <p>{t('Open the site in a browser (or phone) where you are not logged in; the visit shows up here within a few minutes.')}</p>
        </div>
      )}
      {filterNote && (
        <div className="flxan-banner">
          <p className="flxan-filterbar">
            {t('Filter:')} <b>{filterNote}</b>{' '}
            <button type="button" className="flxan-btn" onClick={() => set({ seg: 'all', rc: '' })}>
              {t('Remove filter')}
            </button>
          </p>
        </div>
      )}
      {q.isError && (
        <div className="flxan-banner">
          <p>{q.error?.message || t('Could not load. Try again in a moment.')}</p>
        </div>
      )}

      <div className="flxan-cards flxan-kpis">
        {kpi ? (
          <>
            <Kpi label={t('Visitors')} value={n(kpi.totals.visitors)} ch={kpi.change.visitors} sub={t('Previous period: %s', n(kpi.prev.visitors))} tone="green" icon="users" />
            <Kpi label={t('Page views')} value={n(kpi.totals.pageviews)} ch={kpi.change.pageviews} sub={t('Previous period: %s', n(kpi.prev.pageviews))} tone="blue" icon="eye" />
            <Kpi label={t('Visits')} value={n(kpi.visits.sessions)} ch={kpi.change.sessions} sub={t('Previous period: %s', n(kpi.visitsPrev.sessions))} tone="violet" icon="door" />
            {(() => {
              const ppv = kpi.totals.visitors ? kpi.totals.pageviews / kpi.totals.visitors : 0;
              const pp = kpi.prev.visitors ? kpi.prev.pageviews / kpi.prev.visitors : 0;
              return <Kpi label={t('Pages / visitor')} value={pf(ppv)} ch={pp ? Math.round(((ppv - pp) / pp) * 1000) / 10 : null} sub={t('Previous period: %s', pf(pp))} tone="amber" icon="layers" />;
            })()}
            <Kpi label={t('Avg. visit length')} value={dur(kpi.visits.avgDuration)} ch={kpi.change.avgDuration} sub={t('Previous period: %s', dur(kpi.visitsPrev.avgDuration))} tone="teal" icon="clock" />
            <Kpi label={t('Bounce rate')} value={pf(kpi.visits.bounceRate) + '%'} ch={kpi.change.bounceRate} sub={t('Left after seeing one page')} tone="rose" icon="bounce" invert />
          </>
        ) : (
          <KpiSkeleton tones={[['green', 'users'], ['blue', 'eye'], ['violet', 'door'], ['amber', 'layers'], ['teal', 'clock'], ['rose', 'bounce']]} />
        )}
      </div>

      <div className="flxan-top">
        <div className="flxan-panel">
          <div className="flxan-panel__head">
            <h2>{d ? rangeTitle : <i className="flxan-sk flxan-sk--title" style={{ width: 260 }} />}</h2>
            <div className="flxan-legend">
              <span>
                <i style={{ background: 'var(--flxan-accent)' }} />
                {t('Visitors')}
              </span>
              <span>
                <i style={{ background: 'var(--flxan-accent-2)' }} />
                {t('Page views')}
              </span>
            </div>
          </div>
          {kpi ? <BarChart items={kpi.series.items} group={kpi.series.group} /> : <div className="flxan-chart" style={{ minHeight: 260 }}><i className="flxan-sk" style={{ height: 220, width: '100%' }} /></div>}
        </div>
        <LivePanel r={live} />
      </div>

      {!d ? (
        <div className="flxan-grid">
          {[true, true, true, false, false, false, false, false, false].map((wide, i) => (
            <div key={i} className={'flxan-panel flxan-skpanel' + (wide ? ' flxan-wide' : '')}>
              <div className="flxan-panel__head">
                <i className="flxan-sk flxan-sk--title" style={{ width: '30%' }} />
              </div>
              <SkRows rows={wide ? 4 : 6} />
            </div>
          ))}
        </div>
      ) : (
        <div className="flxan-grid">
          <WorldMap d={d} />
          <FunnelPanel d={d} filter={filter} onRc={(rc) => set({ rc })} />
          <RefsPanel refs={d.refs} onRc={(rc) => { set({ rc }); window.scrollTo({ top: 0, behavior: 'smooth' }); }} />

          <Panel title={t('Traffic sources')}>
            <BarTable rows={d.dims.find((x) => x.kind === 'CHANNEL')?.items ?? []} title={t('Channel')} label={(r) => r.label} sort={(r) => r.visitors} cols={[{ head: t('Visitors'), cell: (r) => n(r.visitors) }, { head: t('Page views'), cell: (r) => n(r.pageviews), second: true }]} />
          </Panel>
          <Panel title={t('By role')}>
            <BarTable
              rows={d.segments}
              title={t('Visitor type')}
              label={(r) => (
                <>
                  {r.label}
                  {r.value === '' && <small>{t('not logged in')}</small>}
                </>
              )}
              sort={(r) => r.visitors}
              onRow={(r) => set({ seg: r.value === '' ? 'guest' : r.value })}
              cols={[{ head: t('Visitors'), cell: (r) => n(r.visitors) }, { head: t('Page views'), cell: (r) => n(r.pageviews), second: true }]}
            />
            <p className="flxan-note">{t('Click a row to filter the whole screen to that visitor type. For outside traffic only, pick "Outside visitors" above.')}</p>
          </Panel>
          <Panel title={t('Most viewed pages')}>
            <BarTable rows={d.pages.items} title={t('Pages')} label={(r) => <PageLabel r={r} />} sort={(r) => r.pageviews} onRow={openPage} cols={[{ head: t('Visitors'), cell: (r) => n(r.visitors) }, { head: t('Page views'), cell: (r) => n(r.pageviews), second: true }]} />
            <More total={d.pages.total} shown={d.pages.items.length} onClick={() => setListCfg({ kind: 'pages', title: t('Most viewed pages') })} />
          </Panel>
          <Panel title={t('Referring sites')}>
            <BarTable
              rows={d.referrers.items}
              title={t('Site')}
              empty={t('No visits from other sites yet.')}
              label={(r) => (
                <>
                  {r.host}
                  <small>{r.channelLabel}</small>
                </>
              )}
              sort={(r) => r.visitors}
              onRow={(r) => window.open('https://' + r.host, '_blank', 'noopener')}
              cols={[{ head: t('Visitors'), cell: (r) => n(r.visitors) }, { head: t('Page views'), cell: (r) => n(r.pageviews), second: true }]}
            />
            <More total={d.referrers.total} shown={d.referrers.items.length} onClick={() => setListCfg({ kind: 'referrers', title: t('Referring sites') })} />
          </Panel>

          <DimTabs id="tech" title={t('Devices and browsers')} kinds={['device', 'browser', 'os', 'lang']} d={d} cur={seg.tech!} setCur={(k) => setSeg({ ...seg, tech: k })} more={(k, title) => setListCfg({ kind: 'dim', dim: k, title })} />
          {['utm_source', 'utm_medium', 'utm_campaign'].some((k) => (d.dims.find((x) => x.kind === KIND_KEY[k])?.items.length ?? 0) > 0) && (
            <DimTabs id="camp" title={t('Campaigns (UTM)')} kinds={['utm_source', 'utm_medium', 'utm_campaign']} d={d} cur={seg.camp!} setCur={(k) => setSeg({ ...seg, camp: k })} more={(k, title) => setListCfg({ kind: 'dim', dim: k, title })} />
          )}

          <EventsPanel d={d} filter={filter} name={eventName} setName={setEventName} more={(name) => setListCfg({ kind: 'events', title: t('Clicks and conversions'), name })} />

          {d.notFound.items.length > 0 && (
            <Panel title={t('Pages not found (404)')}>
              <BarTable rows={d.notFound.items} title={t('Address')} label={(r) => r.path} sort={(r) => r.pageviews} cols={[{ head: t('Visitors'), cell: (r) => n(r.visitors) }, { head: t('Page views'), cell: (r) => n(r.pageviews), second: true }]} />
              <More total={d.notFound.total} shown={d.notFound.items.length} onClick={() => setListCfg({ kind: 'notfound', title: t('Pages not found (404)') })} />
            </Panel>
          )}
        </div>
      )}
      <p className="flxan-note">{t('Visitors: the sum of each day\'s unique people. A person is recognised only within the same day; someone who arrives through a referral link counts for that code for 30 days. The country is worked out from the browser\'s time zone, not the IP address.')}</p>

      {listCfg && <ListModal cfg={listCfg} filter={filter} onClose={() => setListCfg(null)} onPage={(r) => { setListCfg(null); openPage(r); }} />}
      {pageOpen && <PageModal target={pageOpen.target} title={pageOpen.title} filter={filter} range={d?.range} group={kpi?.series.group} onClose={() => setPageOpen(null)} />}
    </Shell>
  );
}

export const StatsScreen = withAnalytics(Stats);

/* ------------------------------------------------------------------- pieces */

const PageLabel = ({ r }: { r: { title: string; path: string } }) => (
  <>
    {r.title}
    {r.title !== r.path && <small>{r.path}</small>}
  </>
);

function More({ total, shown, onClick }: { total: number | null; shown: number; onClick: () => void }) {
  const { t, n } = useFmt();
  if (!total || total <= shown) return null;
  return (
    <div className="flxan-more">
      <button type="button" className="flxan-btn" onClick={onClick}>
        {t('See all (%s)', n(total))}
      </button>
    </div>
  );
}

/** The fast endpoint is tried once a day (every time on Settings) and the result is remembered. */
function FastTest() {
  const cfg = useConfig();
  const m = useFlxMutation(FlxAnalyticsFastTest);
  const done = useRef(false);
  useEffect(() => {
    if (!cfg.fastEndpoint || done.current) return;
    done.current = true;
    fetch(cfg.fastEndpoint + '?test=1&_t=' + Date.now(), { cache: 'no-store', credentials: 'omit' })
      .then((r) => (r.ok ? r.text() : ''))
      .catch(() => '')
      .then((txt) => m.mutate({ input: { ok: txt.trim() === 'ok' } }));
  }, [cfg.fastEndpoint, m]);
  return null;
}

function LivePanel({ r }: { r?: Realtime }) {
  const { t, n } = useFmt();
  const last = useRef<number | null>(null);
  const [flash, setFlash] = useState(false);
  useEffect(() => {
    if (!r) return;
    if (last.current !== null && last.current !== r.active) {
      setFlash(true);
      const id = setTimeout(() => setFlash(false), 900);
      last.current = r.active;
      return () => clearTimeout(id);
    }
    last.current = r.active;
  }, [r]);
  if (!r) {
    return (
      <div className="flxan-panel flxan-live">
        <i className="flxan-sk flxan-sk--title" style={{ width: '30%' }} />
        <i className="flxan-sk flxan-sk--big" />
        <SkRows rows={5} />
      </div>
    );
  }
  const max = Math.max(1, ...r.perMinute);
  const w = 100 / Math.max(1, r.perMinute.length);
  const list = (title: string, rows: { key: string; label: ReactNode; v: number }[]) => {
    if (!rows.length) return null;
    const m = Math.max(...rows.map((x) => x.v));
    return (
      <div className="flxan-live__list">
        <h3>{title}</h3>
        {rows.slice(0, 5).map((x) => (
          <div key={x.key} className="flxan-live__row">
            <span className="bar" style={{ width: (m ? (x.v / m) * 100 : 0).toFixed(1) + '%' }} />
            <span className="txt">{x.label}</span>
            <b>{n(x.v)}</b>
          </div>
        ))}
      </div>
    );
  };
  return (
    <div className="flxan-panel flxan-live" id="flxan-rt">
      <div className="flxan-panel__head">
        <h2>
          <span className="flxan-live-dot" aria-hidden="true" />
          {t('Live')}
        </h2>
        <span className="flxan-muted">{t('Refreshes every 5 sec')}</span>
      </div>
      <div className="flxan-live__now">
        <div>
          <div className={'flxan-live__big' + (flash ? ' flash' : '')}>{n(r.active)}</div>
          <div className="flxan-muted">{t('on the site now (last 5 min)')}</div>
        </div>
        <div>
          <div className="flxan-live__mid">{n(r.visitors)}</div>
          <div className="flxan-muted">
            {t('visitors in the last %s min', r.minutes)} · {t('%s page views', n(r.pageviews))}
          </div>
        </div>
      </div>
      <svg className="flxan-live__bars" viewBox="0 0 100 40" preserveAspectRatio="none" aria-hidden="true">
        {r.perMinute.map((v, i) => {
          const h = (v / max) * 40;
          return (
            <rect key={i} x={i * w + w * 0.12} y={40 - h} width={w * 0.76} height={Math.max(h, v ? 1.5 : 0.6)} rx={1} className={v ? undefined : 'z'}>
              <title>{t('%1$s min ago: %2$s', r.perMinute.length - 1 - i, v)}</title>
            </rect>
          );
        })}
      </svg>
      <div className="flxan-live__axis">
        <span>{t('%s min ago', r.minutes)}</span>
        <span>{t('now')}</span>
      </div>
      {!r.visitors && <div className="flxan-empty">{t('Nobody here right now')}</div>}
      {list(t('Pages'), r.pages.map((x) => ({ key: x.path, label: x.title, v: x.pageviews })))}
      {list(t('Referral links'), r.codes.map((x) => ({ key: x.code, label: x.label, v: x.visitors })))}
      {list(t('Countries'), r.countries.map((x) => ({ key: x.value, label: <><Flag cc={x.value} />{x.label}</>, v: x.visitors })))}
      {list(t('Referring sites'), r.referrers.map((x) => ({ key: x.host, label: x.host, v: x.pageviews })))}
    </div>
  );
}

declare global {
  interface Window {
    FLXAN_WORLD?: { w: number; h: number; p: Record<string, string> };
  }
}

function WorldMap({ d }: { d: Report }) {
  const { t, n, locale } = useFmt();
  const items = (d.dims.find((x) => x.kind === 'COUNTRY')?.items ?? []).filter((x) => /^[A-Z]{2}$/.test(x.value));
  const W = typeof window !== 'undefined' ? window.FLXAN_WORLD : undefined;
  const by = new Map(items.map((x) => [x.value, x]));
  const max = Math.max(0, ...items.map((x) => x.visitors));
  const [tip, setTip] = useState<{ x: number; y: number; name: string; v: number } | null>(null);
  const list = items.slice(0, 10);
  const lmax = list[0]?.visitors ?? 0;
  return (
    <Panel title={t('Visitors by country')} wide>
      <div className="flxan-map">
        <div className="flxan-map__svg">
          {W && (
            <svg viewBox={`0 0 ${W.w} ${W.h}`} role="img" aria-label={t('World map')}>
              {Object.entries(W.p).map(([cc, path]) => {
                const x = by.get(cc);
                const lvl = x && max ? Math.max(1, Math.ceil(Math.sqrt(x.visitors / max) * 5)) : 0;
                return (
                  <path
                    key={cc}
                    d={path}
                    className={'l' + lvl}
                    onMouseMove={(e) => {
                      const r = (e.currentTarget.ownerSVGElement?.parentElement as HTMLElement).getBoundingClientRect();
                      let name = x?.label ?? cc;
                      try {
                        if (!x) name = new Intl.DisplayNames([locale], { type: 'region' }).of(cc) ?? cc;
                      } catch {
                        /* keep the code */
                      }
                      setTip({ x: e.clientX - r.left, y: e.clientY - r.top - 8, name, v: x?.visitors ?? 0 });
                    }}
                    onMouseLeave={() => setTip(null)}
                  />
                );
              })}
            </svg>
          )}
          {tip && (
            <div className="flxan-tip" style={{ display: 'block', left: tip.x, top: tip.y }}>
              <b>{tip.name}</b>
              <br />
              {t('Visitors')}: {n(tip.v)}
            </div>
          )}
          <div className="flxan-map__legend">
            <span>{t('Few')}</span>
            {[1, 2, 3, 4, 5].map((l) => (
              <i key={l} className={'l' + l} />
            ))}
            <span>{t('Many')}</span>
          </div>
        </div>
        <div className="flxan-map__list">
          {list.length ? (
            <table className="flxan-table">
              <thead>
                <tr>
                  <th>{t('Country')}</th>
                  <th className="n">{t('Visitors')}</th>
                </tr>
              </thead>
              <tbody>
                {list.map((x) => (
                  <tr key={x.value}>
                    <td className="flxan-lbl">
                      <span className="bar" style={{ width: (lmax ? (x.visitors / lmax) * 100 : 0).toFixed(1) + '%' }} />
                      <span className="txt">
                        <Flag cc={x.value} />
                        {x.label}
                      </span>
                    </td>
                    <td className="n">{n(x.visitors)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          ) : (
            <div className="flxan-empty">{t('No country data in this range.')}</div>
          )}
        </div>
      </div>
    </Panel>
  );
}

function FunnelPanel({ d, filter, onRc }: { d: Report; filter: FlxAnalyticsFilterInput; onRc: (rc: string) => void }) {
  const { t, n, pf } = useFmt();
  const cfg = useConfig();
  const [by, setBy] = useState<FlxAnalyticsFunnelBy>('CHANNEL');
  const [fid, setFid] = useState(0);
  const base = d.funnel;
  const same = by === 'CHANNEL' && !fid;
  const q = useFlxQuery(FlxAnalyticsFunnel, { filter, id: fid || base.id || 0, by }, { enabled: !same, placeholderData: (p) => p });
  const F: Funnel = same ? base : (q.data?.flxAnalyticsFunnel ?? base);
  if (!base.found) return null;
  const first = F.steps[0]?.count ?? 0;
  const byLabel = cfg.funnelBy.find((x) => x.value === by.toLowerCase())?.label ?? '';
  return (
    <Panel
      id="flxan-p-funnel"
      wide
      title={t('Funnel: %s', F.name ?? '')}
      head={
        <>
          {base.funnels.length > 1 && (
            <select className="flxan-fsel" aria-label={t('Funnel')} value={fid || base.id || 0} onChange={(e) => setFid(Number(e.target.value))}>
              {base.funnels.map((f) => (
                <option key={f.id} value={f.id}>
                  {f.name}
                </option>
              ))}
            </select>
          )}
          <Tabs
            value={by}
            onChange={setBy}
            items={cfg.funnelBy.map((o) => ({ key: o.value.toUpperCase() as FlxAnalyticsFunnelBy, label: o.label }))}
          />
        </>
      }
    >
      <div style={{ opacity: q.isFetching && !same ? 0.5 : 1 }}>
        <div className="flxan-funnel" style={{ ['--n' as string]: F.steps.length }}>
          {F.steps.map((st, i) => {
            const pct = first ? (st.count / first) * 100 : 0;
            const h = Math.max(pct, st.count ? 2 : 0).toFixed(1);
            return (
              <div key={i} className="flxan-fstep">
                <div className="flxan-fstep__head">
                  <span className="flxan-fstep__no">{i + 1}</span>
                  <span className="flxan-fstep__label">{st.label}</span>
                </div>
                <div className="flxan-fstep__bar">
                  <span style={{ height: h + '%', ['--w' as string]: h + '%' }} />
                </div>
                <div className="flxan-fstep__val">{n(st.count)}</div>
                <div className="flxan-fstep__rate">
                  {pf(st.rate)}%{i > 0 && st.drop > 0 && <span className="flxan-drop" title={t('Lost since the previous step')}> −{pf(st.drop)}%</span>}
                </div>
              </div>
            );
          })}
        </div>
        {!first && <div className="flxan-empty">{t('No visits entered the funnel in this range.')}</div>}
        {F.groups.length > 0 && (
          <div className="flxan-scroll">
            <table className="flxan-table flxan-ftable">
              <thead>
                <tr>
                  <th>{byLabel}</th>
                  {F.steps.map((st, i) => (
                    <th key={i} className="n" title={st.label}>
                      {i + 1}. {st.label}
                    </th>
                  ))}
                  <th className="n">{t('Completion')}</th>
                </tr>
              </thead>
              <tbody>
                {F.groups.map((g) => (
                  <tr key={g.value} className={F.by === 'RC' && g.value ? 'flxan-row' : ''} tabIndex={F.by === 'RC' && g.value ? 0 : undefined} onClick={F.by === 'RC' && g.value ? () => onRc(g.value) : undefined}>
                    <td className="flxan-lbl">
                      <span className="txt">
                        {F.by === 'COUNTRY' && <Flag cc={g.value} />}
                        {g.label}
                      </span>
                    </td>
                    {g.counts.map((c, i) => (
                      <td key={i} className="n">
                        <span className="flxan-heat" style={{ ['--a' as string]: (0.08 + (g.counts[0] ? c / g.counts[0]! : 0) * 0.6).toFixed(2) }}>
                          {n(c)}
                        </span>
                      </td>
                    ))}
                    <td className="n">
                      <b>{pf(g.rate)}%</b>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
        <p className="flxan-note">
          {t('Each visitor counts at the furthest step they reached that day. You can change the steps under Settings › Funnels.')}
          {cfg.funnelSettingsUrl && (
            <>
              {' '}
              <a href={cfg.funnelSettingsUrl}>{t('Funnel settings')}</a>
            </>
          )}
        </p>
      </div>
    </Panel>
  );
}

function RefsPanel({ refs, onRc }: { refs: RefRow[]; onRc: (rc: string) => void }) {
  const { t, n, pf, dur, money } = useFmt();
  const cfg = useConfig();
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const [form, setForm] = useState(false);
  const [name, setName] = useState('');
  const [code, setCode] = useState('');
  const [touched, setTouched] = useState(false);
  const [out, setOut] = useState<{ url?: string; error?: string }>({});
  const [rename, setRename] = useState<RefRow | null>(null);
  const [del, setDel] = useState<RefRow | null>(null);
  const save = useFlxMutation(FlxAnalyticsRefSave, {
    onSuccess: (r) => {
      const p = r.flxAnalyticsRefSave;
      if (p?.errors.length) return setOut({ error: p.errors[0]?.message });
      if (p?.ref) setOut({ url: p.ref.url });
      void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxAnalyticsReport) });
      void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxAnalyticsFunnel) });
    },
    onError: (e) => setOut({ error: e.message }),
  });
  const submit = (e: FormEvent) => {
    e.preventDefault();
    const c = slug(code || name);
    if (!c) return;
    save.mutate({ input: { code: c, name: name.trim() } });
  };
  const max = Math.max(0, ...refs.map((x) => x.visitors));
  return (
    <Panel
      id="flxan-p-codes"
      wide
      title={t('Referral links')}
      head={
        cfg.canManageRefs ? (
          <button type="button" className="flxan-btn flxan-btn--primary" onClick={() => setForm(!form)}>
            + {t('New link')}
          </button>
        ) : undefined
      }
    >
      {form && (
        <form className="flxan-refform" onSubmit={submit}>
          <input
            type="text"
            className="flxan-in"
            maxLength={80}
            placeholder={t('Name (e.g. Ayşe\'s Instagram)')}
            aria-label={t('Name')}
            value={name}
            onChange={(e) => {
              setName(e.target.value);
              if (!touched) setCode(slug(e.target.value));
            }}
            autoFocus
          />
          <input type="text" className="flxan-in" maxLength={32} placeholder={t('Code (e.g. ayse-ig)')} aria-label={t('Code')} value={code} onChange={(e) => (setTouched(true), setCode(e.target.value))} />
          <button type="submit" className="flxan-btn flxan-btn--primary" disabled={save.isPending}>
            {t('Save')}
          </button>
          <span className="flxan-refout">
            {out.error}
            {out.url && (
              <>
                <code>{out.url}</code> <CopyButton text={out.url} />
              </>
            )}
          </span>
        </form>
      )}
      {!refs.length ? (
        <div className="flxan-empty">{t('No referral links yet. Add ?r=code to the end of the address wherever you share the site (e.g. %s); give it a name here and reports show that name.', cfg.homeUrl.replace(/\/$/, '') + '/?r=ayse-ig')}</div>
      ) : (
        <div className="flxan-scroll">
          <table className="flxan-table flxan-reftable">
            <thead>
              <tr>
                <th>{t('Link')}</th>
                <th className="n">{t('Visitors')}</th>
                <th className="n n2">{t('Visits')}</th>
                <th className="n n2">{t('Pages / visit')}</th>
                <th className="n n2">{t('Avg. time')}</th>
                <th className="n">{t('Furthest step')}</th>
                <th className="n">{t('Donations')}</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {refs.map((x) => (
                <tr key={x.code}>
                  <td className="flxan-lbl">
                    <span className="bar" style={{ width: (max ? (x.visitors / max) * 100 : 0).toFixed(1) + '%' }} />
                    <span className="txt">
                      <a
                        href="#"
                        className="flxan-rcgo"
                        title={t('Show what the visitors of this link did')}
                        onClick={(e) => {
                          e.preventDefault();
                          onRc(x.code);
                        }}
                      >
                        {x.label}
                      </a>
                      <small>?r={x.code}</small>
                    </span>
                  </td>
                  <td className="n">{n(x.visitors)}</td>
                  <td className="n n2">{n(x.sessions)}</td>
                  <td className="n n2">{pf(x.pagesPer)}</td>
                  <td className="n n2">{dur(x.avgDuration)}</td>
                  <td className="n">{x.deepest ? <span className="flxan-step" title={x.deepestLabel}>{x.deepest}. {x.deepestLabel}</span> : '—'}</td>
                  <td className="n">{x.donations ? n(x.donations) + ' · ' + money(x.amount) : '—'}</td>
                  <td className="n flxan-acts">
                    <CopyButton text={x.url} label="⧉" className="flxan-ico flxan-copy" />
                    {cfg.canManageRefs && (
                      <>
                        <button type="button" className="flxan-ico flxan-edit" title={t('Rename')} aria-label={t('Rename')} onClick={() => setRename(x)}>
                          ✎
                        </button>
                        {x.name && (
                          <button type="button" className="flxan-ico flxan-del" title={t('Remove name')} aria-label={t('Remove name')} onClick={() => setDel(x)}>
                            ×
                          </button>
                        )}
                      </>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      <p className="flxan-note">{t('A person who arrives through the link gets a 30-day cookie; their later pages, later days and donation count for that code. Click a row to filter the whole screen to people who came through that link.')}</p>
      {rename && <PromptDialog title={t('Name for "%s"', rename.code)} value={rename.name} onDone={(v) => save.mutate({ input: { code: rename.code, name: v.trim() } })} onClose={() => setRename(null)} />}
      {del && <ConfirmDialog danger text={t('Remove the name? Statistics stay and show the code.')} action={t('Remove name')} onYes={() => save.mutate({ input: { code: del.code, delete: true } })} onClose={() => setDel(null)} />}
    </Panel>
  );
}

function DimTabs({ id, title, kinds, d, cur, setCur, more }: { id: string; title: string; kinds: string[]; d: Report; cur: DimKind; setCur: (k: DimKind) => void; more: (k: DimKind, title: string) => void }) {
  const { t, n } = useFmt();
  const cfg = useConfig();
  const avail = kinds.filter((k) => k !== 'country' || (d.dims.find((x) => x.kind === 'COUNTRY')?.items.length ?? 0) > 0);
  const label = (k: string) => cfg.kinds.find((o) => o.value === k)?.label ?? k;
  const active = avail.includes(Object.keys(KIND_KEY).find((k) => KIND_KEY[k] === cur) ?? '') ? cur : KIND_KEY[avail[0]!]!;
  const dim = d.dims.find((x) => x.kind === active);
  const key = Object.keys(KIND_KEY).find((k) => KIND_KEY[k] === active) ?? '';
  return (
    <Panel
      id={'flxan-p-' + id}
      title={title}
      head={<Tabs value={key} onChange={(k) => setCur(KIND_KEY[k]!)} items={avail.map((k) => ({ key: k, label: label(k) }))} />}
    >
      <BarTable rows={dim?.items ?? []} title={label(key)} label={(r) => r.label} sort={(r) => r.pageviews} cols={[{ head: t('Visitors'), cell: (r) => n(r.visitors) }, { head: t('Page views'), cell: (r) => n(r.pageviews), second: true }]} />
      <More total={dim?.total ?? 0} shown={dim?.items.length ?? 0} onClick={() => more(active, label(key))} />
    </Panel>
  );
}

function EventsPanel({ d, filter, name, setName, more }: { d: Report; filter: FlxAnalyticsFilterInput; name: string; setName: (n: string) => void; more: (name: string) => void }) {
  const { t, n, money } = useFmt();
  const q = useFlxQuery(FlxAnalyticsEvents, { filter, name, first: 10 }, { enabled: !!name, placeholderData: (p) => p });
  const data = name ? q.data?.flxAnalyticsEvents : undefined;
  const rows = name ? (data?.nodes ?? []) : d.events.items;
  const total = name ? (data?.totalCount ?? 0) : d.events.total;
  if (!d.eventSummary.length) {
    return (
      <Panel title={t('Clicks and conversions')} wide>
        <div className="flxan-empty">{t('No events in this range. Outbound links, downloads, phone/WhatsApp clicks, searches and donations show up here.')}</div>
      </Panel>
    );
  }
  const donation = name === 'donation';
  return (
    <Panel title={t('Clicks and conversions')} wide>
      <div className="flxan-chips">
        <button type="button" className="flxan-chip" aria-pressed={!name} onClick={() => setName('')}>
          {t('All')}
        </button>
        {d.eventSummary.map((e) => (
          <button key={e.name} type="button" className="flxan-chip" aria-pressed={name === e.name} onClick={() => setName(e.name)}>
            {e.label}
            <b>{n(e.hits)}</b>
            {e.amount ? ' · ' + money(e.amount) : ''}
          </button>
        ))}
      </div>
      <div className="flxan-evt">
        <BarTable
          rows={rows}
          title={t('Event')}
          label={(r) => (name ? '' : r.label + ' · ') + (r.param || '—')}
          sort={(r) => r.hits}
          cols={donation ? [{ head: t('Count'), cell: (r) => n(r.hits) }, { head: t('Amount'), cell: (r) => money(r.amount), second: true }] : [{ head: t('Count'), cell: (r) => n(r.hits) }, { head: t('Unique'), cell: (r) => n(r.uniques), second: true }]}
        />
      </div>
      <More total={total} shown={rows.length} onClick={() => more(name)} />
    </Panel>
  );
}

/* ----------------------------------------------------------------- modals */

type ListCfg = { kind: 'pages' | 'referrers' | 'notfound' | 'dim' | 'events'; title: string; dim?: DimKind; name?: string };

/** "See all": a searchable list loaded 50 at a time. */
function ListModal({ cfg, filter, onClose, onPage }: { cfg: ListCfg; filter: FlxAnalyticsFilterInput; onClose: () => void; onPage: (r: PageRow) => void }) {
  const { t, n, money } = useFmt();
  const { client } = useGraphQL();
  const [q, setQ] = useState('');
  const dq = useDebounced(q.trim(), 350);
  type Row = Record<string, unknown>;
  const [state, setState] = useState<{ items: Row[]; total: number; after: string | null; more: boolean; loading: boolean; error: boolean }>({ items: [], total: 0, after: null, more: false, loading: true, error: false });
  const seq = useRef(0);
  const load = (after: string | null, reset: boolean) => {
    const my = ++seq.current;
    setState((s) => ({ ...s, loading: true, error: false, ...(reset ? { items: [] } : {}) }));
    const vars = { filter, first: 50, after };
    const run = async (): Promise<{ nodes: Row[]; totalCount: number | null; pageInfo: { hasNextPage: boolean; endCursor: string | null } }> => {
      if (cfg.kind === 'referrers') return (await client.request(FlxAnalyticsReferrers, { ...vars, q: dq })).flxAnalyticsReferrers;
      if (cfg.kind === 'dim') return (await client.request(FlxAnalyticsBreakdown, { ...vars, kind: cfg.dim! })).flxAnalyticsBreakdown;
      if (cfg.kind === 'events') return (await client.request(FlxAnalyticsEvents, { ...vars, name: cfg.name || '' })).flxAnalyticsEvents;
      return (await client.request(FlxAnalyticsPages, { ...vars, q: dq, only404: cfg.kind === 'notfound' })).flxAnalyticsPages;
    };
    run()
      .then((r) => {
        if (my !== seq.current) return;
        setState((s) => ({ items: reset ? r.nodes : [...s.items, ...r.nodes], total: r.totalCount ?? 0, after: r.pageInfo.endCursor, more: r.pageInfo.hasNextPage, loading: false, error: false }));
      })
      .catch(() => my === seq.current && setState((s) => ({ ...s, loading: false, error: true })));
  };
  useEffect(() => load(null, true), [dq]); // eslint-disable-line react-hooks/exhaustive-deps
  const searchable = cfg.kind === 'pages' || cfg.kind === 'referrers' || cfg.kind === 'notfound';
  const cols2 = [{ head: t('Visitors'), cell: (r: Row) => n(r.visitors as number) }, { head: t('Page views'), cell: (r: Row) => n(r.pageviews as number), second: true }];
  return (
    <Modal title={cfg.title} onClose={onClose} wide>
      <div className="flxan flxan-react flxan-listmodal">
        {searchable && <input type="search" className="flxan-search" style={{ width: '100%', marginBottom: 8 }} placeholder={t('Search')} value={q} onChange={(e) => setQ(e.target.value)} autoFocus />}
        {state.error ? (
          <div className="flxan-empty">{t('Could not load. Try again in a moment.')}</div>
        ) : state.loading && !state.items.length ? (
          <SkRows rows={6} />
        ) : cfg.kind === 'referrers' ? (
          <BarTable rows={state.items} title={t('Site')} label={(r) => <>{r.host as string}<small>{r.channelLabel as string}</small></>} sort={(r) => r.visitors as number} onRow={(r) => window.open('https://' + (r.host as string), '_blank', 'noopener')} cols={cols2} />
        ) : cfg.kind === 'dim' ? (
          <BarTable rows={state.items} title={cfg.title} label={(r) => r.label as string} sort={(r) => r.pageviews as number} cols={cols2} />
        ) : cfg.kind === 'events' ? (
          <BarTable
            rows={state.items}
            title={t('Event')}
            label={(r) => (cfg.name ? '' : (r.label as string) + ' · ') + ((r.param as string) || '—')}
            sort={(r) => r.hits as number}
            cols={cfg.name === 'donation' ? [{ head: t('Count'), cell: (r) => n(r.hits as number) }, { head: t('Amount'), cell: (r) => money(r.amount as number), second: true }] : [{ head: t('Count'), cell: (r) => n(r.hits as number) }, { head: t('Unique'), cell: (r) => n(r.uniques as number), second: true }]}
          />
        ) : (
          <BarTable rows={state.items} title={cfg.kind === 'notfound' ? t('Address') : t('Pages')} label={(r) => (cfg.kind === 'notfound' ? (r.path as string) : <PageLabel r={r as unknown as PageRow} />)} sort={(r) => r.pageviews as number} onRow={cfg.kind === 'pages' ? (r) => onPage(r as unknown as PageRow) : undefined} cols={cols2} />
        )}
        {state.more && (
          <div className="flxan-more">
            <button type="button" className="flxan-btn" aria-disabled={state.loading} onClick={() => !state.loading && load(state.after, false)}>
              {t('Load more')}
            </button>
          </div>
        )}
      </div>
    </Modal>
  );
}

function PageModal({ target, title, filter, range, group, onClose }: { target: string; title: string; filter: FlxAnalyticsFilterInput; range?: Report['range']; group?: 'DAY' | 'WEEK' | 'MONTH'; onClose: () => void }) {
  const { t, n } = useFmt();
  const cfg = useConfig();
  const q = useFlxQuery(FlxAnalyticsPage, { filter, target });
  const r = q.data?.flxAnalyticsPage;
  const items = r ? (range && (group ?? 'DAY') === 'DAY' ? fillDays(r.series, range.start, range.end) : r.series) : [];
  return (
    <Modal title={title} onClose={onClose} wide>
      <div className="flxan flxan-react">
        {q.isError ? (
          <div className="flxan-empty">{t('Could not load. Try again in a moment.')}</div>
        ) : !r ? (
          <SkRows rows={5} />
        ) : (
          <>
            <div className="flxan-cards" style={{ gridTemplateColumns: 'repeat(2,minmax(0,1fr))' }}>
              <div className="flxan-card">
                <div className="flxan-card__label">{t('Visitors')}</div>
                <div className="flxan-card__value">{n(r.visitors)}</div>
              </div>
              <div className="flxan-card">
                <div className="flxan-card__label">{t('Page views')}</div>
                <div className="flxan-card__value">{n(r.pageviews)}</div>
              </div>
            </div>
            <BarChart items={items} group="DAY" />
            <p className="flxan-note">
              {r.paths.map((p, i) => (
                <span key={p}>
                  {i > 0 && ' · '}
                  <a href={cfg.homeUrl.replace(/\/$/, '') + p} target="_blank" rel="noopener noreferrer">
                    {p}
                  </a>
                </span>
              ))}
            </p>
          </>
        )}
      </div>
    </Modal>
  );
}

