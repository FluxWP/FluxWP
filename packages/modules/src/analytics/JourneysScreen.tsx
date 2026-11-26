// Flux Analytics › Journeys: how visitors move through the site (flow chart and frequent paths).
// Same screen in wp-admin and the FluxWP app; data over the FlxAnalyticsJourneys GraphQL query.
import { useEffect, useMemo, useRef, useState } from 'react';
import { useFlxQuery } from '@fluxwp/graphql/react';
import { FlxAnalyticsJourneys } from '@fluxwp/graphql/operations';
import type { FlxAnalyticsJourneysQuery } from '@fluxwp/graphql/types';
import { Delta, FilterBar, Kpi, KpiSkeleton, Panel, Shell, SkRows, Tabs, filterInput, useConfig, useFilters, useFmt, withAnalytics } from './lib';

type Data = FlxAnalyticsJourneysQuery['flxAnalyticsJourneys'];
type Tab = 'all' | 'reach' | 'never';

const PAL = ['#1f2937', '#2563eb', '#7c3aed', '#d97706', '#0d9488', '#e11d48', '#0891b2', '#65a30d', '#c026d3', '#ea580c'];

function Journeys() {
  const { t, n, pf } = useFmt();
  const cfg = useConfig();
  const { f, set, param, setParam } = useFilters(['steps', 'from', 'to', 'goal', 'group', 'top', 'cmp']);
  const steps = Math.max(2, Math.min(6, Number(param('steps')) || 4));
  const from = param('from') ?? '';
  const to = param('to') ?? '';
  const goalParam = param('goal'); // null = the default goal
  const group = param('group') === '1';
  const top = Number(param('top')) || 15;
  const cmp = param('cmp') !== '0';
  const [tab, setTab] = useState<Tab>('all');
  const [search, setSearch] = useState('');
  const pathsRef = useRef<HTMLDivElement>(null);

  const filter = useMemo(() => filterInput(f), [f]);
  const options = useMemo(() => ({ steps, from, to, group, top: 100, ...(goalParam === null ? {} : { goal: goalParam }) }), [steps, from, to, group, goalParam]);
  const q = useFlxQuery(FlxAnalyticsJourneys, { filter, options });
  const d = q.data?.flxAnalyticsJourneys;
  const goal = d?.options.goal ?? goalParam ?? cfg.defaultGoal;
  const goals = d?.goals ?? cfg.goals;

  const pageLabels = useMemo(() => {
    const m = new Map<string, string>();
    d?.paths.items.forEach((p) => p.steps.forEach((s) => s.path && !m.has(s.path) && m.set(s.path, s.label)));
    d?.flow.columns.forEach((c) => c.nodes.forEach((s) => s.path && !m.has(s.path) && m.set(s.path, s.label)));
    return m;
  }, [d]);
  const byLabel = (v: string) => [...pageLabels.entries()].find(([, l]) => l === v)?.[0] ?? v;

  const s = d?.summary;
  const page = (x: { label: string } | null | undefined) => (x ? x.label : '—');
  const psub = (x: { count: number; pct: number } | null | undefined) => (x ? t('%1$s visits · %2$s', n(x.count), pf(x.pct) + '%') : t('No data'));

  return (
    <Shell title={t('Journeys')} busy={q.isFetching && !d}>
      <div className="flxan-tools flxan-tools--bar">
        <FilterBar f={f} set={set} />
      </div>
      <div className="flxan-jbar">
        <label className="flxan-switch">
          <input type="checkbox" checked={cmp} onChange={(e) => setParam('cmp', e.target.checked ? null : '0')} />
          <span />
          {t('Compare with the previous period')}
        </label>
        <label className={'flxan-jgoal' + (goal ? ' on' : '')}>
          <span className="flxan-jgoal__ico" aria-hidden="true">◎</span>
          {t('Goal page')}{' '}
          <select value={goal} onChange={(e) => setParam('goal', e.target.value)}>
            {goals.map((g) => (
              <option key={g.value} value={g.value}>
                {g.label}
              </option>
            ))}
          </select>
        </label>
        <label className="flxan-switch" title={t('Collect sub pages such as /news/… in one box')}>
          <input type="checkbox" checked={group} onChange={(e) => setParam('group', e.target.checked ? '1' : null)} />
          <span />
          {t('Group pages')}
        </label>
      </div>

      <div className="flxan-cards flxan-kpis flxan-kpis--5">
        {s && d ? (
          <>
            <Kpi label={t('Multi-page visits')} value={n(s.multi)} ch={s.multiChange} sub={t('%s of all visits', pf(s.sessions ? (s.multi / s.sessions) * 100 : 0) + '%')} tone="violet" icon="layers" showDelta={cmp} />
            <Kpi label={t('Avg. path length')} value={t('%s pages', pf(s.avgLen))} ch={s.avgLenChange} sub={t('In multi-page visits')} tone="blue" icon="route" showDelta={cmp} />
            <Kpi label={t('Most common entry')} value={page(s.topEntry)} sub={psub(s.topEntry)} tone="teal" icon="enter" text />
            <Kpi label={t('Most common exit after 2+ pages')} value={page(s.topExit)} sub={psub(s.topExit)} tone="amber" icon="exit" text />
            {s.goalRate === null ? (
              <Kpi label={t('Goal reached')} value="—" sub={t('No goal chosen')} tone="green" icon="target" />
            ) : (
              <Kpi label={t('Goal reached')} value={pf(s.goalRate) + '%'} ch={s.goalChange} sub={t('%1$s visits · %2$s', n(s.goalReached), goals.find((g) => g.value === d.options.goal)?.label ?? '')} tone="green" icon="target" showDelta={cmp} />
            )}
          </>
        ) : (
          <KpiSkeleton tones={[['violet', 'layers'], ['blue', 'route'], ['teal', 'enter'], ['amber', 'exit'], ['green', 'target']]} />
        )}
      </div>

      <datalist id="flxan-jpages">
        {[...pageLabels.entries()].map(([p, l]) => (
          <option key={p} value={p}>
            {l}
          </option>
        ))}
      </datalist>

      <div className="flxan-panel flxan-flowpanel">
        <div className="flxan-panel__head">
          <h2>{t('Paths from the entry')}</h2>
          <div className="flxan-jctl">
            <label className="flxan-jfield">
              <span>{t('Starting at')}</span>
              <input className="flxan-in" list="flxan-jpages" placeholder={t('All pages')} defaultValue={from} key={'f' + from} onBlur={(e) => setParam('from', byLabel(e.target.value.trim()))} onKeyDown={(e) => e.key === 'Enter' && e.currentTarget.blur()} />
            </label>
            <label className="flxan-jfield">
              <span>{t('Ending at')}</span>
              <input className="flxan-in" list="flxan-jpages" placeholder={t('All pages')} defaultValue={to} key={'t' + to} onBlur={(e) => setParam('to', byLabel(e.target.value.trim()))} onKeyDown={(e) => e.key === 'Enter' && e.currentTarget.blur()} />
            </label>
            <div className="flxan-jfield">
              <span>{t('Steps')}</span>
              <div className="flxan-stepper">
                <button type="button" className="flxan-btn" aria-label={t('Fewer steps')} aria-disabled={steps <= 2} onClick={() => steps > 2 && setParam('steps', String(steps - 1))}>
                  −
                </button>
                <b>{steps}</b>
                <button type="button" className="flxan-btn" aria-label={t('More steps')} aria-disabled={steps >= 6} onClick={() => steps < 6 && setParam('steps', String(steps + 1))}>
                  +
                </button>
              </div>
            </div>
            <label className="flxan-jfield">
              <span>{t('Paths shown')}</span>
              <select className="flxan-in" value={top} onChange={(e) => setParam('top', e.target.value)}>
                {[10, 15, 25, 50].map((k) => (
                  <option key={k} value={k}>
                    {t('Top %s paths', k)}
                  </option>
                ))}
              </select>
            </label>
          </div>
        </div>
        <div className="flxan-flow">
          {!d ? <FlowSkeleton steps={steps} /> : <Flow d={d} onNode={(label) => { setSearch(label); pathsRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' }); }} />}
        </div>
        <p className="flxan-muted flxan-flowhint">{d && d.flow.columns[0]?.nodes.length ? t('Click a box to filter the paths below by that page. Thickness shows the number of visits.') : ''}</p>
      </div>

      <div className="flxan-panel" ref={pathsRef}>
        <div className="flxan-panel__head">
          <h2>{t('Most frequent paths')}</h2>
          <div className="flxan-filterbar">
            {s && s.goalRate !== null && (
              <Tabs items={([['all', t('All')], ['reach', t('Reached the goal')], ['never', t('Did not reach')]] as [Tab, string][]).map(([key, label]) => ({ key, label }))} value={tab} onChange={setTab} />
            )}
            <input type="search" className="flxan-search" placeholder={t('Search pages…')} value={search} onChange={(e) => setSearch(e.target.value)} />
          </div>
        </div>
        <div className="flxan-paths">{!d ? <SkRows rows={8} /> : <Paths d={d} tab={tab} q={search.trim().toLowerCase()} top={top} cmp={cmp} />}</div>
      </div>
      <p className="flxan-note">{t('Journey: the order of pages seen in one visit (first 8 steps; refreshing the same page counts once). Events such as add to cart, the payment form, "Pay", payment method, order and WhatsApp/phone clicks appear as steps with ⚡. Only visits with at least 2 pages are shown. The goal is chosen from the steps under Settings › Funnels.')}</p>
    </Shell>
  );
}

export const JourneysScreen = withAnalytics(Journeys);

function FlowSkeleton({ steps }: { steps: number }) {
  return (
    <div className="flxan-skflow" style={{ ['--c' as string]: steps }}>
      {Array.from({ length: steps }, (_, i) => (
        <div key={i} className="flxan-skflow__col">
          <i className="flxan-sk flxan-sk--title" />
          {Array.from({ length: Math.max(2, 5 - i) }, (_, j) => (
            <i key={j} className="flxan-sk" style={{ height: Math.max(46, 110 - j * 18 - i * 8) }} />
          ))}
        </div>
      ))}
    </div>
  );
}

/** The flow chart (sankey): boxes per step joined by bands whose thickness is the number of visits. */
function Flow({ d, onNode }: { d: Data; onNode: (label: string) => void }) {
  const { t, n } = useFmt();
  const box = useRef<HTMLDivElement>(null);
  const [width, setWidth] = useState(900);
  const [hot, setHot] = useState<string | null>(null);
  useEffect(() => {
    const el = box.current?.parentElement;
    if (!el) return;
    const ro = new ResizeObserver(() => setWidth(el.clientWidth || 900));
    ro.observe(el);
    setWidth(el.clientWidth || 900);
    return () => ro.disconnect();
  }, []);
  const cols = d.flow.columns.map((c) => c.nodes);
  if (!cols.length || !cols[0]?.length) {
    return (
      <div className="flxan-empty flxan-empty--big">
        <b>{t('No journeys yet')}</b>
        <br />
        {t('No visit with at least 2 pages was found in this range. Widen the filters or wait for new visits.')}
      </div>
    );
  }
  const color: Record<string, string> = {};
  let ci = 0;
  cols.forEach((c) => c.forEach((x) => x.key !== '~' && !color[x.key] && (color[x.key] = PAL[ci++ % PAL.length]!)));
  const colorOf = (k: string) => (k === '~' ? '#9aa1a8' : (color[k] ?? '#999'));
  const NW = 172;
  const GAP = 10;
  const MINH = 46;
  const colN = cols.length;
  const W = Math.max(width, colN * (NW + 70));
  const total = cols[0].reduce((a, x) => a + x.count, 0) || 1;
  const avail = Math.max(260, Math.min(560, 70 * Math.max(...cols.map((c) => c.length))));
  const k = avail / total;
  const colX = (i: number) => (colN === 1 ? 0 : (i * (W - NW)) / (colN - 1));
  const pos = new Map<string, { x: number; y: number; h: number; outY: number; inY: number; label: string }>();
  let H = 0;
  cols.forEach((c, i) => {
    let y = 34;
    c.forEach((x) => {
      const h = Math.max(MINH, x.count * k);
      pos.set(i + '|' + x.key, { x: colX(i), y, h, outY: y, inY: y, label: x.label });
      y += h + GAP;
    });
    H = Math.max(H, y);
  });
  const links = [...d.flow.links]
    .sort((a, b) => a.step - b.step || b.count - a.count)
    .map((l) => {
      const a = pos.get(l.step + '|' + l.from);
      const b = pos.get(l.step + 1 + '|' + l.to);
      if (!a || !b) return null;
      const th = Math.max(2, l.count * k);
      const y1 = a.outY + th / 2;
      const y2 = b.inY + th / 2;
      a.outY += th;
      b.inY += th;
      const x1 = a.x + NW;
      const x2 = b.x;
      const mx = (x1 + x2) / 2;
      return { l, th, path: `M${x1},${y1} C${mx},${y1} ${mx},${y2} ${x2},${y2}`, title: `${a.label} → ${b.label}: ${t('%s visits', n(l.count))}` };
    })
    .filter((x): x is NonNullable<typeof x> => !!x);
  return (
    <div ref={box} className={'flxan-sankey' + (hot ? ' hl' : '')} style={{ width: W, height: H }}>
      <svg className="flxan-sankey__links" width={W} height={H} viewBox={`0 0 ${W} ${H}`} aria-hidden="true">
        {links.map((x, i) => (
          <path key={i} className={'flxan-link' + (hot && (x.l.from === hot || x.l.to === hot) ? ' on' : '')} d={x.path} stroke={colorOf(x.l.from)} strokeWidth={x.th}>
            <title>{x.title}</title>
          </path>
        ))}
      </svg>
      {cols.map((c, i) => (
        <div key={i}>
          <div className="flxan-sankey__col" style={{ left: colX(i), width: NW }}>
            <b>{i === 0 ? t('Entry') : t('Step %s', i + 1)}</b> <span>{n(c.reduce((a, x) => a + x.count, 0))}</span>
          </div>
          {c.map((x) => {
            const p = pos.get(i + '|' + x.key)!;
            const endPct = x.count ? Math.round((x.ends / x.count) * 100) : 0;
            const sub = x.ends > 0 ? t('%s ended here', endPct + '%') : i < colN - 1 ? t('all continued') : t('continuing');
            return (
              <button
                key={x.key}
                type="button"
                className={'flxan-node' + (x.key === '~' ? ' flxan-node--other' : '') + (x.ev ? ' flxan-node--ev' : '')}
                style={{ left: p.x, top: p.y, width: NW, height: p.h, ['--c' as string]: colorOf(x.key) }}
                title={x.label + (x.path ? ' (' + x.path + ')' : '')}
                onMouseEnter={() => setHot(x.key)}
                onMouseLeave={() => setHot(null)}
                onClick={() => x.key !== '~' && onNode(x.label)}
              >
                <span className="flxan-node__l">
                  {x.ev && (
                    <span className="flxan-evi" aria-hidden="true">
                      ⚡
                    </span>
                  )}
                  {x.label}
                </span>
                <span className="flxan-node__s">
                  <b>{n(x.count)}</b> · {sub}
                </span>
                <i className="flxan-node__end" style={{ height: endPct + '%' }} />
              </button>
            );
          })}
        </div>
      ))}
    </div>
  );
}

function Paths({ d, tab, q, top, cmp }: { d: Data; tab: Tab; q: string; top: number; cmp: boolean }) {
  const { t, n, pf } = useFmt();
  const hasGoal = d.summary.goalRate !== null;
  const items = d.paths.items
    .map((p) => ({ p, c: tab === 'reach' ? p.reached : tab === 'never' ? p.sessions - p.reached : p.sessions }))
    .filter((x) => x.c && (!q || x.p.steps.some((s) => (s.label + ' ' + s.path).toLowerCase().includes(q))))
    .sort((a, b) => b.c - a.c);
  const sum = items.reduce((a, x) => a + x.c, 0) || 1;
  const shown = items.slice(0, top);
  if (!shown.length) return <p className="flxan-empty">{q ? t('No path matches the search.') : t('No paths to show.')}</p>;
  return (
    <>
      <table className="flxan-table flxan-ptable">
        <thead>
          <tr>
            <th className="n">#</th>
            <th>{t('Path')}</th>
            <th className="n">{t('Visits')}</th>
            <th className="flxan-pshare">{t('Share')}</th>
            {cmp && <th className="n n2">{t('Against previous')}</th>}
            {hasGoal && <th className="n">{t('Goal reached')}</th>}
          </tr>
        </thead>
        <tbody>
          {shown.map((x, i) => {
            const share = (x.c / sum) * 100;
            return (
              <tr key={i}>
                <td className="n flxan-muted">{i + 1}</td>
                <td>
                  <div className="flxan-pchips">
                    {x.p.steps.map((s, j) => (
                      <span key={j} style={{ display: 'contents' }}>
                        {j > 0 && (
                          <span className="flxan-arrow" aria-hidden="true">
                            →
                          </span>
                        )}
                        <span className={'flxan-pchip' + (s.ev ? ' flxan-pchip--ev' : '')} title={s.path || s.label}>
                          {s.ev ? '⚡ ' : ''}
                          {s.label}
                        </span>
                      </span>
                    ))}
                  </div>
                </td>
                <td className="n">
                  <b>{n(x.c)}</b>
                </td>
                <td className="flxan-pshare">
                  <div className="flxan-sharebar">
                    <i style={{ width: Math.max(2, share) + '%' }} />
                  </div>
                  <span>{pf(share)}%</span>
                </td>
                {cmp && <td className="n n2">{x.p.change === null ? <span className="flxan-muted">{x.p.prev ? '—' : t('new')}</span> : <Delta ch={x.p.change} />}</td>}
                {hasGoal && (
                  <td className="n">
                    <span className={'flxan-rate' + (x.p.rate >= 50 ? ' hi' : x.p.rate > 0 ? ' mid' : '')}>{pf(x.p.rate)}%</span>
                  </td>
                )}
              </tr>
            );
          })}
        </tbody>
      </table>
      {items.length > shown.length && <p className="flxan-muted">{t('Showing %2$s of %1$s paths.', n(items.length), n(shown.length))}</p>}
    </>
  );
}

