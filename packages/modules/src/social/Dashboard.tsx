// Dashboard: totals, daily activity, per-network numbers, followers, best posts, upcoming posts, errors.
import { useState, type ReactNode } from 'react';
import { createPortal } from 'react-dom';
import { useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { FlxSocialStats, FlxSocialStatsRefresh } from '@fluxwp/graphql/operations';
import { Icon, Loading } from '@fluxwp/ui';
import { Avatar, NetDot, StatusBadge, STATUS_KEYS, errorText, excerpt, num, useSocial, type Post } from './common';
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
  const top = s.topSlot
    ? createPortal(
        <>
          <div className="flxsm-seg is-small" role="group">
            {DAYS.map((d) => (
              <button key={d} type="button" className={d === days ? 'is-on' : ''} onClick={() => setDays(d)}>
                {t('{n} days', { n: d })}
              </button>
            ))}
          </div>
          {boot.flxSocialConfig.canManage && (
            <button type="button" className="flxsm-iconbtn" title={t('Update metrics now')} aria-label={t('Update metrics now')} disabled={refresh.isPending} onClick={() => refresh.mutate({ input: {} })}>
              <Icon name="update" size={16} />
            </button>
          )}
        </>,
        s.topSlot,
      )
    : null;
  if (q.isLoading)
    return (
      <>
        {top}
        <Loading />
      </>
    );
  if (q.isError || !q.data) return <div className="flxsm-notice is-error">{errorText(q.error, t('Something went wrong.'))}</div>;
  const st = q.data.flxSocialStats;
  const tot = st.totals;
  const nets = boot.flxSocialConfig.networks;
  const accs = s.accounts;

  const color = (p: string) => nets.find((x) => x.id === p)?.color ?? '#888';
  const netLabel = (p: string) => nets.find((x) => x.id === p)?.label ?? p;
  const tiles: { icon: string; label: string; value: string; sub: string; cls?: string }[] = [
    { icon: 'airplane', label: t('Published'), value: num(tot.published, locale), sub: tot.successRate !== null ? t('Success {n}%', { n: tot.successRate }) : t('last {n} days', { n: st.days }) },
    { icon: 'heart', label: t('Engagement'), value: num(tot.engagement, locale), sub: t('likes + comments + shares') },
    { icon: 'visibility', label: t('Views'), value: num(tot.views, locale), sub: t('reach / impressions') },
    {
      icon: 'users',
      label: t('Followers'),
      value: num(tot.followers, locale),
      sub: tot.followersDelta ? (tot.followersDelta > 0 ? '+' : '') + num(tot.followersDelta, locale) + ' ' + t('this period') : t('no change'),
      cls: tot.followersDelta > 0 ? 'is-up' : '',
    },
    { icon: 'clock', label: t('Scheduled'), value: num(tot.scheduled, locale), sub: tot.pending ? t('{n} awaiting approval', { n: tot.pending }) : tot.drafts ? t('{n} drafts', { n: tot.drafts }) : '' },
    { icon: 'warning', label: t('Failed'), value: num(tot.failed, locale), sub: tot.failed ? t('details below') : t('no issues'), cls: tot.failed ? 'is-bad' : '' },
  ];
  const card = (title: string, body: ReactNode, extra?: ReactNode) => (
    <section className="flxsm-card">
      <div className="flxsm-card-h">
        <h3>{title}</h3>
        {extra}
      </div>
      {body}
    </section>
  );
  const empty = (text: string) => <p className="flxsm-muted flxsm-dempty">{text}</p>;
  const seen = [...new Set(st.byDay.flatMap((d) => d.networks.filter((n) => n.count).map((n) => n.provider)))];
  const byNet = [...st.byNetwork].sort((a, b) => b.published - a.published);
  const maxE = Math.max(1, ...byNet.map((n) => n.engagement));

  return (
    <div className="flxsm-dash">
      {top}
      {!accs.length && (
        <div className="flxsm-banner">
          <Icon name="users" size={16} />
          <span>{t('No accounts connected yet. Enter the app keys first, then connect accounts.')}</span>
          <button type="button" className="flxsm-btn is-ghost" onClick={() => s.go('accounts')}>
            {t('Accounts')}
          </button>
        </div>
      )}
      <div className="flxsm-tiles">
        {tiles.map((x) => (
          <div key={x.label} className={'flxsm-tile' + (x.cls ? ' ' + x.cls : '')}>
            <div className="flxsm-tile-h">
              <Icon name={x.icon} size={15} />
              <span>{x.label}</span>
            </div>
            <div className="flxsm-tile-v">{x.value}</div>
            {x.sub && <div className="flxsm-tile-s">{x.sub}</div>}
          </div>
        ))}
      </div>

      {card(
        t('Daily posts'),
        <DayChart byDay={st.byDay} color={color} label={t('Daily posts')} />,
        <div className="flxsm-legend">
          {seen.map((p) => (
            <span key={p}>
              <i style={{ background: color(p) }} />
              {netLabel(p)}
            </span>
          ))}
        </div>,
      )}

      <div className="flxsm-dgrid">
        {card(
          t('By network'),
          byNet.length ? (
            <table className="flxsm-dtable">
              <thead>
                <tr>
                  <th>{t('Network')}</th>
                  <th className="is-num">{t('Posts')}</th>
                  <th className="is-num">{t('Errors')}</th>
                  <th className="is-num">{t('Engagement')}</th>
                  <th className="is-num">{t('Views')}</th>
                </tr>
              </thead>
              <tbody>
                {byNet.map((n) => (
                  <tr key={n.provider}>
                    <td>
                      <div className="flxsm-dcell">
                        <span>
                          <NetDot provider={n.provider} size={16} /> {netLabel(n.provider)}
                        </span>
                        <div className="flxsm-dbar">
                          <i style={{ width: (n.engagement / maxE) * 100 + '%', background: color(n.provider) }} />
                        </div>
                      </div>
                    </td>
                    <td className="is-num">{num(n.published, locale)}</td>
                    <td className={'is-num' + (n.failed ? ' flxsm-bad' : '')}>{num(n.failed, locale)}</td>
                    <td className="is-num">{num(n.engagement, locale)}</td>
                    <td className="is-num">{num(n.views, locale)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          ) : (
            empty(t('No posts in this period.'))
          ),
        )}
        {card(
          t('Followers'),
          st.followers.length ? (
            <div className="flxsm-flist">
              {[...st.followers]
                .sort((a, b) => b.now - a.now)
                .map((f) => (
                  <div key={f.accountId} className="flxsm-frow">
                    <Avatar src={f.avatar} name={f.name} size={28} />
                    <span className="flxsm-frow-n">{f.name}</span>
                    <span className="flxsm-spark-w" style={{ color: color(f.provider) }}>
                      <Spark points={f.series.map((p) => p.count)} />
                    </span>
                    <strong>{num(f.now, locale)}</strong>
                    <small className={f.delta > 0 ? 'flxsm-up' : f.delta < 0 ? 'flxsm-bad' : 'flxsm-muted'}>{(f.delta > 0 ? '+' : '') + num(f.delta, locale)}</small>
                  </div>
                ))}
            </div>
          ) : (
            empty(t('Follower counts are collected once a day; first data appears tomorrow.'))
          ),
        )}
      </div>

      <div className="flxsm-dgrid">
        {card(
          t('Top engaging posts'),
          st.top.length ? (
            <ol className="flxsm-best">
              {st.top.map((p) => (
                <li key={p.targetId}>
                  <NetDot provider={p.provider} size={18} />
                  <div className="flxsm-best-b">
                    <span className="flxsm-best-t">
                      {p.url ? (
                        <a href={p.url} target="_blank" rel="noreferrer">
                          {p.title || t('(untitled)')}
                        </a>
                      ) : (
                        p.title || t('(untitled)')
                      )}
                    </span>
                    <small className="flxsm-muted">
                      {p.account}
                      {p.at ? ' · ' + s.fmt(p.at.timestamp, 'date') : ''}
                    </small>
                  </div>
                  <div className="flxsm-best-m">
                    <span>
                      <Icon name="heart" size={12} />
                      {num(p.engagement, locale)}
                    </span>
                    {p.views > 0 && (
                      <span>
                        <Icon name="visibility" size={12} />
                        {num(p.views, locale)}
                      </span>
                    )}
                  </div>
                </li>
              ))}
            </ol>
          ) : (
            empty(t('Metrics are collected twice a day after a post is published.'))
          ),
        )}
        {card(
          t('Upcoming posts'),
          st.upcoming.length ? (
            <div className="flxsm-uplist">
              {st.upcoming.map((p: Post) => (
                <button key={p.databaseId} type="button" className="flxsm-uprow" onClick={() => s.detail(p)}>
                  <span className="flxsm-up-t">{p.scheduledAt ? s.fmt(p.scheduledAt.timestamp) : ''}</span>
                  <span className="flxsm-up-x">{excerpt(p, t)}</span>
                  <StatusBadge status={p.status} label={t(STATUS_KEYS[p.status])} />
                </button>
              ))}
            </div>
          ) : (
            <div className="flxsm-dempty">
              <p className="flxsm-muted">{t('No scheduled posts.')}</p>
              <button type="button" className="flxsm-btn is-primary" onClick={() => s.edit(null)}>
                <Icon name="plus-alt" size={15} /> {t('New post')}
              </button>
            </div>
          ),
          <button type="button" className="flxsm-link" onClick={() => s.go('calendar')}>
            {t('Calendar')}
          </button>,
        )}
      </div>

      {st.errors.length > 0 &&
        card(
          t('Recent errors'),
          <ul className="flxsm-derr">
            {st.errors.map((e) => (
              <li key={e.targetId}>
                <div className="flxsm-derr-h">
                  <NetDot provider={e.provider} size={15} />
                  <strong>{e.account}</strong>
                  <small className="flxsm-muted">{e.at ? s.fmt(e.at.timestamp) : ''}</small>
                </div>
                <p>{e.error}</p>
              </li>
            ))}
          </ul>,
          <button type="button" className="flxsm-link" onClick={() => s.go('posts', { status: 'FAILED,PARTIAL' })}>
            {t('All')}
          </button>,
        )}
      <p className="flxsm-muted flxsm-small flxsm-dfoot">{st.lastMetrics ? t('Metrics last updated: {t}', { t: s.fmt(st.lastMetrics.timestamp) }) : t('Metrics not collected yet (once a day, at night).')}</p>
    </div>
  );
}

/** Daily posts: stacked columns per network on a gridded axis (classic SVG chart). */
function DayChart({ byDay, color, label }: { byDay: { day: string; networks: { provider: string; count: number }[] }[]; color: (p: string) => string; label: string }) {
  const W = 720,
    H = 200,
    P = { l: 28, r: 8, t: 10, b: 22 };
  const n = Math.max(1, byDay.length);
  let max = Math.max(1, ...byDay.map((d) => d.networks.reduce((a, x) => a + x.count, 0)));
  const step = max <= 4 ? 1 : Math.ceil(max / 4);
  max = Math.ceil(max / step) * step;
  const iw = W - P.l - P.r,
    ih = H - P.t - P.b,
    bw = iw / n,
    gap = Math.min(4, bw * 0.25);
  const grid: number[] = [];
  for (let g = 0; g <= max; g += step) grid.push(g);
  return (
    <svg viewBox={`0 0 ${W} ${H}`} className="flxsm-chart" role="img" aria-label={label}>
      {grid.map((g) => {
        const y = P.t + ih - (g / max) * ih;
        return (
          <g key={g}>
            <line x1={P.l} x2={W - P.r} y1={y} y2={y} className="flxsm-grid-l" />
            <text x={P.l - 6} y={y + 3} textAnchor="end" className="flxsm-axis">
              {g}
            </text>
          </g>
        );
      })}
      {byDay.map((d, i) => {
        const x = P.l + i * bw;
        let yb = P.t + ih;
        const total = d.networks.reduce((a, k) => a + k.count, 0);
        const [yy, mm, dd] = d.day.split('-');
        const tick = n <= 31 ? i % Math.ceil(n / 8) === 0 : i % 15 === 0;
        return (
          <g key={d.day}>
            <title>{`${+(dd ?? 0)}.${mm ?? ''}.${yy ?? ''}: ${total}`}</title>
            {d.networks
              .filter((k) => k.count)
              .map((k) => {
                const hh = (k.count / max) * ih;
                yb -= hh;
                return <rect key={k.provider} x={x + gap / 2} y={yb} width={Math.max(1, bw - gap)} height={hh} rx={2} fill={color(k.provider)} />;
              })}
            {tick && (
              <text x={x + bw / 2} y={H - 6} textAnchor="middle" className="flxsm-axis">
                {+(dd ?? 0) + '.' + (mm ?? '')}
              </text>
            )}
            <rect x={x} y={P.t} width={bw} height={ih} className="flxsm-hit" />
          </g>
        );
      })}
    </svg>
  );
}

function Spark({ points }: { points: number[] }) {
  if (points.length < 2) return <span className="flxsm-spark" />;
  const min = Math.min(...points);
  const span = Math.max(1, Math.max(...points) - min);
  const d = points.map((v, i) => `${i ? 'L' : 'M'}${(i / (points.length - 1)) * 90},${24 - ((v - min) / span) * 22}`).join('');
  return (
    <svg className="flxsm-spark" viewBox="0 0 90 26" width={90} height={26} aria-hidden="true">
      <path d={d} fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinejoin="round" />
    </svg>
  );
}
