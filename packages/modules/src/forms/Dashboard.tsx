// Forms › Dashboard: responses per day against the previous period, views, conversion, payments, busy hours,
// forms and the latest responses. Numbers come from flxFormsDashboard (the classic dashboard payload).
import { useState } from 'react';
import { useFlxQuery } from '@fluxwp/graphql/react';
import { FlxFormsDashboard, FlxFormsFormOptions } from '@fluxwp/graphql/operations';
import { Loading } from '@fluxwp/ui';
import { Chart } from '@fluxwp/ui/chart';
import { errorText, useForms } from './common';

interface Stats {
  from: string;
  to: string;
  days: number;
  currency: string;
  csvUrl?: string;
  totals: { n: number; unread: number; unreadAll: number; views: number | null; viewsSince: string | null; conversion: number | null };
  prev: { n: number; views: number | null; paid: number | null };
  series: { d: string; n: number; p: number }[];
  forms: { id: number; title: string; exists: boolean; n: number; unread: number; total: number; views: number | null; conv: number | null; last: string }[];
  heat: [number, number, number][];
  payments: { source?: string; paid: { cur: string; amount: number; n: number }[]; status: { s: string; n: number }[]; prevPaid: number | null };
  recent: { id: number; form_id: number; form: string; track: string; date: string; unread: boolean; preview: string }[];
}

const RANGES: { days: number; label: string }[] = [
  { days: 7, label: '7 days' },
  { days: 30, label: '30 days' },
  { days: 90, label: '90 days' },
  { days: 365, label: '12 months' },
];

function day(d: Date): string {
  const p = (n: number) => String(n).padStart(2, '0');
  return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate());
}

export function DashboardPage() {
  const { t, go, num } = useForms();
  const [days, setDays] = useState(30);
  const [form, setForm] = useState(0);
  // The first view (30 days, every form) is the query the page preloads; the others carry a range.
  const vars =
    days === 30 && !form
      ? {}
      : (() => {
          const to = new Date();
          const from = new Date();
          from.setDate(to.getDate() - (days - 1));
          return { from: day(from), to: day(to), form: form || undefined };
        })();
  const q = useFlxQuery(FlxFormsDashboard, vars);
  const options = useFlxQuery(FlxFormsFormOptions, {});
  const s = q.data?.flxFormsDashboard as Stats | undefined;

  const pct = (now: number, before: number | null | undefined) => {
    if (before === null || before === undefined || before === 0) return null;
    return Math.round(((now - before) / before) * 100);
  };
  const paid = s?.payments.paid ?? [];
  const paidMain = paid[0];
  const fmtMoney = (amount: number, cur: string) => {
    try {
      return new Intl.NumberFormat(undefined, { style: 'currency', currency: cur, maximumFractionDigits: 0 }).format(amount);
    } catch {
      return num(amount) + ' ' + cur;
    }
  };

  return (
    <div className="flxfrm-dash">
      <div className="flxfrm-toolbar">
        <p className="flxfrm-muted">{t('Responses, form views, conversion and payments at a glance.')}</p>
        <div className="flxfrm-inline">
          <div className="flxfrm-seg" role="group">
            {RANGES.map((r) => (
              <button key={r.days} type="button" className={days === r.days ? 'is-on' : ''} onClick={() => setDays(r.days)}>
                {t(r.label)}
              </button>
            ))}
          </div>
          <select aria-label={t('All forms')} value={form} onChange={(e) => setForm(Number(e.target.value))}>
            <option value={0}>{t('All forms')}</option>
            {(options.data?.flxFormsFormOptions ?? []).map((f) => (
              <option key={f.databaseId} value={f.databaseId}>
                {f.title}
              </option>
            ))}
          </select>
          {s?.csvUrl ? (
            <a className="flxfrm-btn" href={s.csvUrl}>
              {t('Download CSV')}
            </a>
          ) : null}
        </div>
      </div>

      {q.isLoading && !s ? <Loading /> : null}
      {q.isError ? <p className="flxfrm-bad">{errorText(q.error, t('Something went wrong.'))}</p> : null}
      {s ? (
        <>
          <div className="flxfrm-kpis">
            <Kpi label={t('Responses')} value={num(s.totals.n)} delta={pct(s.totals.n, s.prev.n)} hint={t('vs previous period')} />
            <Kpi label={t('Unread')} value={num(s.totals.unreadAll)} onClick={() => go('responses')} hint={t('All unread')} warn={s.totals.unreadAll > 0} />
            <Kpi
              label={t('Form views')}
              value={s.totals.views === null ? '—' : num(s.totals.views)}
              delta={s.totals.views === null ? null : pct(s.totals.views, s.prev.views)}
              hint={s.totals.viewsSince ? t('since {d}', { d: s.totals.viewsSince }) : t('Views are kept for about eight days.')}
            />
            <Kpi label={t('Conversion')} value={s.totals.conversion === null ? '—' : '%' + s.totals.conversion} />
            <Kpi label={t('Paid')} value={paidMain ? fmtMoney(paidMain.amount, paidMain.cur) : '—'} delta={paidMain ? pct(paidMain.amount, s.prev.paid) : null} hint={paid.length > 1 ? paid.slice(1).map((p) => fmtMoney(p.amount, p.cur)).join(' · ') : undefined} />
          </div>

          <div className="flxfrm-card">
            <h2>{t('Responses per day')}</h2>
            <Chart
              kind="bar"
              height={240}
              labels={s.series.map((x) => x.d.slice(5))}
              series={[
                { label: t('This period'), data: s.series.map((x) => x.n), color: 'var(--flux-primary)' },
                { label: t('Previous period'), data: s.series.map((x) => x.p), kind: 'line', dashed: true, color: 'var(--flux-text-muted)' },
              ]}
            />
          </div>

          <div className="flxfrm-grid2">
            <div className="flxfrm-card">
              <h2>{t('Forms')}</h2>
              <table className="flxfrm-table">
                <thead>
                  <tr>
                    <th>{t('Name')}</th>
                    <th className="is-num">{t('Responses')}</th>
                    <th className="is-num">{t('Views')}</th>
                    <th className="is-num">{t('Conversion')}</th>
                  </tr>
                </thead>
                <tbody>
                  {s.forms.slice(0, 12).map((f) => (
                    <tr key={f.id} className="is-link" tabIndex={0} onClick={() => go('responses', { form: f.id })} onKeyDown={(e) => e.key === 'Enter' && go('responses', { form: f.id })}>
                      <td>
                        {f.exists ? f.title : <span className="flxfrm-muted">{t('Deleted form')} #{f.id}</span>}
                        {f.unread ? <span className="flxfrm-badge is-new">{t('{n} unread', { n: num(f.unread) })}</span> : null}
                      </td>
                      <td className="is-num">{num(f.n)}</td>
                      <td className="is-num">{f.views === null ? '—' : num(f.views)}</td>
                      <td className="is-num">{f.conv === null ? '—' : '%' + f.conv}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <div className="flxfrm-card">
              <h2>{t('Latest responses')}</h2>
              {s.recent.length ? (
                <ul className="flxfrm-recent">
                  {s.recent.map((r) => (
                    <li key={r.id}>
                      <button type="button" onClick={() => go('responses', { entry: r.id })}>
                        <span className="flxfrm-recent__top">
                          {r.unread ? <span className="flxfrm-dot" aria-label={t('Unread')} /> : null}
                          <strong>{r.form || '#' + r.form_id}</strong>
                          <span className="flxfrm-muted">{r.date.replace('T', ' ').slice(0, 16)}</span>
                        </span>
                        <span className="flxfrm-muted flxfrm-clip">{r.preview || r.track}</span>
                      </button>
                    </li>
                  ))}
                </ul>
              ) : (
                <p className="flxfrm-muted">{t('No responses in this period.')}</p>
              )}
            </div>
          </div>

          <div className="flxfrm-grid2">
            <div className="flxfrm-card">
              <h2>{t('Busy hours')}</h2>
              <Heat heat={s.heat} />
            </div>
            {s.payments.status.length ? (
              <div className="flxfrm-card">
                <h2>{t('Payment status')}</h2>
                <Chart kind="doughnut" height={220} labels={s.payments.status.map((x) => t(x.s))} series={[{ label: t('Payments'), data: s.payments.status.map((x) => x.n) }]} />
              </div>
            ) : null}
          </div>
        </>
      ) : null}
    </div>
  );
}

function Kpi({ label, value, delta, hint, warn, onClick }: { label: string; value: string; delta?: number | null; hint?: string; warn?: boolean; onClick?: () => void }) {
  const body = (
    <>
      <span className="flxfrm-kpi__label">{label}</span>
      <span className="flxfrm-kpi__value">{value}</span>
      {delta !== null && delta !== undefined ? <span className={'flxfrm-kpi__delta ' + (delta >= 0 ? 'is-up' : 'is-down')}>{(delta >= 0 ? '+' : '') + delta + '%'}</span> : null}
      {hint ? <span className="flxfrm-kpi__hint">{hint}</span> : null}
    </>
  );
  return onClick ? (
    <button type="button" className={'flxfrm-kpi' + (warn ? ' is-warn' : '')} onClick={onClick}>
      {body}
    </button>
  ) : (
    <div className="flxfrm-kpi">{body}</div>
  );
}

function Heat({ heat }: { heat: [number, number, number][] }) {
  const { t } = useForms();
  const max = Math.max(1, ...heat.map((h) => h[2]));
  const at = new Map(heat.map((h) => [h[0] + '-' + h[1], h[2]]));
  const days = [t('Mon'), t('Tue'), t('Wed'), t('Thu'), t('Fri'), t('Sat'), t('Sun')];
  return (
    <div className="flxfrm-heat" role="table">
      {days.map((d, di) => (
        <div key={d} className="flxfrm-heat__row" role="row">
          <span className="flxfrm-heat__day">{d}</span>
          {Array.from({ length: 24 }, (_, h) => {
            const v = at.get(di + '-' + h) ?? 0;
            return <span key={h} className="flxfrm-heat__cell" title={d + ' ' + String(h).padStart(2, '0') + ':00 · ' + v} style={{ opacity: v ? 0.18 + (0.82 * v) / max : 1, background: v ? 'var(--flux-primary)' : undefined }} />;
          })}
        </div>
      ))}
    </div>
  );
}
