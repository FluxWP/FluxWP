// Backups › Dashboard: storage, counts, last/next backup, history chart, split by type, recent backups, events.
import { useMemo, useState } from 'react';
import { useFlxQuery } from '@fluxwp/graphql/react';
import { FlxBackupDashboard } from '@fluxwp/graphql/operations';
import type { FlxBackupDashboardQuery } from '@fluxwp/graphql/types';
import { Loading } from '@fluxwp/ui';
import { Chart } from '@fluxwp/ui/chart';
import { Badge, bytes, useBackup } from './shared';

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

function HistoryChart({ h }: { h: History }) {
  const { t, lang } = useBackup();
  const [range, setRange] = useState(30);
  const r = h.ranges.find((x) => x.days === range) ?? h.ranges[0];
  const days = useMemo(() => h.days.slice(-(r?.days ?? 30)), [h.days, r?.days]);
  if (!r) return null;
  const nf = new Intl.NumberFormat(lang);
  const quota = r.showQuota && h.quota <= r.max;
  const empty = days.every((d) => d.values.every((v) => !v));
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
      <Chart
        kind="area"
        stacked
        height={260}
        labels={days.map((d) => d.fullLabel)}
        tickLabel={(_, i) => days[i]?.label ?? ''}
        series={[
          ...h.series.map((s, i) => ({ label: s.name, color: s.color, data: days.map((d) => d.values[i] ?? 0) })),
          ...(quota ? [{ label: t('Quota') + ': ' + h.quotaLabel, color: 'var(--flux-danger)', dashed: true, data: days.map(() => h.quota) }] : []),
        ]}
        legend
        points={empty ? false : undefined}
        max={empty ? 1.5 * 1024 * 1024 : undefined}
        format={(v) => bytes(v, lang)}
        label={t('Storage history')}
      />
    </section>
  );
}

function Donut({ by, total }: { by: FlxBackupDashboardQuery['flxBackupOverview']['byType']; total: number }) {
  const { t, lang } = useBackup();
  const pct = new Intl.NumberFormat(lang, { style: 'percent', maximumFractionDigits: 0 });
  const parts = by.filter((x) => x.bytes > 0);
  if (total <= 0 || parts.length === 0) return <p className="flxy-empty">{t('No backups yet.')}</p>;
  return (
    <div className="flxy-donut">
      <div className="flxy-donut-chart">
        <Chart kind="doughnut" height={170} legend={false} labels={parts.map((x) => x.label)} colors={parts.map((x) => x.color)} series={[{ label: t('By type'), data: parts.map((x) => x.bytes) }]} format={(v) => bytes(v, lang)} label={t('By type')} />
      </div>
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
