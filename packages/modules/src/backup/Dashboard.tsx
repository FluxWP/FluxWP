// Backups › Dashboard: storage, counts, last/next backup, history chart, split by type, recent backups, events.
import { useMemo, useState } from 'react';
import { useFlxQuery } from '@fluxwp/graphql/react';
import { FlxBackupDashboard } from '@fluxwp/graphql/operations';
import type { FlxBackupDashboardQuery } from '@fluxwp/graphql/types';
import { Loading } from '@fluxwp/ui';
import { Badge, useBackup } from './shared';

type History = FlxBackupDashboardQuery['flxBackupHistory'];

export function Dashboard() {
  const { t, tn, lang, go } = useBackup();
  const q = useFlxQuery(FlxBackupDashboard, {});
  if (q.isLoading) return <Loading />;
  if (q.isError || !q.data) return <div className="flxy-notice is-error">{q.error?.message || t('An error occurred.')}</div>;
  const { flxBackupOverview: o, flxBackupHistory: h } = q.data;
  const pre = o.preScope === 'OFF' ? t('Pre-update backup: off') : o.preScope === 'FLX' ? t('Pre-update backup: FluxWP packages') : t('Pre-update backup: on');
  const nf = new Intl.NumberFormat(lang);
  return (
    <>
      <div className="flxy-cards">
        <section className={'flxy-card flxy-level-' + o.level}>
          <h2>{t('Storage')}</h2>
          <p className="flxy-big">
            {o.usedLabel} <span>/ {o.quotaLabel}</span>
          </p>
          <div className="flxy-bar" role="img" aria-label={t('%s%% of the quota is used', nf.format(Math.round(o.percent)))}>
            {o.byType
              .filter((x) => x.bytes > 0)
              .map((x) => (
                <span key={x.type} style={{ width: (100 * x.bytes) / Math.max(1, o.quotaBytes) + '%', background: x.color }} title={x.label + ': ' + x.bytesLabel} />
              ))}
          </div>
          <p className="flxy-sub">{t('%1$s%% used · %2$s free', nf.format(Math.round(o.percent)), o.freeLabel)}</p>
        </section>
        <section className="flxy-card">
          <h2>{t('Backups')}</h2>
          <p className="flxy-big">{nf.format(o.backupCount)}</p>
          <p className="flxy-sub">{tn('%s locked', '%s locked', o.lockedCount, nf.format(o.lockedCount))}</p>
        </section>
        <section className="flxy-card">
          <h2>{t('Last successful backup')}</h2>
          {o.last ? (
            <>
              <p className="flxy-big is-sm">{o.last.created?.relative ?? '—'}</p>
              <p className="flxy-sub">{o.last.title + ' · ' + (o.last.created?.label ?? '')}</p>
            </>
          ) : (
            <>
              <p className="flxy-big is-sm">—</p>
              <p className="flxy-sub">{t('No backups yet.')}</p>
            </>
          )}
        </section>
        <section className="flxy-card">
          <h2>{t('Next scheduled')}</h2>
          {o.nextRun ? (
            <>
              <p className="flxy-big is-sm">{o.nextRun.label}</p>
              <p className="flxy-sub">{o.scheduleKind === 'WEEKLY' ? t('Weekly full backup') : t('Daily full backup')}</p>
            </>
          ) : (
            <>
              <p className="flxy-big is-sm">{t('Off')}</p>
              <p className="flxy-sub">
                <button type="button" className="flxy-link" onClick={() => go('settings')}>
                  {t('Turn on schedule')}
                </button>
              </p>
            </>
          )}
          <p className={'flxy-sub flxy-pre ' + (o.preScope === 'OFF' ? 'is-off' : 'is-on')}>{pre}</p>
        </section>
      </div>

      <div className="flxy-grid">
        <HistoryChart h={h} />
        <section className="flxy-card">
          <h2>{t('By type')}</h2>
          <Donut by={o.byType} total={o.usedBytes} />
        </section>
      </div>

      <div className="flxy-grid">
        <section className="flxy-card">
          <div className="flxy-card-head">
            <h2>{t('Recent backups')}</h2>
            <button type="button" className="flxy-link" onClick={() => go('list')}>
              {t('View all')}
            </button>
          </div>
          {o.recent.length === 0 ? (
            <p className="flxy-empty">{t('No backups yet. Take the first one with "Back up now"; a backup is taken automatically before plugin and theme updates.')}</p>
          ) : (
            <ul className="flxy-recent">
              {o.recent.map((r) => (
                <li key={r.id}>
                  <Badge label={r.typeLabel} color={r.typeColor} />
                  <span className="flxy-recent-title">
                    <button type="button" className="flxy-link" onClick={() => go(`detail:${r.id}`)}>
                      {r.title}
                    </button>
                    <small>{r.created?.label}</small>
                  </span>
                  <span className="flxy-recent-size">
                    {r.status === 'OK' ? r.sizeLabel : r.status === 'RUNNING' ? t('Running') : <span className="flxy-failed" title={r.error}>{t('Failed')}</span>}
                  </span>
                </li>
              ))}
            </ul>
          )}
        </section>
        <section className="flxy-card">
          <h2>{t('Recent events')}</h2>
          {o.events.length === 0 ? (
            <p className="flxy-empty">{t('No events yet.')}</p>
          ) : (
            <ul className="flxy-events">
              {o.events.map((e, i) => (
                <li key={i} className={'flxy-ev-' + e.level}>
                  <time>{e.at?.label}</time>
                  <span>{e.message}</span>
                </li>
              ))}
            </ul>
          )}
        </section>
      </div>
    </>
  );
}

const W = 760;
const H = 260;
const PAD = { l: 56, r: 16, t: 14, b: 26 };

function HistoryChart({ h }: { h: History }) {
  const { t, lang } = useBackup();
  const [range, setRange] = useState(30);
  const [hover, setHover] = useState<number | null>(null);
  const r = h.ranges.find((x) => x.days === range) ?? h.ranges[0];
  const days = useMemo(() => h.days.slice(-(r?.days ?? 30)), [h.days, r?.days]);
  if (!r) return null;
  const iw = W - PAD.l - PAD.r;
  const ih = H - PAD.t - PAD.b;
  const x = (i: number) => PAD.l + (days.length > 1 ? (iw * i) / (days.length - 1) : iw / 2);
  const y = (v: number) => PAD.t + ih - (ih * Math.min(v, r.max)) / Math.max(1, r.max);
  // Stacked areas, bottom series first.
  const areas = h.series.map((s, si) => {
    const top = days.map((d, i) => [x(i), y(d.values.slice(0, si + 1).reduce((a, b) => a + b, 0))] as const);
    const bottom = days.map((d, i) => [x(i), y(d.values.slice(0, si).reduce((a, b) => a + b, 0))] as const).reverse();
    const pts = [...top, ...bottom].map((p) => p[0].toFixed(1) + ',' + p[1].toFixed(1)).join(' ');
    return { s, pts };
  });
  const step = Math.max(1, Math.ceil(days.length / 7));
  const hd = hover === null ? null : days[hover];
  const nf = new Intl.NumberFormat(lang);
  return (
    <section className="flxy-card">
      <div className="flxy-card-head">
        <h2>{t('Storage history')}</h2>
        <div className="flxy-range" role="group" aria-label={t('Time range')}>
          {h.ranges.map((x) => (
            <button key={x.days} type="button" className={'flxy-btn' + (x.days === r.days ? ' is-active' : '')} aria-pressed={x.days === r.days} onClick={() => setRange(x.days)}>
              {t('%s days', nf.format(x.days))}
            </button>
          ))}
        </div>
      </div>
      <div className="flxy-legend">
        {h.series.map((s) => (
          <span key={s.key}>
            <i style={{ background: s.color }} />
            {s.name}
          </span>
        ))}
        {r.showQuota && (
          <span>
            <i className="flxy-quota-key" />
            {t('Quota')}
          </span>
        )}
      </div>
      <div className="flxy-chart" onMouseLeave={() => setHover(null)}>
        <svg viewBox={`0 0 ${W} ${H}`} role="img" aria-label={t('Storage history')}>
          {r.ticks.map((tk) => (
            <g key={tk.value}>
              <line className="gl" x1={PAD.l} x2={W - PAD.r} y1={y(tk.value)} y2={y(tk.value)} />
              <text className="tk" x={PAD.l - 8} y={y(tk.value) + 4} textAnchor="end">
                {tk.label}
              </text>
            </g>
          ))}
          {days.map((d, i) =>
            i % step === 0 ? (
              <text key={d.key} className="tk" x={x(i)} y={H - 8} textAnchor="middle">
                {d.label}
              </text>
            ) : null,
          )}
          {areas.map((a) => (
            <polygon key={a.s.key} points={a.pts} fill={a.s.color} fillOpacity={0.85} />
          ))}
          {r.showQuota && h.quota <= r.max && (
            <>
              <line className="ql" x1={PAD.l} x2={W - PAD.r} y1={y(h.quota)} y2={y(h.quota)} />
              <text className="qt" x={W - PAD.r} y={y(h.quota) - 4} textAnchor="end">
                {t('Quota')}: {h.quotaLabel}
              </text>
            </>
          )}
          {hover !== null && <line className="cr" x1={x(hover)} x2={x(hover)} y1={PAD.t} y2={PAD.t + ih} />}
          <rect
            x={PAD.l}
            y={PAD.t}
            width={iw}
            height={ih}
            fill="transparent"
            onMouseMove={(e) => {
              const box = e.currentTarget.getBoundingClientRect();
              const f = (e.clientX - box.left) / Math.max(1, box.width);
              setHover(Math.max(0, Math.min(days.length - 1, Math.round(f * (days.length - 1)))));
            }}
          />
        </svg>
        {hd && hover !== null && (
          <div className="flxy-tip" style={{ left: `${Math.min(70, (100 * x(hover)) / W)}%`, top: 8 }}>
            <strong>{hd.fullLabel}</strong>
            {h.series.map((s, i) => (
              <div key={s.key}>
                <i style={{ background: s.color }} />
                {s.name}
                <b>{hd.valueLabels[i]}</b>
              </div>
            ))}
            <div>
              {t('Total %s', '')}
              <b>{hd.totalLabel}</b>
            </div>
          </div>
        )}
      </div>
    </section>
  );
}

function Donut({ by, total }: { by: FlxBackupDashboardQuery['flxBackupOverview']['byType']; total: number }) {
  const { t, lang } = useBackup();
  const R = 60;
  const C = 2 * Math.PI * R;
  let off = 0;
  const pct = new Intl.NumberFormat(lang, { style: 'percent', maximumFractionDigits: 0 });
  return (
    <div className="flxy-donut">
      <svg viewBox="0 0 160 160" role="img" aria-label={t('By type')}>
        <circle cx="80" cy="80" r={R} fill="none" stroke="var(--flux-surface2)" strokeWidth="22" />
        {total > 0 &&
          by
            .filter((x) => x.bytes > 0)
            .map((x) => {
              const len = (C * x.bytes) / total;
              const el = <circle key={x.type} cx="80" cy="80" r={R} fill="none" stroke={x.color} strokeWidth="22" strokeDasharray={`${len} ${C - len}`} strokeDashoffset={-off} transform="rotate(-90 80 80)" />;
              off += len;
              return el;
            })}
      </svg>
      <table className="flxy-donut-table">
        <tbody>
          {by.map((x) => (
            <tr key={x.type}>
              <td>
                <i style={{ background: x.color }} /> {x.label}
              </td>
              <td>{x.bytesLabel}</td>
              <td>{pct.format(x.share / 100)}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
