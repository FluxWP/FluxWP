// Flux Analytics screens: shared pieces (formatting, filters in the address bar, cards, tables, charts, dialogs).
// Every screen reads and writes through the Flux Analytics GraphQL operations (plugins/flx-analytics/graphql/operations).
import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState, type ComponentType, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { createGraphQLClient } from '@fluxwp/graphql';
import { FlxGraphQLProvider, useFlxQuery } from '@fluxwp/graphql/react';
import { FlxAnalyticsConfig } from '@fluxwp/graphql/operations';
import type { FlxAnalyticsConfigQuery, FlxAnalyticsFilterInput, FlxAnalyticsRangePreset } from '@fluxwp/graphql/types';
import { DatePicker, Loading, Modal, Screen, Tabs as UiTabs } from '@fluxwp/ui';
import { Chart } from '@fluxwp/ui/chart';
import { useHost } from '../host';
import { translator, type Translate } from './i18n';
import './analytics.css';

export type Config = FlxAnalyticsConfigQuery['flxAnalyticsConfig'];

/* ---------------------------------------------------------------- formatting */

export interface Fmt {
  t: Translate;
  n: (x: number | null | undefined) => string;
  pf: (x: number | null | undefined) => string;
  money: (x: number | null | undefined) => string;
  compact: (x: number) => string;
  day: (s: string, opt?: Intl.DateTimeFormatOptions) => string;
  dur: (sec: number) => string;
  locale: string;
}

export function useFmt(): Fmt {
  const { i18n } = useTranslation();
  return useMemo(() => {
    const locale = (i18n.language || 'en').replace('_', '-');
    const { t } = translator(i18n.language);
    const nf = new Intl.NumberFormat(locale);
    const pf = new Intl.NumberFormat(locale, { maximumFractionDigits: 1 });
    const mf = new Intl.NumberFormat(locale, { maximumFractionDigits: 0 });
    return {
      t,
      locale,
      n: (x) => nf.format(x || 0),
      pf: (x) => pf.format(x || 0),
      money: (x) => mf.format(x || 0),
      compact: (x) => new Intl.NumberFormat(locale, { notation: 'compact' }).format(x),
      day: (s, opt) => {
        const p = s.split('-').map(Number);
        return new Date(p[0] ?? 1970, (p[1] ?? 1) - 1, p[2] ?? 1).toLocaleDateString(locale, opt ?? { day: 'numeric', month: 'short' });
      },
      dur: (sec) => {
        const s = Math.round(sec || 0);
        return s < 60 ? t('%s sec', s) : t('%1$s min %2$s sec', Math.floor(s / 60), String(s % 60).padStart(2, '0'));
      },
    };
  }, [i18n.language]);
}

/* ------------------------------------------------------------------ provider */

const ConfigCtx = createContext<Config | null>(null);
export const useConfig = (): Config => {
  const c = useContext(ConfigCtx);
  if (!c) throw new Error('Analytics config missing');
  return c;
};

/** Wraps a screen: GraphQL provider, then the option lists and permissions every screen needs once. */
export function withAnalytics(Screen: ComponentType): ComponentType {
  function Bound() {
    const host = useHost();
    const client = useMemo(() => createGraphQLClient({ api: host.client }), [host.client]);
    return (
      <FlxGraphQLProvider client={client} scope={host.scope}>
        <ConfigGate />
      </FlxGraphQLProvider>
    );
  }
  function ConfigGate() {
    const { t } = useFmt();
    const q = useFlxQuery(FlxAnalyticsConfig, {}, { staleTime: 5 * 60_000 });
    if (q.isLoading) return <Loading />;
    if (q.isError || !q.data) return <div className="flxan flxan-react"><div className="flxan-banner">{q.error?.message || t('Could not load. Try again in a moment.')}</div></div>;
    return (
      <ConfigCtx.Provider value={q.data.flxAnalyticsConfig}>
        <Screen />
      </ConfigCtx.Provider>
    );
  }
  return Bound;
}

/* ------------------------------------------------------------- range and filters */

const KEY_TO_ENUM: Record<string, FlxAnalyticsRangePreset> = {
  today: 'TODAY',
  yesterday: 'YESTERDAY',
  '7d': 'DAYS_7',
  '30d': 'DAYS_30',
  '90d': 'DAYS_90',
  this_month: 'THIS_MONTH',
  last_month: 'LAST_MONTH',
  '12m': 'MONTHS_12',
  this_year: 'THIS_YEAR',
  last_year: 'LAST_YEAR',
  all: 'ALL',
  custom: 'CUSTOM',
};

export interface Filters {
  range: string;
  start: string;
  end: string;
  seg: string;
  rc: string;
}

const read = (): URLSearchParams => new URLSearchParams(window.location.hash.slice(1));

/** Range and visitor filters live in the address bar (#range=30d&seg=guest). */
export function useFilters(extra: string[] = []): { f: Filters; set: (p: Partial<Filters>) => void; param: (k: string) => string | null; setParam: (k: string, v: string | null) => void } {
  const [h, setH] = useState(() => read());
  const f: Filters = {
    range: KEY_TO_ENUM[h.get('range') ?? ''] ? (h.get('range') as string) : '30d',
    start: h.get('start') ?? '',
    end: h.get('end') ?? '',
    seg: h.get('seg') ?? 'all',
    rc: h.get('rc') ?? '',
  };
  const write = useCallback((next: URLSearchParams) => {
    setH(next);
    try {
      window.history.replaceState(null, '', '#' + next.toString());
    } catch {
      /* the hash is only a convenience */
    }
  }, []);
  const set = (p: Partial<Filters>) => {
    const n = new URLSearchParams(h);
    const m = { ...f, ...p };
    n.set('range', m.range);
    if (m.range === 'custom') {
      n.set('start', m.start);
      n.set('end', m.end);
    } else {
      n.delete('start');
      n.delete('end');
    }
    if (m.seg !== 'all') n.set('seg', m.seg);
    else n.delete('seg');
    if (m.rc) n.set('rc', m.rc);
    else n.delete('rc');
    write(n);
  };
  const param = (k: string) => (extra.includes(k) ? h.get(k) : null);
  const setParam = (k: string, v: string | null) => {
    const n = new URLSearchParams(h);
    if (v === null || v === '') n.delete(k);
    else n.set(k, v);
    write(n);
  };
  return { f, set, param, setParam };
}

export function filterInput(f: Filters): FlxAnalyticsFilterInput {
  const out: FlxAnalyticsFilterInput = { range: KEY_TO_ENUM[f.range] ?? 'DAYS_30' };
  if (f.range === 'custom') {
    out.start = f.start;
    out.end = f.end;
  }
  if (f.seg !== 'all') out.seg = f.seg;
  if (f.rc) out.rc = f.rc;
  return out;
}

export const presetKey = (e: FlxAnalyticsRangePreset): string => Object.entries(KEY_TO_ENUM).find(([, v]) => v === e)?.[0] ?? '30d';

/** Date range, visitor type and referral filters (the same bar on every report screen). */
export function FilterBar({ f, set, refs, extra }: { f: Filters; set: (p: Partial<Filters>) => void; refs?: { code: string; label: string }[]; extra?: ReactNode }) {
  const { t } = useFmt();
  const cfg = useConfig();
  const [dates, setDates] = useState<[string, string]>([f.start, f.end]);
  useEffect(() => setDates([f.start, f.end]), [f.start, f.end]);
  const segs = cfg.segments.some((s) => s.value === f.seg) ? cfg.segments : [...cfg.segments, { value: f.seg, label: f.seg }];
  const known = refs ?? [];
  return (
    <div className="flxan-tools">
      <select aria-label={t('Date range')} value={f.range} onChange={(e) => set({ range: e.target.value })}>
        {cfg.presets.map((p) => (
          <option key={p.value} value={p.value}>
            {p.label}
          </option>
        ))}
      </select>
      <span className={'flxan-custom' + (f.range === 'custom' ? ' on' : '')}>
        <DatePicker aria-label={t('Start')} value={dates[0]} onChange={(v) => setDates([v, dates[1]])} />
        <span>–</span>
        <DatePicker aria-label={t('End')} value={dates[1]} onChange={(v) => setDates([dates[0], v])} />
        <button type="button" className="flxan-btn" onClick={() => dates[0] && dates[1] && set({ start: dates[0], end: dates[1] })}>
          {t('Apply')}
        </button>
      </span>
      <select aria-label={t('Visitor type')} className={f.seg !== 'all' ? 'on' : ''} value={f.seg} onChange={(e) => set({ seg: e.target.value })}>
        {segs.map((s) => (
          <option key={s.value} value={s.value}>
            {s.label}
          </option>
        ))}
      </select>
      <select aria-label={t('Referral')} className={f.rc ? 'on' : ''} value={f.rc} onChange={(e) => set({ rc: e.target.value })}>
        <option value="">{t('All sources')}</option>
        <option value="*">{t('Referral links only')}</option>
        {known.map((r) => (
          <option key={r.code} value={r.code}>
            {r.label + (r.label !== r.code ? ' (' + r.code + ')' : '')}
          </option>
        ))}
        {f.rc && f.rc !== '*' && !known.some((r) => r.code === f.rc) && <option value={f.rc}>{f.rc}</option>}
      </select>
      {extra}
    </div>
  );
}

/* ----------------------------------------------------------------- small parts */

export const ICONS: Record<string, string> = {
  users: '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
  eye: '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
  door: '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/>',
  layers: '<path d="M12 2 2 7l10 5 10-5-10-5z"/><path d="m2 17 10 5 10-5"/><path d="m2 12 10 5 10-5"/>',
  clock: '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
  bounce: '<path d="M9 14 4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 0 11H11"/>',
  route: '<circle cx="6" cy="19" r="3"/><path d="M9 19h8.5a3.5 3.5 0 0 0 0-7h-11a3.5 3.5 0 0 1 0-7H15"/><circle cx="18" cy="5" r="3"/>',
  enter: '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/>',
  exit: '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
  target: '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>',
  page: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>',
  bolt: '<path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/>',
};

export function Icon({ name, size = 18 }: { name: string; size?: number }) {
  return <svg viewBox="0 0 24 24" width={size} height={size} fill="none" stroke="currentColor" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" dangerouslySetInnerHTML={{ __html: ICONS[name] ?? '' }} />;
}

export function Delta({ ch, invert, show = true }: { ch: number | null | undefined; invert?: boolean; show?: boolean }) {
  const { t, pf } = useFmt();
  if (!show || ch === null || ch === undefined) return null;
  const good = invert ? ch < 0 : ch > 0;
  const bad = invert ? ch > 0 : ch < 0;
  return (
    <span className={'flxan-delta ' + (good ? 'up' : bad ? 'down' : '')} title={t('Against the previous equal period')}>
      {ch > 0 ? '↑' : ch < 0 ? '↓' : ''}
      {pf(Math.abs(ch))}%
    </span>
  );
}

export function Kpi({ label, value, ch, sub, tone, icon, invert, text, showDelta = true }: { label: string; value: string; ch?: number | null; sub?: string; tone: string; icon: string; invert?: boolean; text?: boolean; showDelta?: boolean }) {
  return (
    <div className={'flxan-card flxan-kpi flxan-kpi--' + tone + (text ? ' flxan-kpi--text' : '')}>
      <div className="flxan-kpi__top">
        <span className="flxan-kpi__ico">
          <Icon name={icon} />
        </span>
        <span className="flxan-card__label">{label}</span>
      </div>
      <div className="flxan-kpi__row">
        <div className="flxan-card__value" title={text ? value : undefined}>
          {value}
        </div>
        <Delta ch={ch} invert={invert} show={showDelta} />
      </div>
      <div className="flxan-card__sub" title={sub}>
        {sub}
      </div>
    </div>
  );
}

export function KpiSkeleton({ tones }: { tones: [string, string][] }) {
  return (
    <>
      {tones.map(([tone, icon]) => (
        <div key={tone} className={'flxan-card flxan-kpi flxan-kpi--' + tone + ' flxan-kpi--sk'}>
          <div className="flxan-kpi__top">
            <span className="flxan-kpi__ico">
              <Icon name={icon} />
            </span>
            <i className="flxan-sk" style={{ width: '55%' }} />
          </div>
          <i className="flxan-sk flxan-sk--val" />
          <i className="flxan-sk flxan-sk--sub" />
        </div>
      ))}
    </>
  );
}

export function SkRows({ rows }: { rows: number }) {
  return (
    <div className="flxan-skrows">
      {Array.from({ length: rows }, (_, i) => (
        <div key={i} className="flxan-skrow">
          <i className="flxan-sk" style={{ width: 78 - ((i * 9) % 40) + '%' }} />
          <i className="flxan-sk flxan-sk--n" />
        </div>
      ))}
    </div>
  );
}

export function Panel({ title, head, wide, children, id }: { title: ReactNode; head?: ReactNode; wide?: boolean; children: ReactNode; id?: string }) {
  return (
    <div className={'flxan-panel' + (wide ? ' flxan-wide' : '')} id={id}>
      <div className="flxan-panel__head">
        <h2>{title}</h2>
        {head}
      </div>
      <div className="flxan-body">{children}</div>
    </div>
  );
}

export function Tabs<K extends string>({ items, value, onChange }: { items: { key: K; label: string }[]; value: K; onChange: (k: K) => void }) {
  return <UiTabs size="sm" items={items.map((i) => ({ id: i.key, label: i.label }))} current={value} onChange={onChange} />;
}

export interface Col<R> {
  head: string;
  cell: (r: R) => ReactNode;
  second?: boolean;
}

/** Table with the label cell drawn as a bar scaled to `sort` (the report tables). */
export function BarTable<R>({ rows, title, label, cols, sort, onRow, empty }: { rows: R[]; title: string; label: (r: R) => ReactNode; cols: Col<R>[]; sort: (r: R) => number; onRow?: (r: R) => void; empty?: string }) {
  const { t } = useFmt();
  if (!rows.length) return <div className="flxan-empty">{empty ?? t('No data in this range.')}</div>;
  const max = Math.max(0, ...rows.map(sort));
  return (
    <table className="flxan-table">
      <thead>
        <tr>
          <th>{title}</th>
          {cols.map((c, i) => (
            <th key={i} className={'n' + (c.second ? ' n2' : '')}>
              {c.head}
            </th>
          ))}
        </tr>
      </thead>
      <tbody>
        {rows.map((r, i) => (
          <tr
            key={i}
            className={onRow ? 'flxan-row' : ''}
            tabIndex={onRow ? 0 : undefined}
            onClick={onRow ? () => onRow(r) : undefined}
            onKeyDown={onRow ? (e) => (e.key === 'Enter' || e.key === ' ') && (e.preventDefault(), onRow(r)) : undefined}
          >
            <td className="flxan-lbl">
              <span className="bar" style={{ width: (max ? (sort(r) / max) * 100 : 0).toFixed(1) + '%' }} />
              <span className="txt">{label(r)}</span>
            </td>
            {cols.map((c, j) => (
              <td key={j} className={'n' + (c.second ? ' n2' : '')}>
                {c.cell(r)}
              </td>
            ))}
          </tr>
        ))}
      </tbody>
    </table>
  );
}

export function Flag({ cc }: { cc: string }) {
  // No emoji flags: WordPress turns them into broken images and Windows has none; a code chip instead.
  return /^[A-Z]{2}$/.test(cc) ? <span className="flxan-cc">{cc}</span> : null;
}

/** Visitors (bars) and page views (area) per day/week/month: shared interactive chart. */
export function BarChart({ items, group, height = 260 }: { items: { date: string; visitors: number; pageviews: number }[]; group: 'DAY' | 'WEEK' | 'MONTH'; height?: number }) {
  const { t, n, day, compact } = useFmt();
  const label = (d: string, short: boolean) => {
    if (group === 'MONTH') return day(d, { month: 'short', year: short ? '2-digit' : 'numeric' });
    if (group === 'WEEK') return short ? day(d) : t('Week of %s', day(d, { day: 'numeric', month: 'long' }));
    return short ? day(d) : day(d, { weekday: 'short', day: 'numeric', month: 'long', year: 'numeric' });
  };
  return (
    <Chart
      kind="bar"
      height={height}
      labels={items.map((i) => i.date)}
      formatLabel={(d) => label(d, false)}
      tickLabel={(d) => label(d, true)}
      series={[
        { label: t('Visitors'), data: items.map((i) => i.visitors), color: 'var(--flxan-accent)' },
        { label: t('Views'), data: items.map((i) => i.pageviews), kind: 'area', color: 'var(--flxan-accent-2)' },
      ]}
      format={(v) => n(v)}
      formatTick={(v) => compact(v)}
      label={t('Visitors and page views chart')}
      legend={false}
      points={false}
    />
  );
}

/** Fills the days without views with zeros (a page's series only has the days it was viewed). */
export function fillDays(series: { date: string; visitors: number; pageviews: number }[], start: string, end: string) {
  const by = new Map(series.map((x) => [x.date, x]));
  const out: { date: string; visitors: number; pageviews: number }[] = [];
  const s = start.split('-').map(Number);
  const e = end.split('-').map(Number);
  const cur = new Date(s[0] ?? 1970, (s[1] ?? 1) - 1, s[2] ?? 1);
  const last = new Date(e[0] ?? 1970, (e[1] ?? 1) - 1, e[2] ?? 1);
  while (cur <= last) {
    const k = `${cur.getFullYear()}-${String(cur.getMonth() + 1).padStart(2, '0')}-${String(cur.getDate()).padStart(2, '0')}`;
    out.push(by.get(k) ?? { date: k, visitors: 0, pageviews: 0 });
    cur.setDate(cur.getDate() + 1);
  }
  return out;
}

/* ---------------------------------------------------------------------- dialogs */

/** Yes/no dialog (no browser confirm). */
export function ConfirmDialog({ text, action, danger, onYes, onClose }: { text: string; action: string; danger?: boolean; onYes: () => void; onClose: () => void }) {
  const { t } = useFmt();
  return (
    <Modal title={action} onClose={onClose}>
      <div className="flxan flxan-react flxan-dlg">
        <p>{text}</p>
        <div className="flxan-dlg__acts">
          <button type="button" className="flxan-btn" onClick={onClose}>
            {t('Cancel')}
          </button>
          <button
            type="button"
            className={'flxan-btn ' + (danger ? 'flxan-btn--danger' : 'flxan-btn--primary')}
            onClick={() => {
              onYes();
              onClose();
            }}
          >
            {action}
          </button>
        </div>
      </div>
    </Modal>
  );
}

/** Text prompt dialog (no browser prompt). */
export function PromptDialog({ title, value, label, onDone, onClose }: { title: string; value?: string; label?: string; onDone: (v: string) => void; onClose: () => void }) {
  const { t } = useFmt();
  const [v, setV] = useState(value ?? '');
  return (
    <Modal title={title} onClose={onClose}>
      <form
        className="flxan flxan-react flxan-dlg"
        onSubmit={(e) => {
          e.preventDefault();
          onDone(v);
          onClose();
        }}
      >
        <input className="flxan-in" autoFocus aria-label={label ?? title} value={v} onChange={(e) => setV(e.target.value)} style={{ width: '100%' }} />
        <div className="flxan-dlg__acts">
          <button type="button" className="flxan-btn" onClick={onClose}>
            {t('Cancel')}
          </button>
          <button type="submit" className="flxan-btn flxan-btn--primary">
            {t('OK')}
          </button>
        </div>
      </form>
    </Modal>
  );
}

export async function copyText(text: string): Promise<boolean> {
  try {
    if (navigator.clipboard && window.isSecureContext) {
      await navigator.clipboard.writeText(text);
      return true;
    }
  } catch {
    /* fall through */
  }
  return false;
}

/** Small button that copies text and says "Copied" for a moment. */
export function CopyButton({ text, label, className }: { text: string; label?: string; className?: string }) {
  const { t } = useFmt();
  const [done, setDone] = useState(false);
  const [ask, setAsk] = useState(false);
  return (
    <>
      <button
        type="button"
        className={className ?? 'flxan-btn'}
        title={label}
        onClick={async () => {
          if (await copyText(text)) {
            setDone(true);
            setTimeout(() => setDone(false), 1500);
          } else setAsk(true);
        }}
      >
        {done ? t('Copied') : (label ?? t('Copy'))}
      </button>
      {ask && <PromptDialog title={t('Link')} value={text} onDone={() => undefined} onClose={() => setAsk(false)} />}
    </>
  );
}

export function useDebounced<T>(value: T, ms: number): T {
  const [v, setV] = useState(value);
  useEffect(() => {
    const id = setTimeout(() => setV(value), ms);
    return () => clearTimeout(id);
  }, [value, ms]);
  return v;
}

export function Shell({ title, head, children, notice, busy }: { title: string; head?: ReactNode; children: ReactNode; notice?: ReactNode; busy?: boolean }) {
  const host = useHost();
  return (
    <div className="flxan flxan-react" aria-busy={busy ? 'true' : 'false'}>
      <Screen className="flxan-app" width="wide" icon={host.appIcon} title={title} actions={head} notice={notice}>
        {children}
      </Screen>
    </div>
  );
}
