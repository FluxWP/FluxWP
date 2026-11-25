// Donation dashboard: period (7 / 30 / 90 days or custom), KPIs, donations per day (stacked SVG columns
// with a tooltip), one-time vs recurring, by donation type, by form, top donors. Same layout as the
// classic page; the data comes ready from FlxDonateDashboard (PHP preloads the 30 day view).
import { useEffect, useLayoutEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { keepPreviousData } from '@tanstack/react-query';
import { useFlxQuery } from '@fluxwp/graphql/react';
import { FlxDonateDashboard } from '@fluxwp/graphql/operations';
import type { FlxDonateDashboardQuery, FlxDonationRange } from '@fluxwp/graphql/types';
import { DatePicker, Loading, Screen } from '@fluxwp/ui';
import { Chart } from '@fluxwp/ui/chart';
import { useHost } from '../host';
import { Go, Ico, useDonate } from './shared';

type D = FlxDonateDashboardQuery['flxDonationDashboard'];
type Fmt = { num: (n: number, d?: number) => string; money: (n: number) => string; short: (n: number) => string };

const RANGES: { key: FlxDonationRange; label: string }[] = [
  { key: 'DAYS_7' as FlxDonationRange, label: '7 days' },
  { key: 'DAYS_30' as FlxDonationRange, label: '30 days' },
  { key: 'DAYS_90' as FlxDonationRange, label: '90 days' },
  { key: 'CUSTOM' as FlxDonationRange, label: 'Custom' },
];

const today = () => {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};
const shift = (day: string, n: number) => {
  const d = new Date(day + 'T12:00:00');
  d.setDate(d.getDate() + n);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};

/** Second series colour: a blue brand colour would hide the recurring series, so it turns amber. */
function second(c1: string): string {
  const m = c1.match(/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})/i);
  if (!m) return '#2F6FA3';
  const [r, g, b] = [m[1], m[2], m[3]].map((x) => parseInt(x ?? '0', 16) / 255) as [number, number, number];
  const mx = Math.max(r, g, b);
  const mn = Math.min(r, g, b);
  const d = mx - mn;
  let h = 0;
  if (d) h = mx === r ? ((g - b) / d) % 6 : mx === g ? (b - r) / d + 2 : (r - g) / d + 4;
  h = (h * 60 + 360) % 360;
  return d > 0.08 && h >= 180 && h <= 260 ? '#E0A526' : '#2F6FA3';
}

export function Dashboard({ notice }: { notice: ReactNode }) {
  const host = useHost();
  const { t, lang } = useDonate();
  const [range, setRange] = useState<FlxDonationRange>('DAYS_30' as FlxDonationRange);
  const [seg, setSeg] = useState<FlxDonationRange>('DAYS_30' as FlxDonationRange);
  const [from, setFrom] = useState(() => shift(today(), -29));
  const [to, setTo] = useState(today);
  const [applied, setApplied] = useState<{ from: string; to: string } | null>(null);
  const vars = range === 'CUSTOM' && applied ? { range, from: applied.from, to: applied.to } : { range };
  const q = useFlxQuery(FlxDonateDashboard, vars, { placeholderData: keepPreviousData });
  const d = q.data?.flxDonationDashboard;
  const root = useRef<HTMLDivElement>(null);
  const [c1, setC1] = useState('#1F2937');
  useLayoutEffect(() => {
    const el = root.current?.closest('.flxdn') ?? root.current;
    if (!el) return;
    const v = getComputedStyle(el).getPropertyValue('--flxdn-primary').trim();
    const dark = !!el.closest('[data-theme="dark"]') || document.documentElement.classList.contains('flxa-dark');
    const m = v.match(/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})/i);
    const lum = m ? (0.299 * parseInt(m[1] ?? '0', 16) + 0.587 * parseInt(m[2] ?? '0', 16) + 0.114 * parseInt(m[3] ?? '0', 16)) / 255 : 1;
    // A dark brand colour would vanish on the dark background: a light slate stands in for it there.
    if (dark && lum < 0.35) setC1('#94A3B8');
    else if (/^#[0-9a-f]{3,8}$|^rgb/i.test(v)) setC1(v);
  }, [d]);
  const c2 = second(c1);

  const fmt = useMemo<Fmt>(() => {
    const locale = lang.replace('_', '-') || 'tr-TR';
    const num = (n: number, dg = 0) => {
      try {
        return new Intl.NumberFormat(locale, { maximumFractionDigits: dg, minimumFractionDigits: dg }).format(n);
      } catch {
        return String(n);
      }
    };
    const money = (n: number) => {
      const dg = Math.abs(n - Math.round(n)) < 0.005 ? 0 : 2;
      const sym = d?.symbol ?? '₺';
      return d && d.currency !== 'TRY' && sym.length === 1 ? sym + num(n, dg) : num(n, dg) + ' ' + sym;
    };
    const short = (n: number) => {
      try {
        return new Intl.NumberFormat(locale, { notation: 'compact', maximumFractionDigits: 1 }).format(n);
      } catch {
        return num(n);
      }
    };
    return { num, money, short };
  }, [lang, d?.symbol, d?.currency]);

  const pick = (r: FlxDonationRange) => {
    setSeg(r);
    if (r !== 'CUSTOM') setRange(r);
  };
  const apply = () => {
    if (!from || !to) return;
    setApplied({ from: from <= to ? from : to, to: from <= to ? to : from });
    setRange('CUSTOM' as FlxDonationRange);
  };

  const own = d?.ownOnly ?? false;
  const all = d?.canList ?? false;
  const actions = all ? (
    <>
      <Go className="flxdn-btn" href={d?.listUrl ?? ''}>
        {t('All donations')}
      </Go>
      <Go className="flxdn-btn flxdn-btn--primary" href={d?.donorsUrl ?? ''}>
        {t('Donors')}
      </Go>
    </>
  ) : own && d ? (
    <Go className="flxdn-btn" href={d.listUrl}>
      {t('My referrals')}
    </Go>
  ) : null;

  return (
    <Screen className="flxdn" width="wide" icon={host.appIcon} title={t('Donation dashboard')} subtitle={t('Donations, donors and recurring gifts at a glance.')} actions={actions} notice={notice}>
      <div ref={root}>
        <div className="flxdn-filters" role="group" aria-label={t('Period')}>
          <div className="flxdn-seg">
            {RANGES.map((r) => (
              <button key={r.key} type="button" className={seg === r.key ? 'is-on' : undefined} aria-pressed={seg === r.key} onClick={() => pick(r.key)}>
                {t(r.label)}
              </button>
            ))}
          </div>
          <div className={'flxdn-custom' + (seg === 'CUSTOM' ? ' is-open' : '')}>
            <DatePicker value={from} onChange={setFrom} aria-label={t('From')} />
            <span className="flxdn-muted">–</span>
            <DatePicker value={to} onChange={setTo} aria-label={t('To')} />
            <button type="button" className="flxdn-btn flxdn-btn--sm" onClick={apply}>
              {t('Apply')}
            </button>
          </div>
          {all && d ? (
            <div className="flxdn-filters__end">
              <a className="flxdn-btn" href={d.csvUrl}>
                <Ico name="download" />
                {t('Export CSV')}
              </a>
            </div>
          ) : null}
        </div>
        <div className={'flxdn-app' + (q.isFetching && d ? ' is-busy' : '')} aria-live="polite">
          {d ? (
            <Body d={d} fmt={fmt} c1={c1} c2={c2} />
          ) : q.isError ? (
            <div className="flxdn-card flxdn-empty">
              <p>{t('The data could not be loaded.')}</p>
              <button type="button" className="flxdn-btn" onClick={() => void q.refetch()}>
                {t('Try again')}
              </button>
            </div>
          ) : (
            <Loading />
          )}
        </div>
      </div>
    </Screen>
  );
}

function Card({ title, hint, children, extra }: { title: string; hint?: string; children: ReactNode; extra?: string }) {
  return (
    <section className={'flxdn-card' + (extra ?? '')}>
      <div className="flxdn-card__head">
        <div>
          <h2 className="flxdn-card__title">{title}</h2>
          {hint ? <p className="flxdn-card__hint">{hint}</p> : null}
        </div>
      </div>
      {children}
    </section>
  );
}

function Body({ d, fmt, c1, c2 }: { d: D; fmt: Fmt; c1: string; c2: string }) {
  const { t } = useDonate();
  if (!d.hasAny) {
    return (
      <div className="flxdn-card flxdn-emptystate">
        <Ico name="heart" size={36} />
        <h2>{t('No donations yet')}</h2>
        <p>{t('Donations appear here as soon as someone pays through a Flux Forms payment form. Payments taken before Flux Donate was installed can be imported on the Settings page.')}</p>
        {d.importUrl ? (
          <Go className="flxdn-btn flxdn-btn--primary" href={d.importUrl}>
            {t('Import existing payments')}
          </Go>
        ) : null}
      </div>
    );
  }
  const n = d.now;
  const p = d.prev;
  const gifts = (k: number) => fmt.num(k) + ' ' + (k === 1 ? t('gift') : t('gifts'));
  const delta = (now: number, prev: number) => {
    if (!prev) {
      return (
        <span className="flxdn-delta">
          <span className="flxdn-muted">{t('no gifts in the previous period')}</span>
        </span>
      );
    }
    const pc = Math.round(((now - prev) / prev) * 100);
    return (
      <span className={'flxdn-delta' + (pc > 0 ? ' is-up' : pc < 0 ? ' is-down' : '')}>
        <span>
          {pc > 0 ? '▲ +' : pc < 0 ? '▼ ' : ''}
          {fmt.num(pc)}%
        </span>{' '}
        <span className="flxdn-muted">{t('vs previous period')}</span>
      </span>
    );
  };
  const listLink = (k: 'dtype' | 'form', id: number) => (d.listUrl && (k === 'dtype' || id) ? `${d.listUrl}&${k}=${id}&from=${d.from}&to=${d.to}` : '');
  return (
    <div className="flxdn-body">
      <div className="flxdn-kpis">
        <Kpi label={t('Total donations')} value={fmt.money(n.total)}>
          {delta(n.total, p.total)}
          <br />
          {gifts(n.gifts)}
        </Kpi>
        <Kpi label={t('Donors')} value={fmt.num(n.donors)} accent="#2F6FA3">
          {t('all time: %s donors', fmt.num(d.allDonors))}
        </Kpi>
        <Kpi label={t('New donors')} value={fmt.num(n.newDonors)} accent="#5BA88A">
          {t('first gift in this period')}
          <br />
          {delta(n.newDonors, p.newDonors)}
        </Kpi>
        <Kpi label={t('Average gift')} value={fmt.money(n.average)} accent="#E0A526">
          {t('per gift')}
          <br />
          {delta(n.average, p.average)}
        </Kpi>
        <Kpi label={t('Recurring (monthly)')} value={fmt.money(d.mrr)} accent="#234E73">
          {t('monthly value of active ones')}
        </Kpi>
        <Kpi label={t('Active recurring donations')} value={fmt.num(d.activeSubscriptions)} accent="#7A8C96">
          {d.pastDueSubscriptions ? t('%s past due', fmt.num(d.pastDueSubscriptions)) : ' '}
        </Kpi>
      </div>
      <div className="flxdn-grid flxdn-grid--2-1">
        <Card title={t('Donations per day')} hint={t('Received amount, one-time and recurring')}>
<Chart
            kind="bar"
            stacked
            height={260}
            labels={d.series.map((r) => r.label)}
            series={[
              { label: t('One-time'), data: d.series.map((r) => r.oneTime), color: c1 },
              { label: t('Recurring'), data: d.series.map((r) => r.recurring), color: c2 },
            ]}
            format={(v) => fmt.money(v)}
            formatTick={(v) => fmt.short(v)}
            label={t('Donations per day')}
          />
        </Card>
        <Card title={t('One-time vs recurring')} hint={`${d.fromLabel} – ${d.toLabel}`}>
          <Split a={d.oneTime} b={d.recurring} fmt={fmt} c1={c1} c2={c2} gifts={gifts} />
        </Card>
      </div>
      <div className="flxdn-grid flxdn-grid--1-1" style={{ marginTop: 16 }}>
        <Card title={t('By donation type')}>
          <Bars rows={d.byType} fmt={fmt} gifts={gifts} link={(r) => listLink('dtype', r.id)} />
        </Card>
        <Card title={t('By form')}>
          <Bars rows={d.byForm} fmt={fmt} gifts={gifts} link={(r) => listLink('form', r.id)} />
        </Card>
      </div>
      <div style={{ marginTop: 16 }}>
        <Card title={t('Top donors')}>
          {d.topDonors.length ? (
            <div className="flxdn-tablewrap">
              <table className="flxdn-table">
                <thead>
                  <tr>
                    <th scope="col">#</th>
                    <th scope="col">{t('Donor')}</th>
                    <th scope="col" className="num">
                      {t('Gifts')}
                    </th>
                    <th scope="col" className="num">
                      {t('Amount')}
                    </th>
                  </tr>
                </thead>
                <tbody>
                  {d.topDonors.map((r, i) => (
                    <tr key={r.id || i}>
                      <td className="flxdn-muted flxdn-col-rank" data-label="#">
                        <span>{i + 1}</span>
                      </td>
                      <td className="flxdn-col-main">
                        {r.url ? <Go href={r.url}>{r.name}</Go> : r.name}
                        {r.contact && r.contact !== r.name ? <span className="flxdn-sub2">{r.contact}</span> : null}
                      </td>
                      <td className="num" data-label={t('Gifts')}>
                        <span>{fmt.num(r.gifts)}</span>
                      </td>
                      <td className="num" data-label={t('Amount')}>
                        <span>{fmt.money(r.amount)}</span>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : (
            <p className="flxdn-empty">{t('No donations in this period.')}</p>
          )}
        </Card>
      </div>
    </div>
  );
}

function Kpi({ label, value, accent, children }: { label: string; value: string; accent?: string; children: ReactNode }) {
  return (
    <div className="flxdn-kpi" style={accent ? ({ '--accent': accent } as React.CSSProperties) : undefined}>
      <div className="flxdn-kpi__label">{label}</div>
      <div className="flxdn-kpi__value" title={value}>
        {value}
      </div>
      <div className="flxdn-kpi__sub">{children}</div>
    </div>
  );
}

type Bar = D['byType'][number];
function Bars({ rows, fmt, gifts, link }: { rows: Bar[]; fmt: Fmt; gifts: (n: number) => string; link: (r: Bar) => string }) {
  const { t } = useDonate();
  if (!rows.length) return <p className="flxdn-empty">{t('No donations in this period.')}</p>;
  const max = rows.reduce((m, r) => Math.max(m, r.amount), 0) || 1;
  return (
    <ul className="flxdn-bars">
      {rows.map((r, i) => {
        const url = link(r);
        return (
          <li key={`${r.id}-${i}`}>
            <span className="flxdn-bars__label" title={r.label}>
              {url ? <Go href={url}>{r.label}</Go> : r.label}
            </span>
            <span className="flxdn-bars__val">
              {fmt.money(r.amount)}
              <small>{gifts(r.gifts)}</small>
            </span>
            <span className="flxdn-bars__track">
              <span style={{ width: `${Math.max(2, Math.round((100 * r.amount) / max))}%` }} />
            </span>
          </li>
        );
      })}
    </ul>
  );
}

function Split({ a, b, fmt, c1, c2, gifts }: { a: D['oneTime']; b: D['recurring']; fmt: Fmt; c1: string; c2: string; gifts: (n: number) => string }) {
  const { t } = useDonate();
  const total = a.amount + b.amount;
  const R = 52;
  const C = 2 * Math.PI * R;
  const la = total > 0 ? (C * a.amount) / total : 0;
  const li = (color: string, label: string, v: { amount: number; gifts: number }) => (
    <li>
      <i className="flxdn-sw" style={{ background: color }} />
      <span className="flxdn-paylegend__label">{label}</span>
      <span className="flxdn-paylegend__n">{gifts(v.gifts)}</span>
      <span className="flxdn-paylegend__amt">
        {fmt.money(v.amount)}
        {total ? ' · ' + fmt.num(Math.round((100 * v.amount) / total)) + '%' : ''}
      </span>
    </li>
  );
  return (
    <div className="flxdn-donut">
      <svg viewBox="0 0 140 140" role="img" aria-label={t('One-time vs recurring')}>
        <circle cx="70" cy="70" r={R} fill="none" stroke="var(--flxdn-cell0, #EEF3F0)" strokeWidth="18" />
        {total > 0 ? <circle cx="70" cy="70" r={R} fill="none" stroke={c1} strokeWidth="18" strokeDasharray={`${la} ${C - la}`} transform="rotate(-90 70 70)" /> : null}
        {total > 0 && b.amount > 0 ? <circle cx="70" cy="70" r={R} fill="none" stroke={c2} strokeWidth="18" strokeDasharray={`${C - la} ${la}`} strokeDashoffset={-la} transform="rotate(-90 70 70)" /> : null}
        <text x="70" y="68" textAnchor="middle" className="flxdn-donut__center">
          {total ? fmt.num(Math.round((100 * b.amount) / total)) + '%' : '–'}
        </text>
        <text x="70" y="86" textAnchor="middle" className="flxdn-donut__centersub">
          {t('Recurring')}
        </text>
      </svg>
      <ul className="flxdn-paylegend">
        {li(c1, t('One-time'), a)}
        {li(c2, t('Recurring'), b)}
      </ul>
    </div>
  );
}

