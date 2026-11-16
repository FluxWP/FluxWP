// Dashboard: totals, daily activity, per-network numbers, followers, best posts, upcoming posts, errors.
import { useState } from 'react';
import { useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { FlxSocialStats, FlxSocialStatsRefresh } from '@fluxwp/graphql/operations';
import { Loading } from '@fluxwp/ui';
import { NetDot, StatusBadge, STATUS_KEYS, errorText, excerpt, num, useSocial, type Post } from './common';
import { useQueryClient } from '@tanstack/react-query';

const DAYS = [7, 30, 90];

export function Dashboard() {
  const s = useSocial();
  const { t, boot, locale } = s;
  const qc = useQueryClient();
  const { scope } = useGraphQL();
  const [days, setDays] = useState(30);
  const q = useFlxQuery(FlxSocialStats, { days });
  const refresh = useFlxMutation(FlxSocialStatsRefresh, {
    onSuccess: (r) => {
      s.toast(t('{n} records updated.', { n: r.flxSocialStatsRefresh?.updated ?? 0 }));
      void qc.invalidateQueries({ queryKey: [scope, 'gql', 'FlxSocialStats'] });
      void q.refetch();
    },
    onError: (e) => s.toast(errorText(e, t('Something went wrong.')), 'error'),
  });
  if (q.isLoading) return <Loading />;
  if (q.isError || !q.data) return <div className="flxsm-notice is-error">{errorText(q.error, t('Something went wrong.'))}</div>;
  const st = q.data.flxSocialStats;
  const tot = st.totals;
  const nets = boot.flxSocialConfig.networks;
  const accs = s.accounts;

  if (!accs.length) {
    return (
      <div className="flxsm-empty">
        <h2>{t('No accounts yet')}</h2>
        <p>{t('No accounts connected yet. Enter the app keys first, then connect accounts.')}</p>
        {boot.flxSocialConfig.canManage ? (
          <button type="button" className="flxsm-btn is-primary" onClick={() => s.go('settings', { tab: 'apps' })}>
            {t('App keys')}
          </button>
        ) : (
          <p>{t('Accounts are connected by an administrator.')}</p>
        )}
      </div>
    );
  }

  const max = Math.max(1, ...st.byDay.map((d) => d.networks.reduce((a, n) => a + n.count, 0)));
  const tiles: { label: string; value: string; sub: string; warn?: boolean }[] = [
    { label: t('Posts'), value: num(tot.published, locale), sub: t('last {n} days', { n: st.days }) },
    { label: t('Engagement'), value: num(tot.engagement, locale), sub: t('likes + comments + shares') },
    { label: t('Views'), value: num(tot.views, locale), sub: t('reach / impressions') },
    {
      label: t('Followers'),
      value: num(tot.followers, locale),
      sub: tot.followersDelta ? (tot.followersDelta > 0 ? '+' : '') + num(tot.followersDelta, locale) + ' ' + t('this period') : t('no change'),
    },
    { label: t('Scheduled posts'), value: num(tot.scheduled, locale), sub: t('{n} awaiting approval', { n: tot.pending }) + ' · ' + t('{n} drafts', { n: tot.drafts }) },
    {
      label: t('Errors'),
      value: num(tot.failed, locale),
      sub: tot.failed ? t('details below') : t('no issues'),
      warn: tot.failed > 0,
    },
  ];

  return (
    <div className="flxsm-dash">
      <div className="flxsm-bar">
        <div className="flxsm-seg" role="group">
          {DAYS.map((d) => (
            <button key={d} type="button" className={d === days ? 'is-on' : ''} onClick={() => setDays(d)}>
              {t('{n} days', { n: d })}
            </button>
          ))}
        </div>
        {tot.successRate !== null && <span className="flxsm-muted">{t('Success {n}%', { n: tot.successRate })}</span>}
        {boot.flxSocialConfig.canManage && (
          <button type="button" className="flxsm-btn" disabled={refresh.isPending} onClick={() => refresh.mutate({ input: {} })}>
            {refresh.isPending ? t('Trying…') : t('Update metrics now')}
          </button>
        )}
      </div>
      <div className="flxsm-tiles">
        {tiles.map((x) => (
          <div key={x.label} className={'flxsm-tile' + (x.warn ? ' is-warn' : '')}>
            <span className="flxsm-muted">{x.label}</span>
            <strong>{x.value}</strong>
            <span className="flxsm-muted">{x.sub}</span>
          </div>
        ))}
      </div>

      <section className="flxsm-card">
        <h2>{t('Daily posts')}</h2>
        {tot.published + tot.failed === 0 ? (
          <p className="flxsm-muted">{t('No posts in this period.')}</p>
        ) : (
          <div className="flxsm-chart" role="img" aria-label={t('Daily posts')}>
            {st.byDay.map((d) => {
              const total = d.networks.reduce((a, n) => a + n.count, 0);
              return (
                <div key={d.day} className="flxsm-col" title={d.day + ': ' + total}>
                  <div className="flxsm-stack" style={{ height: `${(total / max) * 100}%` }}>
                    {d.networks.map((n) => (
                      <i key={n.provider} style={{ flex: n.count, background: nets.find((x) => x.id === n.provider)?.color ?? '#888' }} />
                    ))}
                  </div>
                </div>
              );
            })}
          </div>
        )}
        <div className="flxsm-legend">
          {st.byNetwork.map((n) => (
            <span key={n.provider}>
              <NetDot provider={n.provider} size={14} /> {nets.find((x) => x.id === n.provider)?.label}
            </span>
          ))}
        </div>
      </section>

      <div className="flxsm-grid2">
        <section className="flxsm-card">
          <h2>{t('By network')}</h2>
          {st.byNetwork.length ? (
            <table className="flxsm-table">
              <thead>
                <tr>
                  <th>{t('Network')}</th>
                  <th>{t('Posts')}</th>
                  <th>{t('Errors')}</th>
                  <th>{t('Engagement')}</th>
                  <th>{t('Views')}</th>
                </tr>
              </thead>
              <tbody>
                {st.byNetwork.map((n) => (
                  <tr key={n.provider}>
                    <td>
                      <NetDot provider={n.provider} size={16} /> {nets.find((x) => x.id === n.provider)?.label}
                    </td>
                    <td>{num(n.published, locale)}</td>
                    <td>{num(n.failed, locale)}</td>
                    <td>{num(n.engagement, locale)}</td>
                    <td>{num(n.views, locale)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          ) : (
            <p className="flxsm-muted">{t('No posts in this period.')}</p>
          )}
        </section>
        <section className="flxsm-card">
          <h2>{t('Followers')}</h2>
          {st.followers.length ? (
            <ul className="flxsm-followers">
              {st.followers.map((f) => (
                <li key={f.accountId}>
                  <NetDot provider={f.provider} size={18} />
                  <span className="flxsm-grow">{f.name}</span>
                  <Spark points={f.series.map((p) => p.count)} />
                  <strong>{num(f.now, locale)}</strong>
                  <span className={'flxsm-delta' + (f.delta < 0 ? ' is-neg' : '')}>{f.delta ? (f.delta > 0 ? '+' : '') + num(f.delta, locale) : '–'}</span>
                </li>
              ))}
            </ul>
          ) : (
            <p className="flxsm-muted">{t('Follower counts are collected once a day; first data appears tomorrow.')}</p>
          )}
        </section>
      </div>

      <div className="flxsm-grid2">
        <section className="flxsm-card">
          <h2>{t('Top engaging posts')}</h2>
          {st.top.length ? (
            <ul className="flxsm-list">
              {st.top.map((p) => (
                <li key={p.targetId}>
                  <NetDot provider={p.provider} size={18} />
                  <span className="flxsm-grow">
                    {p.url ? (
                      <a href={p.url} target="_blank" rel="noreferrer">
                        {p.title || t('(untitled)')}
                      </a>
                    ) : (
                      p.title || t('(untitled)')
                    )}
                    <small className="flxsm-muted"> {p.account}</small>
                  </span>
                  <span className="flxsm-muted">
                    {num(p.engagement, locale)} · {num(p.views, locale)}
                  </span>
                </li>
              ))}
            </ul>
          ) : (
            <p className="flxsm-muted">{t('Metrics are collected twice a day after a post is published.')}</p>
          )}
          <p className="flxsm-muted flxsm-small">
            {st.lastMetrics ? t('Metrics last updated: {t}', { t: s.fmt(st.lastMetrics.timestamp) }) : t('Metrics not collected yet (once a day, at night).')}
          </p>
        </section>
        <section className="flxsm-card">
          <h2>{t('Upcoming posts')}</h2>
          {st.upcoming.length ? (
            <ul className="flxsm-list">
              {st.upcoming.map((p: Post) => (
                <li key={p.databaseId}>
                  <button type="button" className="flxsm-link flxsm-grow" onClick={() => s.detail(p)}>
                    {excerpt(p, t)}
                  </button>
                  <span className="flxsm-muted">{p.scheduledAt?.label}</span>
                  <StatusBadge status={p.status} label={t(STATUS_KEYS[p.status])} />
                </li>
              ))}
            </ul>
          ) : (
            <p className="flxsm-muted">{t('No scheduled posts.')}</p>
          )}
        </section>
      </div>

      {st.errors.length > 0 && (
        <section className="flxsm-card is-warn">
          <h2>{t('Recent errors')}</h2>
          <ul className="flxsm-list">
            {st.errors.map((e) => (
              <li key={e.targetId}>
                <NetDot provider={e.provider} size={18} />
                <span className="flxsm-grow">
                  <strong>{e.account}</strong> {e.error}
                </span>
                <span className="flxsm-muted">{e.at ? s.fmt(e.at.timestamp) : ''}</span>
              </li>
            ))}
          </ul>
        </section>
      )}
    </div>
  );
}

function Spark({ points }: { points: number[] }) {
  if (points.length < 2) return <span className="flxsm-spark" />;
  const min = Math.min(...points);
  const span = Math.max(1, Math.max(...points) - min);
  const d = points.map((v, i) => `${i ? 'L' : 'M'}${(i / (points.length - 1)) * 80},${22 - ((v - min) / span) * 20}`).join('');
  return (
    <svg className="flxsm-spark" viewBox="0 0 80 24" width={80} height={24} aria-hidden="true">
      <path d={d} fill="none" stroke="currentColor" strokeWidth="1.5" />
    </svg>
  );
}
