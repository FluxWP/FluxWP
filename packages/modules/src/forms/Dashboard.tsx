// Forms › Dashboard: the classic dashboard (includes/flx/dashboard/dashboard.js in 2.x) as React: the same markup
// (flxfd-*), hand-drawn SVG charts and tooltip; includes/flx/assets/classic/dashboard.css styles it. Numbers come
// from flxFormsDashboard (the classic stats payload).
import { useEffect, useRef, useState, type ReactNode } from 'react';
import { useFlxQuery } from '@fluxwp/graphql/react';
import { FlxFormsDashboard, FlxFormsFormOptions } from '@fluxwp/graphql/operations';
import { Loading, PageActions, usePageHead } from '@fluxwp/ui';
import { useForms } from './common';
import type { Translate } from './i18n';

interface Stats {
  from: string;
  to: string;
  days: number;
  now: string;
  currency: string;
  csvUrl?: string;
  totals: { n: number; unread: number; unreadAll: number; views: number | null; viewsSince: string | null; conversion: number | null };
  prev: { n: number; views: number | null; paid: number | null };
  series: { d: string; n: number; p: number }[];
  forms: { id: number; title: string; exists: boolean; n: number; unread: number; total: number; views: number | null; conv: number | null; last: string }[];
  heat: [number, number, number][];
  payments: { source?: string; paid: { cur: string; amount: number; n: number }[]; status: { s: string; n: number; amount: Record<string, number> }[]; prevPaid: number | null };
  recent: { id: number; form_id: number; form: string; track: string; date: string; unread: boolean; preview: string }[];
}

const PRIMARY = '#1F2937';
const PREV = '#8A9AA3';
const PAY: Record<string, string> = { paid: '#1F2937', pending: '#B7791F', failed: '#B83227', refunded: '#6B7C88' };
const HEAT = ['#DCEFE3', '#A9D8BB', '#6DBE94', '#2E9A6D', '#111827'];
const KEY = 'flxfd_filters';

const pad = (x: number) => (x < 10 ? '0' : '') + x;
const ymd = (d: Date) => d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
function parseDate(str: string): Date | null {
  const m = String(str || '').match(/^(\d{4})-(\d{2})-(\d{2})(?:[T ](\d{2}):(\d{2})(?::(\d{2}))?)?/);
  if (!m) return null;
  return new Date(+m[1]!, +m[2]! - 1, +m[3]!, +(m[4] ?? 0), +(m[5] ?? 0), +(m[6] ?? 0));
}
function addDays(day: string, n: number): string {
  const d = parseDate(day) ?? new Date();
  d.setDate(d.getDate() + n);
  return ymd(d);
}
function niceScale(max: number) {
  if (max <= 4) return { top: 4, ticks: 4 };
  const raw = max / 4;
  const p = Math.pow(10, Math.floor(Math.log10(raw)));
  const m = raw / p;
  let step = (m <= 1 ? 1 : m <= 2 ? 2 : m <= 2.5 ? 2.5 : m <= 5 ? 5 : 10) * p;
  if (step < 1) step = 1;
  const ticks = Math.ceil(max / step);
  return { top: ticks * step, ticks };
}

function fmts() {
  let loc = document.documentElement.lang || 'en-US';
  try {
    new Intl.NumberFormat(loc);
  } catch {
    loc = 'en-US';
  }
  const nf = (o: Intl.NumberFormatOptions) => {
    try {
      return new Intl.NumberFormat(loc, o);
    } catch {
      return new Intl.NumberFormat('en-US');
    }
  };
  const df = (d: Date | null, o: Intl.DateTimeFormatOptions) => {
    if (!d) return '';
    try {
      return new Intl.DateTimeFormat(loc, o).format(d);
    } catch {
      return ymd(d);
    }
  };
  const f = {
    num: (v: number) => nf({ maximumFractionDigits: 0 }).format(v || 0),
    compact: (v: number) => nf({ notation: 'compact', maximumFractionDigits: 1 }).format(v || 0),
    pct: (v: number, digits = 1) => nf({ style: 'percent', maximumFractionDigits: digits }).format((v || 0) / 100),
    money: (v: number, cur: string) => {
      try {
        return new Intl.NumberFormat(loc, { style: 'currency', currency: cur || 'TRY' }).format(v || 0);
      } catch {
        return f.num(v) + ' ' + (cur || '');
      }
    },
    dayShort: (s: string) => df(parseDate(s), { day: 'numeric', month: 'short' }),
    dayLong: (s: string) => df(parseDate(s), { weekday: 'short', day: 'numeric', month: 'long', year: 'numeric' }),
    dateTime: (s: string) => df(parseDate(s), { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }),
    weekday: (d: Date, long = false) => df(d, { weekday: long ? 'long' : 'short' }),
    ago: (s: string, nowStr: string) => {
      const d = parseDate(s);
      const now = parseDate(nowStr);
      if (!d || !now) return '';
      const sec = Math.round((d.getTime() - now.getTime()) / 1000);
      const abs = Math.abs(sec);
      const units: [Intl.RelativeTimeFormatUnit, number][] = [
        ['year', 31536000],
        ['month', 2592000],
        ['week', 604800],
        ['day', 86400],
        ['hour', 3600],
        ['minute', 60],
      ];
      try {
        const rtf = new Intl.RelativeTimeFormat(loc, { numeric: 'auto' });
        for (const [u, n] of units) if (abs >= n) return rtf.format(Math.round(sec / n), u);
        return rtf.format(0, 'minute');
      } catch {
        return f.dateTime(s);
      }
    },
  };
  return f;
}
type F = ReturnType<typeof fmts>;

interface Tip {
  x: number;
  y: number;
  title: string;
  lines: { color?: string; dashed?: boolean; label: string; value: string }[];
}
type ShowTip = (ev: { clientX: number; clientY: number }, title: string, lines: Tip['lines']) => void;

/** Width of a box, followed on resize (the charts are drawn for it, as the classic ones were). */
function useWidth<T extends HTMLElement>(): [React.RefObject<T | null>, number] {
  const ref = useRef<T>(null);
  const [w, setW] = useState(0);
  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    setW(el.clientWidth);
    const ro = new ResizeObserver(() => setW(el.clientWidth));
    ro.observe(el);
    return () => ro.disconnect();
  }, []);
  return [ref, w];
}

interface Filters {
  preset: string;
  from: string;
  to: string;
  form: string;
}
function loadFilters(): Filters {
  const st: Filters = { preset: '30', from: '', to: '', form: '' };
  try {
    const saved = JSON.parse(localStorage.getItem(KEY) || 'null') as Partial<Filters> | null;
    if (saved && typeof saved === 'object') for (const k of ['preset', 'from', 'to', 'form'] as const) if (typeof saved[k] === 'string') st[k] = saved[k];
  } catch {
    /* private mode */
  }
  if (!['7', '30', '90', 'custom'].includes(st.preset)) st.preset = '30';
  if (st.preset === 'custom' && !(st.from && st.to)) st.preset = '30';
  return st;
}

export function DashboardPage() {
  const { t, go, wp } = useForms();
  usePageHead(t('Dashboard'), t('Responses, form views, conversion and payments at a glance.'));
  const today = ymd(new Date());
  const [st, setSt] = useState<Filters>(loadFilters);
  const [draft, setDraft] = useState({ from: st.from, to: st.to });
  const options = useFlxQuery(FlxFormsFormOptions, {});
  const forms = options.data?.flxFormsFormOptions ?? [];
  const form = st.form && (!options.data || forms.some((f) => String(f.databaseId) === st.form)) ? st.form : '';
  const range: [string, string] = st.preset === 'custom' ? [st.from, st.to] : [addDays(today, -((parseInt(st.preset, 10) || 30) - 1)), today];
  // The first view (30 days, every form) is the query the page preloads.
  const vars = st.preset === '30' && !form ? {} : { from: range[0], to: range[1], form: Number(form) || undefined };
  const q = useFlxQuery(FlxFormsDashboard, vars, { placeholderData: (prev) => prev });
  const S = q.data?.flxFormsDashboard as Stats | undefined;
  const [tip, setTip] = useState<Tip | null>(null);
  const f = fmts();

  const update = (next: Partial<Filters>) => {
    const v = { ...st, ...next };
    setSt(v);
    try {
      localStorage.setItem(KEY, JSON.stringify(v));
    } catch {
      /* private mode */
    }
  };
  useEffect(() => {
    const hide = () => setTip(null);
    window.addEventListener('scroll', hide, { passive: true });
    return () => window.removeEventListener('scroll', hide);
  }, []);
  const showTip: ShowTip = (ev, title, lines) => setTip({ x: ev.clientX, y: ev.clientY, title, lines });
  const hideTip = () => setTip(null);
  const openForm = (id: number, entry?: number) => go('responses', entry ? { entry } : { form: id });
  const head = (
    <PageActions>
      <a
        className="flxfd-btn"
        href="#/forms"
        onClick={(e) => {
          e.preventDefault();
          go('forms');
        }}
      >
        {t('Open panel')}
      </a>
      {wp ? (
        <a
          className="flxfd-btn flxfd-btn--primary"
          href="#/create"
          onClick={(e) => {
            e.preventDefault();
            go('create');
          }}
        >
          {t('New form')}
        </a>
      ) : null}
    </PageActions>
  );

  if (options.data && !forms.length) {
    return (
      <div className="flxfd flx-raw">
        {head}
        <div className="flxfd-card flxfd-emptystate">
          <h2>{t('You have not created a form yet.')}</h2>
          <a
            className="flxfd-btn flxfd-btn--primary"
            href="#/create"
            onClick={(e) => {
              e.preventDefault();
              go('create');
            }}
          >
            {t('Create a form')}
          </a>
        </div>
      </div>
    );
  }

  const csv = S?.csvUrl
    ? S.csvUrl.replace(/([?&])(from|to|form)=[^&]*/g, '$1').replace(/[?&]+$/, '') +
      (S.csvUrl.includes('?') ? '&' : '?') +
      'from=' +
      encodeURIComponent(range[0]) +
      '&to=' +
      encodeURIComponent(range[1]) +
      '&form=' +
      encodeURIComponent(form)
    : '#';
  const presets: [string, string][] = [
    ['7', t('7 days')],
    ['30', t('30 days')],
    ['90', t('90 days')],
    ['custom', t('Custom')],
  ];
  const shownFrom = st.preset === 'custom' ? draft.from || st.from : range[0];
  const shownTo = st.preset === 'custom' ? draft.to || st.to : range[1];

  return (
    <div className="flxfd flx-raw">
      {head}
      <div className={'flxfd-app' + (q.isFetching && S ? ' is-busy' : '')} aria-live="polite">
        <div className="flxfd-filters">
          <div className="flxfd-seg" role="group" aria-label={t('Date range')}>
            {presets.map(([p, label]) => (
              <button
                key={p}
                type="button"
                data-p={p}
                className={st.preset === p ? 'is-on' : ''}
                aria-pressed={st.preset === p}
                onClick={() => {
                  if (p === 'custom') {
                    const from = st.from || addDays(today, -29);
                    const to = st.to || today;
                    setDraft({ from, to });
                    update({ preset: 'custom', from, to });
                    return;
                  }
                  update({ preset: p });
                }}
              >
                {label}
              </button>
            ))}
          </div>
          <div className={'flxfd-custom' + (st.preset === 'custom' ? ' is-open' : '')}>
            <label>
              <span className="screen-reader-text">{t('From')}</span>
              <input type="date" max={today} aria-label={t('From')} value={shownFrom} onChange={(e) => setDraft((d) => ({ ...d, from: e.target.value }))} />
            </label>
            <span className="flxfd-dash" aria-hidden="true">
              –
            </span>
            <label>
              <span className="screen-reader-text">{t('Until')}</span>
              <input type="date" max={today} aria-label={t('Until')} value={shownTo} onChange={(e) => setDraft((d) => ({ ...d, to: e.target.value }))} />
            </label>
            <button
              type="button"
              className="flxfd-btn"
              onClick={() => {
                if (!draft.from || !draft.to) return;
                const a = draft.from < draft.to ? draft.from : draft.to;
                const b = draft.from < draft.to ? draft.to : draft.from;
                update({ preset: 'custom', from: a, to: b });
              }}
            >
              {t('Apply')}
            </button>
          </div>
          <div className="flxfd-filters__end">
            <select aria-label={t('Form')} value={form} onChange={(e) => update({ form: e.target.value })}>
              <option value="">{t('All forms')}</option>
              {forms.map((x) => (
                <option key={x.databaseId} value={String(x.databaseId)}>
                  {x.title}
                </option>
              ))}
            </select>
            <a className="flxfd-btn flxfd-btn--ghost" href={csv} title={t('Download the responses in the selected range and form as a spreadsheet file.')}>
              <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M12 3v12m0 0l-4.5-4.5M12 15l4.5-4.5M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2" />
              </svg>
              <span>{t('Export CSV')}</span>
            </a>
          </div>
        </div>
        <div className="flxfd-body">
          {q.isError && !S ? (
            <div className="flxfd-card flxfd-error" role="alert">
              <p>{t('The dashboard data could not be loaded.')}</p>
              <button type="button" className="flxfd-btn" onClick={() => void q.refetch()}>
                {t('Try again')}
              </button>
            </div>
          ) : !S ? (
            <div className="flxfd-card flxfd-loading">
              <Loading />
            </div>
          ) : (
            <Body S={S} t={t} f={f} form={form} showTip={showTip} hideTip={hideTip} openForm={openForm} go={go} />
          )}
        </div>
      </div>
      {tip ? <TipBox tip={tip} /> : null}
    </div>
  );
}

function TipBox({ tip }: { tip: Tip }) {
  const ref = useRef<HTMLDivElement>(null);
  const [pos, setPos] = useState({ left: tip.x + 14, top: tip.y + 14 });
  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    const r = el.getBoundingClientRect();
    let x = tip.x + 14;
    let y = tip.y + 14;
    if (x + r.width > window.innerWidth - 8) x = tip.x - r.width - 14;
    if (x < 8) x = 8;
    if (y + r.height > window.innerHeight - 8) y = tip.y - r.height - 14;
    setPos({ left: x, top: y });
  }, [tip]);
  return (
    <div ref={ref} className="flxfd-tip" role="tooltip" style={pos}>
      <strong>{tip.title}</strong>
      {tip.lines.map((l, i) => (
        <span key={i} className="flxfd-tip__row">
          {l.color ? <i className={'flxfd-sw' + (l.dashed ? ' is-dashed' : '')} style={{ background: l.color }} /> : null}
          <span>{l.label}</span>
          <b>{l.value}</b>
        </span>
      ))}
    </div>
  );
}

interface BodyProps {
  S: Stats;
  t: Translate;
  f: F;
  form: string;
  showTip: ShowTip;
  hideTip: () => void;
  openForm: (id: number, entry?: number) => void;
  go: ReturnType<typeof useForms>['go'];
}

function Card({ title, hint, aside, cls, children }: { title: string; hint?: string; aside?: ReactNode; cls?: string; children: ReactNode }) {
  return (
    <section className={'flxfd-card' + (cls ? ' ' + cls : '')}>
      <header className="flxfd-card__head">
        <div>
          <h2 className="flxfd-card__title">{title}</h2>
          {hint ? <p className="flxfd-card__hint">{hint}</p> : null}
        </div>
        {aside ?? null}
      </header>
      {children}
    </section>
  );
}

function Body(p: BodyProps) {
  const { S, t, f, form } = p;
  const formTitle = (id: number, title: string) => title || t('Deleted form') + ' #' + id;
  const empty = (text: string) => <div className="flxfd-empty">{text}</div>;
  const hasPay = S.payments.status.length > 0 || (S.payments.source ?? 'none') !== 'none';
  const link = (id: number, entry?: number) => ({
    href: '#/responses?' + (entry ? 'entry=' + entry : 'form=' + id),
    onClick: (e: React.MouseEvent) => {
      e.preventDefault();
      p.openForm(id, entry);
    },
  });
  const panel = {
    href: form ? '#/responses?form=' + form : '#/responses',
    onClick: (e: React.MouseEvent) => {
      e.preventDefault();
      if (form) p.openForm(Number(form));
      else p.go('responses');
    },
  };

  return (
    <>
      <Kpis {...p} />
      <div className={'flxfd-grid' + (hasPay ? ' flxfd-grid--2-1' : '')}>
        <Card
          title={t('Daily responses')}
          aside={
            <div className="flxfd-legend">
              <span>
                <i className="flxfd-sw" style={{ background: PRIMARY }} />
                {t('Selected period')}
              </span>
              <span>
                <i className="flxfd-sw is-dashed" style={{ background: PREV }} />
                {t('Previous period')}
              </span>
            </div>
          }
        >
          {S.totals.n || S.prev.n ? <LineChart {...p} /> : empty(t('No responses in this range yet.'))}
        </Card>
        {hasPay ? (
          <Card title={t('Payment status')}>
            <PayChart {...p} />
          </Card>
        ) : null}
      </div>
      <div className={'flxfd-grid' + (form ? '' : ' flxfd-grid--1-1')}>
        {form ? null : (
          <Card title={t('Responses by form')}>
            <BarChart {...p} />
          </Card>
        )}
        <Card title={t('When responses arrive')} hint={t('Day of week and hour of day')}>
          <Heatmap {...p} />
        </Card>
      </div>
      <Card
        title={t('Recent responses')}
        aside={
          <a className="flxfd-link" {...panel}>
            {t('Open panel')} →
          </a>
        }
      >
        {!S.recent.length ? (
          empty(t('No responses in this range yet.'))
        ) : (
          <div className="flxfd-tablewrap">
            <table className="flxfd-table flxfd-table--recent">
              <thead>
                <tr>
                  <th scope="col">{t('Form')}</th>
                  <th scope="col">{t('Preview')}</th>
                  <th scope="col">{t('Date')}</th>
                  <th scope="col">{t('Status')}</th>
                  <th scope="col">
                    <span className="screen-reader-text">{t('Open')}</span>
                  </th>
                </tr>
              </thead>
              <tbody>
                {S.recent.map((r) => (
                  <tr key={r.id} className={r.unread ? 'is-unread' : ''}>
                    <td className="flxfd-col-form" data-label={t('Form')}>
                      <a {...link(r.form_id)}>{formTitle(r.form_id, r.form)}</a>
                    </td>
                    <td className="flxfd-col-preview" data-label={t('Preview')}>
                      <a {...link(r.form_id, r.id)} className="flxfd-preview">
                        {r.preview || t('(empty response)')}
                      </a>
                    </td>
                    <td className="flxfd-col-date" data-label={t('Date')}>
                      <time dateTime={r.date} title={f.dateTime(r.date)}>
                        {f.ago(r.date, S.now)}
                      </time>
                    </td>
                    <td className="flxfd-col-status" data-label={t('Status')}>
                      <span className={'flxfd-badge' + (r.unread ? ' is-new' : '')}>{r.unread ? t('New') : t('Read')}</span>
                    </td>
                    <td className="flxfd-col-act">
                      <a {...link(r.form_id, r.id)} className="flxfd-open" aria-label={t('Open') + ': ' + formTitle(r.form_id, r.form) + ' #' + r.id}>
                        {t('Open')} →
                      </a>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Card>
      <Card title={t('Top forms')}>
        {!S.forms.length ? (
          empty(t('No responses in this range yet.'))
        ) : (
          <div className="flxfd-tablewrap">
            <table className="flxfd-table flxfd-table--forms">
              <thead>
                <tr>
                  <th scope="col">{t('Form')}</th>
                  <th scope="col" className="num">
                    {t('Responses')}
                  </th>
                  <th scope="col" className="num">
                    {t('Views')}
                  </th>
                  <th scope="col" className="num">
                    {t('Conversion')}
                  </th>
                  <th scope="col" className="num">
                    {t('All time')}
                  </th>
                  <th scope="col">{t('Last response')}</th>
                  <th scope="col">
                    <span className="screen-reader-text">{t('Open')}</span>
                  </th>
                </tr>
              </thead>
              <tbody>
                {S.forms.map((r) => {
                  const share = S.totals.n ? (r.n / S.totals.n) * 100 : 0;
                  return (
                    <tr key={r.id}>
                      <th scope="row" className="flxfd-col-form" data-label={t('Form')}>
                        <a {...link(r.id)}>{formTitle(r.id, r.title)}</a>
                        {r.unread ? <span className="flxfd-badge is-new">{f.num(r.unread) + ' ' + t('New').toLocaleLowerCase(document.documentElement.lang || undefined)}</span> : null}
                      </th>
                      <td className="num" data-label={t('Responses')}>
                        <span className="flxfd-sharecell">
                          <b>{f.num(r.n)}</b>
                          <span className="flxfd-share" title={f.pct(share, 0)}>
                            <span style={{ width: Math.max(r.n ? 3 : 0, share).toFixed(1) + '%' }} />
                          </span>
                        </span>
                      </td>
                      <td className="num" data-label={t('Views')}>
                        {r.views === null ? '—' : f.num(r.views)}
                      </td>
                      <td className="num" data-label={t('Conversion')}>
                        {r.conv === null ? '—' : f.pct(r.conv, 1)}
                      </td>
                      <td className="num" data-label={t('All time')}>
                        {f.num(r.total)}
                      </td>
                      <td data-label={t('Last response')}>
                        {r.last ? (
                          <time dateTime={r.last} title={f.dateTime(r.last)}>
                            {f.ago(r.last, S.now)}
                          </time>
                        ) : (
                          <span className="flxfd-muted">{t('Never')}</span>
                        )}
                      </td>
                      <td className="flxfd-col-act">
                        {r.exists ? (
                          <a {...link(r.id)} className="flxfd-open">
                            {t('View responses')} →
                          </a>
                        ) : null}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}
      </Card>
    </>
  );
}

function Kpis({ S, t, f, go }: BodyProps) {
  const tot = S.totals;
  const prev = S.prev;
  const pay = S.payments;
  const vs = t('vs previous {n} days', { n: S.days });
  const delta = (cur: number, before: number | null | undefined): ReactNode => {
    if (before === null || before === undefined) return null;
    const muted = <span className="flxfd-muted">{vs}</span>;
    if (!before && !cur)
      return (
        <span className="flxfd-delta">
          <span>= {t('no change')}</span> {muted}
        </span>
      );
    if (!before)
      return (
        <span className="flxfd-delta is-up">
          <span>▲ {t('new')}</span> {muted}
        </span>
      );
    const x = ((cur - before) / before) * 100;
    if (Math.abs(x) < 0.5)
      return (
        <span className="flxfd-delta">
          <span>= {t('no change')}</span> {muted}
        </span>
      );
    return (
      <span className={'flxfd-delta ' + (x > 0 ? 'is-up' : 'is-down')}>
        <span aria-label={(x > 0 ? t('up') : t('down')) + ' ' + f.pct(Math.abs(x), 0)}>{(x > 0 ? '▲ ' : '▼ ') + f.pct(Math.abs(x), 0)}</span> {muted}
      </span>
    );
  };
  const kpi = (label: string, value: string, sub: ReactNode, accent: string) => (
    <div className="flxfd-kpi" style={{ ['--accent' as string]: accent }}>
      <div className="flxfd-kpi__label">{label}</div>
      <div className="flxfd-kpi__value" title={value}>
        {value}
      </div>
      <div className="flxfd-kpi__sub">{sub ?? ' '}</div>
    </div>
  );
  const unreadSub =
    tot.unreadAll !== tot.unread ? (
      <span>{t('{n} unread in total', { n: f.num(tot.unreadAll) })}</span>
    ) : (
      <a
        href="#/responses"
        onClick={(e) => {
          e.preventDefault();
          go('responses');
        }}
      >
        {t('Open panel')} →
      </a>
    );
  const viewsSub =
    tot.views === null ? (
      <span className="flxfd-muted">{t('No view data')}</span>
    ) : tot.viewsSince ? (
      <span className="flxfd-muted">{t('Recorded since {date}', { date: f.dayShort(tot.viewsSince) })}</span>
    ) : prev.views === null ? (
      <span className="flxfd-muted">{t('View data is kept for about 8 days')}</span>
    ) : (
      delta(tot.views, prev.views)
    );
  const convSub = <span className="flxfd-muted">{tot.viewsSince ? t('Responses per view since {date}', { date: f.dayShort(tot.viewsSince) }) : t('Responses per form view')}</span>;
  const main = pay.paid[0] ?? { cur: S.currency, amount: 0, n: 0 };
  let paySub: ReactNode;
  if (pay.paid.length) {
    const paidN = pay.paid.reduce((a, x) => a + x.n, 0);
    if (pay.paid.length > 1)
      paySub = (
        <>
          <span>{t('{n} paid', { n: f.num(paidN) })}</span>
          <span className="flxfd-muted">
            {' · ' +
              t('Also {list}', {
                list: pay.paid
                  .slice(1)
                  .map((x) => f.money(x.amount, x.cur))
                  .join(', '),
              })}
          </span>
        </>
      );
    else if (prev.paid !== null && prev.paid !== undefined) paySub = delta(main.amount, prev.paid);
    else paySub = <span>{t('{n} paid', { n: f.num(paidN) })}</span>;
  } else {
    paySub = <span className="flxfd-muted">{t('No payments in this range')}</span>;
  }
  return (
    <div className="flxfd-kpis">
      {kpi(t('Responses'), f.num(tot.n), delta(tot.n, prev.n), PRIMARY)}
      {kpi(t('Unread'), f.num(tot.unread), unreadSub, '#2F8F6A')}
      {kpi(t('Form views'), tot.views === null ? '—' : f.num(tot.views), viewsSub, '#3A7CA5')}
      {kpi(t('Conversion rate'), tot.conversion === null ? '—' : f.pct(tot.conversion, 1), convSub, '#111827')}
      {kpi(t('Payments received'), f.money(main.amount, main.cur), paySub, '#B7791F')}
    </div>
  );
}

function LineChart({ S, t, f, showTip, hideTip }: BodyProps) {
  const [ref, width] = useWidth<HTMLDivElement>();
  const [hover, setHover] = useState(-1);
  const pts = S.series;
  const n = pts.length;
  let total = 0;
  let max = 0;
  for (const p of pts) {
    total += p.n;
    max = Math.max(max, p.n, p.p);
  }
  const W = Math.max(280, Math.round(width || 600));
  const narrow = W < 520;
  const H = narrow ? 210 : 260;
  const L = 36;
  const R = 12;
  const TP = 12;
  const BT = 28;
  const iw = W - L - R;
  const ih = H - TP - BT;
  const { top, ticks } = niceScale(Math.max(max, 1));
  const x = (i: number) => L + (n > 1 ? (i * iw) / (n - 1) : iw / 2);
  const y = (v: number) => TP + ih - (v / top) * ih;
  const every = Math.max(1, Math.ceil(n / (narrow ? 4 : 8)));
  const path = (key: 'n' | 'p') => pts.map((p, i) => (i ? 'L' : 'M') + x(i).toFixed(1) + ' ' + y(p[key]).toFixed(1)).join(' ');
  const move = (ev: { clientX: number; clientY: number }, svg: SVGSVGElement) => {
    const r = svg.getBoundingClientRect();
    const cx = ((ev.clientX - r.left) / r.width) * W;
    const i = n > 1 ? Math.max(0, Math.min(n - 1, Math.round((cx - L) / (iw / (n - 1))))) : 0;
    const p = pts[i];
    if (!p) return;
    setHover(i);
    showTip(ev, f.dayLong(p.d), [
      { color: PRIMARY, label: t('Selected period'), value: f.num(p.n) },
      { color: PREV, dashed: true, label: t('Previous period') + ' (' + f.dayShort(addDays(p.d, -S.days)) + ')', value: f.num(p.p) },
    ]);
  };
  const hp = hover >= 0 ? pts[hover] : undefined;
  return (
    <div className="flxfd-chart" ref={ref}>
      {width ? (
        <svg viewBox={'0 0 ' + W + ' ' + H} width={W} height={H} role="img" aria-label={t('Daily responses') + ': ' + f.num(total) + ' ' + t('responses')}>
          <g className="flxfd-gl">
            {Array.from({ length: ticks + 1 }, (_, g) => {
              const gv = (top / ticks) * g;
              const gy = Math.round(y(gv)) + 0.5;
              return (
                <g key={g}>
                  <line x1={L} x2={W - R} y1={gy} y2={gy} className={g === 0 ? 'is-base' : ''} />
                  <text x={L - 8} y={gy + 4} textAnchor="end">
                    {gv >= 1000 ? f.compact(gv) : f.num(gv)}
                  </text>
                </g>
              );
            })}
          </g>
          <g className="flxfd-axis">
            {pts.map((p, i) => {
              const last = i === n - 1;
              if (!((i % every === 0 && (n - 1 - i >= every * 0.85 || last)) || last)) return null;
              return (
                <text key={i} x={x(i)} y={H - 8} textAnchor={i === 0 ? 'start' : last ? 'end' : 'middle'}>
                  {f.dayShort(p.d)}
                </text>
              );
            })}
          </g>
          <defs>
            <linearGradient id="flxfd-area" x1="0" x2="0" y1="0" y2="1">
              <stop offset="0" stopColor={PRIMARY} stopOpacity="0.16" />
              <stop offset="1" stopColor={PRIMARY} stopOpacity="0" />
            </linearGradient>
          </defs>
          {n ? <path d={path('n') + ' L' + x(n - 1).toFixed(1) + ' ' + y(0) + ' L' + x(0).toFixed(1) + ' ' + y(0) + ' Z'} fill="url(#flxfd-area)" /> : null}
          <path d={path('p')} fill="none" stroke={PREV} strokeWidth="1.5" strokeDasharray="4 4" strokeLinejoin="round" />
          <path d={path('n')} fill="none" stroke={PRIMARY} strokeWidth="2" strokeLinejoin="round" strokeLinecap="round" />
          <line className="flxfd-cross" x1={hp ? x(hover) : 0} x2={hp ? x(hover) : 0} y1={TP} y2={TP + ih} visibility={hp ? 'visible' : 'hidden'} />
          <circle r="4" fill={PREV} stroke="#fff" strokeWidth="2" cx={hp ? x(hover) : 0} cy={hp ? y(hp.p) : 0} visibility={hp ? 'visible' : 'hidden'} />
          <circle r="5" fill={PRIMARY} stroke="#fff" strokeWidth="2" cx={hp ? x(hover) : 0} cy={hp ? y(hp.n) : 0} visibility={hp ? 'visible' : 'hidden'} />
          <rect
            x={L - 6}
            y={0}
            width={iw + 12}
            height={H}
            fill="transparent"
            onMouseMove={(e) => move(e, e.currentTarget.ownerSVGElement as SVGSVGElement)}
            onTouchStart={(e) => {
              const tt = e.touches[0];
              if (tt) move(tt, e.currentTarget.ownerSVGElement as SVGSVGElement);
            }}
            onMouseLeave={() => {
              setHover(-1);
              hideTip();
            }}
          />
        </svg>
      ) : null}
    </div>
  );
}

function BarChart({ S, t, f, showTip, hideTip, openForm }: BodyProps) {
  const [ref, width] = useWidth<HTMLDivElement>();
  let rows: (Stats['forms'][number] & { other?: number })[] = S.forms.filter((r) => r.n > 0);
  if (!rows.length)
    return (
      <div className="flxfd-chart" ref={ref}>
        <div className="flxfd-empty">{t('No responses in this range yet.')}</div>
      </div>
    );
  const MAXR = 8;
  if (rows.length > MAXR) {
    const rest = rows.slice(MAXR - 1);
    const other = { id: 0, title: '', exists: false, n: rest.reduce((a, r) => a + r.n, 0), unread: 0, total: 0, views: null, conv: null, last: '', other: rest.length };
    rows = [...rows.slice(0, MAXR - 1), other];
  }
  const total = S.totals.n || 1;
  const top = Math.max(...rows.map((r) => r.n));
  const W = Math.max(260, Math.round(width || 400));
  const ROW = 40;
  const BAR = 12;
  const VAL = 64;
  const H = rows.length * ROW;
  const iw = W - VAL;
  const maxChars = Math.max(8, Math.floor(iw / 7.2));
  const titleOf = (r: (typeof rows)[number]) => (r.other ? t('{n} other forms', { n: f.num(r.other) }) : r.title || t('Deleted form') + ' #' + r.id);
  return (
    <div className="flxfd-chart" ref={ref}>
      {width ? (
        <svg viewBox={'0 0 ' + W + ' ' + H} width={W} height={H} role="img" aria-label={t('Responses by form')}>
          {rows.map((r, i) => {
            const y0 = i * ROW;
            const w = Math.max(r.n ? 4 : 0, (r.n / top) * iw);
            let label = titleOf(r);
            if (label.length > maxChars) label = label.slice(0, maxChars - 1) + '…';
            const bw = Math.max(0, w);
            const rr = Math.min(4, bw / 2);
            const g = (
              <g
                key={i}
                className="flxfd-barrow"
                style={r.id && !r.other ? { cursor: 'pointer' } : undefined}
                onClick={r.id && !r.other ? () => openForm(r.id) : undefined}
                onMouseMove={(ev) =>
                  showTip(ev, titleOf(r), [
                    { color: PRIMARY, label: t('Responses'), value: f.num(r.n) },
                    { label: t('Share'), value: f.pct((r.n / total) * 100, 0) },
                    ...(r.unread ? [{ label: t('Unread'), value: f.num(r.unread) }] : []),
                  ])
                }
                onMouseLeave={hideTip}
              >
                <text x={0} y={y0 + 14} className="flxfd-bar__label">
                  {label}
                </text>
                <rect x={0} y={y0 + 21} width={iw} height={BAR} rx={4} className="flxfd-bar__track" />
                <path
                  className="flxfd-bar__fill"
                  fill={PRIMARY}
                  d={'M0 ' + (y0 + 21) + ' h' + (bw - rr) + ' a' + rr + ' ' + rr + ' 0 0 1 ' + rr + ' ' + rr + ' v' + (BAR - 2 * rr) + ' a' + rr + ' ' + rr + ' 0 0 1 -' + rr + ' ' + rr + ' h-' + (bw - rr) + ' Z'}
                />
                <text x={W} y={y0 + 31} textAnchor="end" className="flxfd-bar__val">
                  {f.num(r.n)}
                </text>
                <rect x={0} y={y0} width={W} height={ROW} fill="transparent" />
              </g>
            );
            return g;
          })}
        </svg>
      ) : null}
    </div>
  );
}

function PayChart({ S, t, f, showTip, hideTip }: BodyProps) {
  const [ref, width] = useWidth<HTMLDivElement>();
  const list = S.payments.status;
  if (!list.length)
    return (
      <div className="flxfd-chart" ref={ref}>
        <div className="flxfd-empty">{t('No payments in this range')}</div>
      </div>
    );
  const label = (s: string) => ({ paid: t('Payment paid'), pending: t('Pending'), failed: t('Failed or canceled'), refunded: t('Refunded') })[s] ?? s;
  const total = list.reduce((a, x) => a + x.n, 0);
  const W = Math.max(240, Math.round(width || 300));
  const H = 16;
  const GAP = 2;
  const avail = W - GAP * (list.length - 1);
  let xpos = 0;
  const segs = list.map((x, i) => {
    let w = Math.max(3, (x.n / total) * avail);
    if (i === list.length - 1) w = Math.max(3, W - xpos);
    const seg = { x: xpos, w, s: x };
    xpos += w + GAP;
    return seg;
  });
  const lines = (x: (typeof list)[number]) => [{ label: t('{n} payments', { n: f.num(x.n) }), value: '' }, ...Object.keys(x.amount ?? {}).map((c) => ({ label: c, value: f.money(x.amount[c] ?? 0, c) }))];
  return (
    <div className="flxfd-chart" ref={ref}>
      {width ? (
        <svg viewBox={'0 0 ' + W + ' ' + H} width={W} height={H} role="img" aria-label={t('Payment status')}>
          {segs.map((g, i) => (
            <rect key={i} x={g.x} y={0} width={g.w} height={H} rx={4} fill={PAY[g.s.s] ?? PREV} onMouseMove={(ev) => showTip(ev, label(g.s.s), lines(g.s))} onMouseLeave={hideTip} />
          ))}
        </svg>
      ) : null}
      <ul className="flxfd-paylegend">
        {list.map((x) => (
          <li key={x.s}>
            <i className="flxfd-sw" style={{ background: PAY[x.s] ?? PREV }} />
            <span className="flxfd-paylegend__label">{label(x.s)}</span>
            <span className="flxfd-paylegend__n">{f.num(x.n) + ' · ' + f.pct((x.n / total) * 100, 0)}</span>
            <span className="flxfd-paylegend__amt">
              {Object.keys(x.amount ?? {})
                .map((c) => f.money(x.amount[c] ?? 0, c))
                .join(' + ')}
            </span>
          </li>
        ))}
      </ul>
    </div>
  );
}

function Heatmap({ S, t, f, showTip, hideTip }: BodyProps) {
  const [ref, width] = useWidth<HTMLDivElement>();
  if (!S.heat.length)
    return (
      <div className="flxfd-chart flxfd-chart--heat" ref={ref}>
        <div className="flxfd-empty">{t('No responses in this range yet.')}</div>
      </div>
    );
  const m: Record<string, number> = {};
  let max = 0;
  for (const [d, h, v] of S.heat) {
    m[d + '-' + h] = v;
    max = Math.max(max, v);
  }
  // Rows from Monday (0), the WordPress default week start, as the payload counts days.
  const order = [0, 1, 2, 3, 4, 5, 6];
  const monday = new Date(2024, 0, 1);
  let W = Math.max(280, Math.round(width || 500));
  const LBL = 40;
  const TOPL = 18;
  const GAP = 2;
  const cell = Math.max(7, Math.min(26, Math.floor((W - LBL - GAP * 23) / 24)));
  const gridW = cell * 24 + GAP * 23;
  const H = TOPL + 7 * cell + 6 * GAP;
  W = LBL + gridW;
  const hours: number[] = [];
  for (let hr = 0; hr < 24; hr += cell < 14 ? 6 : 3) hours.push(hr);
  return (
    <div className="flxfd-chart flxfd-chart--heat" ref={ref}>
      {width ? (
        <svg viewBox={'0 0 ' + W + ' ' + H} width={W} height={H} role="img" aria-label={t('When responses arrive') + ' — ' + t('Day of week and hour of day')}>
          {hours.map((hr) => (
            <text key={hr} x={LBL + hr * (cell + GAP)} y={11} className="flxfd-axis-t">
              {pad(hr)}
            </text>
          ))}
          {order.map((d, row) => {
            const day = new Date(monday);
            day.setDate(1 + d);
            const y0 = TOPL + row * (cell + GAP);
            return (
              <g key={d}>
                <text x={0} y={y0 + cell / 2 + 4} className="flxfd-axis-t">
                  {f.weekday(day)}
                </text>
                {Array.from({ length: 24 }, (_, hh) => {
                  const v = m[d + '-' + hh] ?? 0;
                  const lvl = v ? Math.min(4, Math.floor((v / max) * 4.999)) : -1;
                  return (
                    <rect
                      key={hh}
                      x={LBL + hh * (cell + GAP)}
                      y={y0}
                      width={cell}
                      height={cell}
                      rx={Math.min(3, cell / 4)}
                      fill={lvl < 0 ? 'var(--flxfd-cell0)' : HEAT[lvl]}
                      onMouseMove={(ev) => showTip(ev, f.weekday(day, true) + ' ' + pad(hh) + ':00–' + pad((hh + 1) % 24) + ':00', [{ label: t('Responses'), value: f.num(v) }])}
                      onMouseLeave={hideTip}
                    />
                  );
                })}
              </g>
            );
          })}
        </svg>
      ) : null}
      <div className="flxfd-heatlegend">
        <span>{t('Less')}</span>
        {['var(--flxfd-cell0)', ...HEAT].map((c) => (
          <i key={c} style={{ background: c }} />
        ))}
        <span>{t('More')}</span>
      </div>
    </div>
  );
}
