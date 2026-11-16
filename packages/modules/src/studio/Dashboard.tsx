// Studio dashboard (managers): usage, generations, spend, models, providers, people, recent errors.
import { useState } from 'react';
import { useFlxQuery } from '@fluxwp/graphql/react';
import { FlxStudioStats } from '@fluxwp/graphql/operations';
import type { FlxStudioStatsQuery } from '@fluxwp/graphql/types';
import { Btn, Ic, IconBtn, useT, type IconName } from './ui';
import { ago, loadPref, money, savePref } from './lib';

type Stats = FlxStudioStatsQuery['flxStudioStats'];
type Daily = Stats['daily'][number];
interface Series {
  key: 'image' | 'video' | 'failed' | 'cost';
  label: string;
  cls: string;
}

const niceStep = (max: number): number => {
  if (max <= 0) return 1;
  const raw = max / 4;
  const p = Math.pow(10, Math.floor(Math.log10(raw)));
  const m = raw / p;
  return (m <= 1 ? 1 : m <= 2 ? 2 : m <= 5 ? 5 : 10) * p;
};

export function Dashboard({ locale }: { locale: string }) {
  const t = useT();
  const [days, setDays] = useState<number>(loadPref<number>('dashDays') ?? 30);
  const q = useFlxQuery(FlxStudioStats, { days });
  const [tip, setTip] = useState<{ x: number; y: number; lines: string[] } | null>(null);
  const money2 = (v: number) => (v >= 10 ? '$' + v.toLocaleString(locale, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : money(v, locale));
  const num = (n: number) => (n || 0).toLocaleString(locale);
  const bytes = (b: number) => (!b ? '0 MB' : b >= 1073741824 ? (b / 1073741824).toLocaleString(locale, { maximumFractionDigits: 1 }) + ' GB' : Math.max(1, Math.round(b / 1048576)).toLocaleString(locale) + ' MB');
  const dayLabel = (iso: string, long?: boolean) => new Date(iso + 'T12:00:00').toLocaleDateString(locale, long ? { day: 'numeric', month: 'long', weekday: 'short' } : { day: 'numeric', month: 'short' });

  if (q.isLoading)
    return (
      <div className="flxst-dash">
        <div className="flxst-boot">
          <div className="flxst-spin" />
        </div>
      </div>
    );
  if (q.isError || !q.data)
    return (
      <div className="flxst-dash">
        <div className="flxst-empty">
          <Ic n="alert" size={28} />
          <p>{q.error?.message ?? t('Something went wrong.')}</p>
          <Btn label={t('Try again')} onClick={() => void q.refetch()} />
        </div>
      </div>
    );
  const d = q.data.flxStudioStats;
  const out = d.image + d.video;
  const tried = out + d.failed;

  const bars = (series: Series[], fmt: (v: number, axis?: boolean) => string) => {
    const W = 640;
    const H = 168;
    const PL = 36;
    const PB = 22;
    const PT = 8;
    const n = d.daily.length;
    let max = 0;
    for (const x of d.daily) max = Math.max(max, series.reduce((s, y) => s + (x[y.key] || 0), 0));
    const step = niceStep(max);
    const top = Math.max(step, Math.ceil(max / step) * step);
    const ih = H - PB - PT;
    const iw = W - PL;
    const bw = iw / Math.max(1, n);
    const gap = Math.max(2, bw * 0.22);
    const w = Math.max(2, bw - gap);
    const every = Math.ceil(n / 8);
    const grid: number[] = [];
    for (let g = 0; g <= top + 1e-9; g += step) grid.push(g);
    return (
      <svg viewBox={`0 0 ${W} ${H}`} className="flxst-chart" role="img">
        {grid.map((g) => {
          const y = PT + ih - (g / top) * ih;
          return (
            <g key={g}>
              <line x1={PL} x2={W} y1={y} y2={y} className="flxst-grid-l" />
              <text x={PL - 6} y={y + 3.5} className="flxst-axis" textAnchor="end">
                {fmt(g, true)}
              </text>
            </g>
          );
        })}
        {d.daily.map((day: Daily, i) => {
          const x = PL + i * bw + gap / 2;
          let base = PT + ih;
          return (
            <g key={day.date}>
              {series.map((s, si) => {
                const v = day[s.key] || 0;
                if (!v) return null;
                const hgt = Math.max(1.5, (v / top) * ih);
                const y = base - hgt;
                base -= hgt;
                return <rect key={s.key} x={x} y={y} width={w} height={Math.max(1, hgt - (si ? 2 : 0))} rx={Math.min(3, w / 2)} className={'flxst-bar-' + s.cls} />;
              })}
              {(i % every === 0 || (i === n - 1 && i % every >= every / 2)) && (
                <text x={x + w / 2} y={H - 6} className="flxst-axis" textAnchor="middle">
                  {dayLabel(day.date)}
                </text>
              )}
              <rect
                x={PL + i * bw}
                y={PT}
                width={bw}
                height={ih}
                className="flxst-hit"
                onMouseMove={(e) => setTip({ x: e.clientX + 14, y: e.clientY + 14, lines: [dayLabel(day.date, true), ...series.map((s) => `${s.label}: ${fmt(day[s.key] || 0)}`)] })}
                onMouseLeave={() => setTip(null)}
              />
            </g>
          );
        })}
      </svg>
    );
  };

  const tile = (label: string, value: string, sub: string, icon: IconName) => (
    <div className="flxst-tile">
      <div className="flxst-tile-h">
        <Ic n={icon} size={15} />
        <span>{label}</span>
      </div>
      <div className="flxst-tile-v">{value}</div>
      {sub && <div className="flxst-tile-s">{sub}</div>}
    </div>
  );
  const card = (title: string, body: React.ReactNode, extra?: React.ReactNode) => (
    <section className="flxst-dcard">
      <div className="flxst-card-h">
        <h3>{title}</h3>
        {extra}
      </div>
      {body}
    </section>
  );
  const empty = (msg: string) => <p className="flxst-muted flxst-dempty">{msg}</p>;
  const hasAny = d.daily.some((x) => x.image || x.video || x.failed);
  const budgets = d.budget.filter((b) => b.limit > 0);
  const maxDone = (rows: Array<{ done: number }>) => rows.reduce((m, r) => Math.max(m, r.done), 0);
  const cell = (content: React.ReactNode, v: number, max: number) => (
    <div className="flxst-dcell">
      {content}
      <div className="flxst-dbar">
        <i style={{ width: (max ? (v / max) * 100 : 0) + '%' }} />
      </div>
    </div>
  );
  const peopleMax = d.people.reduce((m, r) => Math.max(m, r.image + r.video), 0);

  return (
    <div className="flxst-dash">
      <div className="flxst-dhead">
        <div className="flxst-seg is-small">
          {[7, 30, 90].map((n) => (
            <button
              key={n}
              type="button"
              className={'flxst-seg-b' + (days === n ? ' is-on' : '')}
              onClick={() => {
                setDays(n);
                savePref('dashDays', n);
              }}
            >
              {n === 7 ? t('7 days') : n === 30 ? t('30 days') : t('90 days')}
            </button>
          ))}
        </div>
        <span className="flxst-muted is-small">{d.spendFrom === 'flx-ai' ? t('Spend from Flux AI logs') : t('Flux AI logs unavailable; Studio amounts')}</span>
        <IconBtn icon="repeat" title={t('Refresh')} onClick={() => void q.refetch()} />
      </div>
      <div className="flxst-tiles">
        {tile(t('Generations'), num(out), t('{i} images · {v} videos', { i: num(d.image), v: num(d.video) }), 'sparkle')}
        {tile(t('Spend'), money2(d.spendTotal), t('Image/video {m} · text {x}', { m: money2(d.spendMedia), x: money2(d.spendText) }) + (d.spendEstimated ? ' ' + t('(estimated)') : ''), 'chart')}
        {tile(t('Average'), out ? money(d.spendMedia / out, locale) : '—', t('per generation'), 'layers')}
        {tile(t('Failed'), num(d.failed), tried ? t('{p}% error rate', { p: Math.round((d.failed / tried) * 100) }) : t('no attempts yet'), 'alert')}
        {tile(t('People'), num(d.users), t('Storage {b}', { b: bytes(d.bytes) }), 'users')}
      </div>
      <div className="flxst-dgrid">
        {card(
          t('Daily generations'),
          hasAny
            ? bars(
                [
                  { key: 'image', label: t('Images'), cls: 'img' },
                  { key: 'video', label: t('Videos'), cls: 'vid' },
                  { key: 'failed', label: t('Failed'), cls: 'err' },
                ],
                (v) => num(v),
              )
            : empty(t('No generations in this period.')),
          <div className="flxst-legend">
            <span>
              <i className="flxst-bar-img" />
              {t('Images')}
            </span>
            <span>
              <i className="flxst-bar-vid" />
              {t('Videos')}
            </span>
            {d.failed > 0 && (
              <span>
                <i className="flxst-bar-err" />
                {t('Failed')}
              </span>
            )}
          </div>,
        )}
        {card(t('Daily spend'), d.spendTotal > 0 ? bars([{ key: 'cost', label: t('Spend'), cls: 'cost' }], (v, axis) => (axis ? '$' + Math.round(v * 100) / 100 : money(v, locale))) : empty(t('No spend in this period.')))}
        {budgets.length > 0 &&
          card(
            t('Flux AI spending limit'),
            <div className="flxst-dbudget">
              {budgets.map((b) => (
                <div key={b.period} className={'flxst-budget' + (b.over ? ' is-over' : '')}>
                  <div className="flxst-budget-t">
                    <span>{b.label}</span>
                    <span>
                      {money(b.spent, locale)} / {money(b.limit, locale)}
                    </span>
                  </div>
                  <div className="flxst-bar">
                    <i style={{ width: Math.min(100, b.pct) + '%' }} />
                  </div>
                </div>
              ))}
            </div>,
            d.logUrl ? (
              <a className="flxst-link" href={d.logUrl}>
                {t('Flux AI logs')}
              </a>
            ) : null,
          )}
      </div>
      <div className="flxst-dgrid is-3">
        {card(
          t('Models'),
          d.models.length ? (
            <table className="flxst-dtable">
              <thead>
                <tr>
                  <th>{t('Model')}</th>
                  <th className="is-num">{t('Generations')}</th>
                  <th className="is-num">{t('Errors')}</th>
                  <th className="is-num">{t('Amount')}</th>
                </tr>
              </thead>
              <tbody>
                {d.models.map((r) => (
                  <tr key={r.id}>
                    <td>
                      {cell(
                        <span>
                          <Ic n={r.kind === 'VIDEO' ? 'video' : 'image'} size={13} /> {r.label}
                        </span>,
                        r.done,
                        maxDone(d.models),
                      )}
                    </td>
                    <td className="is-num">{num(r.done)}</td>
                    <td className="is-num">{r.failed ? num(r.failed) : '—'}</td>
                    <td className="is-num">{money2(r.cost)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          ) : (
            empty(t('No records.'))
          ),
        )}
        {card(
          t('Providers'),
          d.providers.length ? (
            <table className="flxst-dtable">
              <thead>
                <tr>
                  <th>{t('Provider')}</th>
                  <th className="is-num">{t('Generations')}</th>
                  <th className="is-num">{t('Errors')}</th>
                  <th className="is-num">{t('Amount')}</th>
                </tr>
              </thead>
              <tbody>
                {d.providers.map((r) => (
                  <tr key={r.id}>
                    <td>{cell(<span>{r.label}</span>, r.done, maxDone(d.providers))}</td>
                    <td className="is-num">{num(r.done)}</td>
                    <td className="is-num">{r.failed ? num(r.failed) : '—'}</td>
                    <td className="is-num">{money2(r.cost)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          ) : (
            empty(t('No records.'))
          ),
        )}
        {card(
          t('People'),
          d.people.length ? (
            <table className="flxst-dtable">
              <thead>
                <tr>
                  <th>{t('Person')}</th>
                  <th className="is-num">{t('Images')}</th>
                  <th className="is-num">{t('Videos')}</th>
                  <th className="is-num">{t('Amount')}</th>
                </tr>
              </thead>
              <tbody>
                {d.people.map((r) => (
                  <tr key={r.userId}>
                    <td>
                      {cell(
                        <span className="flxst-duser">
                          {r.avatar && <img src={r.avatar} alt="" />}
                          <span>{r.name}</span>
                        </span>,
                        r.image + r.video,
                        peopleMax,
                      )}
                    </td>
                    <td className="is-num">{num(r.image)}</td>
                    <td className="is-num">{num(r.video)}</td>
                    <td className="is-num">{money2(r.cost)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          ) : (
            empty(t('No records.'))
          ),
        )}
      </div>
      {d.errors.length > 0 &&
        card(
          t('Recent errors'),
          <ul className="flxst-derr">
            {d.errors.map((e) => (
              <li key={e.itemId}>
                <div className="flxst-derr-h">
                  <strong>{e.model}</strong>
                  {e.provider && <span className="flxst-muted"> · {e.provider}</span>}
                  <small className="flxst-muted">{ago(t, e.createdAt?.timestamp ?? 0, locale)}</small>
                </div>
                <p>{e.error}</p>
              </li>
            ))}
          </ul>,
        )}
      {tip && (
        <div className="flxst-dtip" style={{ left: Math.min(tip.x, window.innerWidth - 180), top: Math.min(tip.y, window.innerHeight - 90) }}>
          {tip.lines.map((l, i) => (i ? <div key={i}>{l}</div> : <strong key={i}>{l}</strong>))}
        </div>
      )}
    </div>
  );
}
