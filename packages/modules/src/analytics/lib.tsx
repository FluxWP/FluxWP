// Flux Analytics screens: shared pieces (formatting, filters in the address bar, cards, tables, charts, dialogs).
// Every screen reads and writes through the Flux Analytics GraphQL operations (plugins/flx-analytics/graphql/operations).
import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState, type ComponentType, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { createGraphQLClient } from '@fluxwp/graphql';
import { FlxGraphQLProvider, useFlxQuery } from '@fluxwp/graphql/react';
import { FlxAnalyticsConfig } from '@fluxwp/graphql/operations';
import type { FlxAnalyticsConfigQuery, FlxAnalyticsFilterInput, FlxAnalyticsRangePreset } from '@fluxwp/graphql/types';
import { DatePicker, Loading, Modal } from '@fluxwp/ui';
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
  return (
    <div className="flxan-tabs" role="tablist">
      {items.map((i) => (
        <button key={i.key} type="button" role="tab" aria-selected={value === i.key} onClick={() => onChange(i.key)}>
          {i.label}
        </button>
      ))}
    </div>
  );
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

/** Bar chart of visitors (front) over page views (behind) with a hover tip. */
export function BarChart({ items, group }: { items: { date: string; visitors: number; pageviews: number }[]; group: 'DAY' | 'WEEK' | 'MONTH' }) {
  const { t, n, day, compact, locale } = useFmt();
  const box = useRef<HTMLDivElement>(null);
  const [w, setW] = useState(600);
  const [tip, setTip] = useState<{ i: number; x: number; y: number } | null>(null);
  useEffect(() => {
    const el = box.current;
    if (!el) return;
    const ro = new ResizeObserver(() => setW(Math.max(300, el.clientWidth || 600)));
    ro.observe(el);
    setW(Math.max(300, el.clientWidth || 600));
    return () => ro.disconnect();
  }, []);
  const H = 260;
  const pl = 40;
  const pb = 22;
  const pt = 8;
  const max = Math.max(0, ...items.map((i) => i.pageviews));
  const step = (() => {
    if (max <= 4) return 1;
    const raw = max / 4;
    const p = Math.pow(10, Math.floor(Math.log10(raw)));
    const f = raw / p;
    return (f <= 1 ? 1 : f <= 2 ? 2 : f <= 5 ? 5 : 10) * p;
  })();
  const top = Math.max(step, Math.ceil(max / step) * step);
  const iw = w - pl;
  const ih = H - pb - pt;
  const bw = iw / Math.max(1, items.length);
  const every = Math.ceil(items.length / Math.max(2, Math.floor(iw / 60)));
  const label = (d: string, short: boolean) => {
    if (group === 'MONTH') return day(d, { month: 'short', year: short ? '2-digit' : 'numeric' });
    if (group === 'WEEK') return short ? day(d) : t('Week of %s', day(d, { day: 'numeric', month: 'long' }));
    return short ? day(d) : day(d, { weekday: 'short', day: 'numeric', month: 'long', year: 'numeric' });
  };
  const ticks: number[] = [];
  for (let y = 0; y <= top; y += step) ticks.push(y);
  void locale;
  return (
    <div className="flxan-chart" ref={box}>
      <svg viewBox={`0 0 ${w} ${H}`} role="img" aria-label={t('Visitors and page views chart')}>
        <g className="grid">
          {ticks.map((y) => {
            const yy = pt + ih - (y / top) * ih;
            return (
              <g key={y}>
                <line x1={pl} x2={w} y1={yy} y2={yy} />
                <text className="ax" x={pl - 6} y={yy + 4} textAnchor="end" style={{ fill: 'var(--flxan-muted)', fontSize: 11 }}>
                  {compact(y)}
                </text>
              </g>
            );
          })}
        </g>
        {items.map((it, i) => {
          const x = pl + i * bw;
          const bwid = Math.max(2, bw * (w < 520 ? 0.86 : 0.72));
          const ox = x + (bw - bwid) / 2;
          const hp = it.pageviews ? Math.max(3, (it.pageviews / top) * ih) : 0;
          const hv = it.visitors ? Math.max(3, (it.visitors / top) * ih) : 0;
          const rx = Math.min(3, bwid / 3);
          return (
            <g key={it.date}>
              <rect className="pv" x={ox} y={pt + ih - hp} width={bwid} height={hp} rx={rx} />
              <rect className="v" x={ox} y={pt + ih - hv} width={bwid} height={hv} rx={rx} />
              <rect
                className="hit"
                x={x}
                y={pt}
                width={bw}
                height={ih}
                onMouseEnter={() => setTip({ i, x: ((x + bw / 2) / w) * (box.current?.clientWidth ?? w), y: Math.max(10, pt + ih - (it.pageviews / top) * ih - 6) })}
                onMouseLeave={() => setTip(null)}
              />
              {i % every === 0 && (
                <text className="ax" x={x + bw / 2} y={H - 6} textAnchor="middle" style={{ fill: 'var(--flxan-muted)', fontSize: 11 }}>
                  {label(it.date, true)}
                </text>
              )}
            </g>
          );
        })}
      </svg>
      {tip && items[tip.i] && (
        <div className="flxan-tip" style={{ display: 'block', left: tip.x, top: tip.y }}>
          <b>{label(items[tip.i]!.date, false)}</b>
          <br />
          {t('Visitors')}: {n(items[tip.i]!.visitors)}
          <br />
          {t('Page views')}: {n(items[tip.i]!.pageviews)}
        </div>
      )}
    </div>
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
  const { t } = useFmt();
  return (
    <div className="flxan flxan-react" aria-busy={busy ? 'true' : 'false'}>
      <div className="flxan-app">
        <div className="flxan-head">
          <h1>{title}</h1>
          {head}
        </div>
        {notice}
        {children}
      </div>
    </div>
  );
}
