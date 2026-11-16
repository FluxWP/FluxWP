// Campaigns, Suggestions and Tests tabs (Meta ad account).
import { useState } from 'react';
import { useFlxQuery } from '@fluxwp/graphql/react';
import {
  FlxAdsCampaign,
  FlxAdsCampaignBudget,
  FlxAdsCampaignStatus,
  FlxAdsCampaigns,
  FlxAdsChangeHistory,
  FlxAdsContext,
  FlxAdsExperimentChoices,
  FlxAdsExperimentCreate,
  FlxAdsExperimentDelete,
  FlxAdsExperimentFinish,
  FlxAdsExperimentPauseLoser,
  FlxAdsExperiments,
  FlxAdsSuggestionApply,
  FlxAdsSuggestionDismiss,
  FlxAdsSuggestions,
  FlxAdsSuggestionsRefresh,
} from '@fluxwp/graphql/operations';
import { Loading } from '@fluxwp/ui';
import { Btn, Card, Dot, Empty, Field, Kpi, Muted, Notice, Pill, Roas, Td, num, roas, useAct, useEnv } from './common';
import { Fail } from './AdsScreen';

const SETUP = 'Enter the Meta ad account ID and a Marketing API key in Settings › Meta. Reading needs ads_read; pausing and budget changes need ads_management.';

function Setup() {
  const { t, ctx, go } = useEnv();
  return (
    <Card pad>
      <p>{t(SETUP)}</p>
      {ctx.canManage && (
        <p>
          <Btn onClick={() => go('settings')}>{t('Settings')}</Btn>
        </p>
      )}
    </Card>
  );
}

const money = (n: number, cur: string, locale: string) => {
  try {
    return new Intl.NumberFormat(locale, { style: 'currency', currency: cur, maximumFractionDigits: 2 }).format(n);
  } catch {
    return num(n, locale, 2) + ' ' + cur;
  }
};

interface Metric {
  spend: { formatted: string };
  impressions: number;
  clicks: number;
  ctr: number | null;
}

function MetricCells({ o }: { o: Metric }) {
  const { t, locale } = useEnv();
  return (
    <>
      <Td l={t('Spend')} num>{o.spend.formatted}</Td>
      <Td l={t('Impressions')} num>{num(o.impressions, locale)}</Td>
      <Td l={t('Link clicks')} num>
        <span>
          {num(o.clicks, locale)}
          {o.ctr !== null && <small className="flxads-muted">{t('%s%% CTR', num(o.ctr, locale, 2))}</small>}
        </span>
      </Td>
    </>
  );
}

interface Item {
  id: string;
  name: string;
  canPause: boolean;
  active: boolean;
  dailyBudget?: number | null;
}

/** Pause / turn on and daily budget of one campaign, ad set or ad; every change is confirmed. */
function Controls({ o, cur, refresh }: { o: Item; cur: string; refresh: ReadonlyArray<{ name: string }> }) {
  const { t, ctx, ask, locale } = useEnv();
  const [budget, setBudget] = useState(o.dailyBudget == null ? '' : String(Math.round(o.dailyBudget)));
  const status = useAct(FlxAdsCampaignStatus, (r) => r.flxAdsCampaignStatus, t('Status changed in Meta.'), [...refresh, FlxAdsChangeHistory, FlxAdsCampaigns]);
  const bud = useAct(FlxAdsCampaignBudget, (r) => r.flxAdsCampaignBudget, t('Daily budget changed in Meta.'), [...refresh, FlxAdsChangeHistory, FlxAdsCampaigns]);
  if (!ctx.canManage) return null;
  return (
    <div className="flxads-actions">
      {o.canPause && (
        <Btn
          small
          primary={!o.active}
          disabled={status.isPending}
          onClick={() =>
            ask({
              text: o.active ? t('"%s" stops showing in Meta right away. You can turn it back on any time.', o.name) : t('"%s" starts showing in Meta and spending its budget.', o.name),
              ok: o.active ? t('Pause') : t('Turn on'),
              run: () => status.mutate({ input: { id: o.id, status: o.active ? 'PAUSED' : 'ACTIVE' } }),
            })
          }
        >
          {o.active ? t('Pause') : t('Turn on')}
        </Btn>
      )}
      {o.dailyBudget != null && (
        <form
          className="flxads-budget"
          onSubmit={(e) => {
            e.preventDefault();
            const v = Number(budget);
            if (!(v >= 1)) return;
            ask({
              text: t('The daily budget of "%1$s" changes in Meta right away (now %2$s).', o.name, money(o.dailyBudget ?? 0, cur, locale)),
              ok: t('Change budget'),
              run: () => bud.mutate({ input: { id: o.id, amount: v } }),
            });
          }}
        >
          <input type="number" min={1} step={1} aria-label={t('Daily budget')} value={budget} onChange={(e) => setBudget(e.target.value)} />
          <Btn small type="submit" disabled={bud.isPending}>
            {t('Save')}
          </Btn>
        </form>
      )}
    </div>
  );
}

const Status = ({ label, tone }: { label: string; tone: string }) => <Pill tone={tone}>{label}</Pill>;

export function CampaignsTab() {
  const { t, locale, ctx, params } = useEnv();
  const [range, setRange] = useState('7');
  const [cid, setCid] = useState(params.campaign ?? '');
  const list = useFlxQuery(FlxAdsCampaigns, { range }, { enabled: ctx.metaReady });
  if (!ctx.metaReady) return <><p className="flxads-lead">{t('Your Meta campaigns, ad sets and ads with spend and the donations they brought on this site. Pause, turn on or change the daily budget here; every change goes to Meta at once and is written to the change history.')}</p><Setup /></>;
  if (list.isLoading) return <Loading />;
  const d = list.data?.flxAdsCampaigns;
  if (!d) return <Fail q={list} />;
  return (
    <>
      <p className="flxads-lead">{t('Your Meta campaigns, ad sets and ads with spend and the donations they brought on this site. Pause, turn on or change the daily budget here; every change goes to Meta at once and is written to the change history.')}</p>
      <nav className="flxads-seg" aria-label={t('Period')}>
        {d.ranges.map((r) => (
          <a key={r.key} href="#" className={r.key === range ? 'is-on' : ''} aria-current={r.key === range ? 'true' : undefined} onClick={(e) => { e.preventDefault(); setRange(r.key); }}>
            {r.label}
          </a>
        ))}
      </nav>
      {cid !== '' ? (
        <CampaignDetail id={cid} range={range} back={() => setCid('')} />
      ) : d.error ? (
        <Card pad>
          <Notice kind="bad">
            {t('Meta could not be read:')} {d.error}
          </Notice>
        </Card>
      ) : (
        <>
          {d.totals && (
            <div className="flxads-kpis">
              <Kpi label={t('Ad spend')} value={d.totals.spend.formatted} hint={d.sinceLabel + ' – ' + d.untilLabel} />
              <Kpi label={t('Brought on the site')} value={d.totals.income.formatted} hint={t('%s payments', num(d.totals.payments, locale))} />
              <Kpi label={t('Return on ad spend (ROAS)')} value={roas(d.totals.roas, locale)} />
              <Kpi label={t('Active campaigns')} value={num(d.totals.active, locale)} hint={t('%s in total', num(d.totals.count, locale))} />
            </div>
          )}
          <Card>
            <table className="flxads-table flxads-mgr">
              <thead>
                <tr>
                  <th>{t('Campaign')}</th>
                  <th>{t('Budget')}</th>
                  <th className="num">{t('Spend')}</th>
                  <th className="num">{t('Impressions')}</th>
                  <th className="num">{t('Link clicks')}</th>
                  <th className="num">{t('On the site')}</th>
                  <th className="num">ROAS</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                {d.rows.length === 0 && <Empty cols={8}>{t('No campaigns in this ad account.')}</Empty>}
                {d.rows.map((c) => (
                  <tr key={c.id}>
                    <Td l={t('Campaign')}>
                      <a className="flxads-mgr__name" href="#" onClick={(e) => { e.preventDefault(); setCid(c.id); }}>
                        {c.name}
                      </a>{' '}
                      <Status label={c.statusLabel} tone={c.statusTone} />
                      <small className="flxads-muted">{c.objectiveLabel}</small>
                    </Td>
                    <Td l={t('Budget')}>{c.budgetLabel !== '' ? c.budgetLabel : <Muted>{t('in ad sets')}</Muted>}</Td>
                    <MetricCells o={c} />
                    <Td l={t('On the site')} num>
                      {c.payments > 0 ? (
                        <span>
                          {c.income.formatted}
                          <small className="flxads-muted">{t('%s payments', num(c.payments, locale))}</small>
                        </span>
                      ) : (
                        <Muted>—</Muted>
                      )}
                    </Td>
                    <Td l="ROAS" num>
                      <Roas v={c.roas} level={c.roasLevel} />
                    </Td>
                    <td>
                      <Controls o={c} cur={d.currency} refresh={[FlxAdsCampaigns]} />
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </Card>
          <p className="flxads-muted">{t('Donations on the site are matched by campaign name or ID in the ad link (utm_campaign). Meta numbers are refreshed every 10 minutes.')}</p>
        </>
      )}
      <History />
    </>
  );
}

function CampaignDetail({ id, range, back }: { id: string; range: string; back: () => void }) {
  const { t, locale } = useEnv();
  const q = useFlxQuery(FlxAdsCampaign, { id, range });
  const c = q.data?.flxAdsCampaign;
  return (
    <>
      <p>
        <a href="#" onClick={(e) => { e.preventDefault(); back(); }}>
          ← {t('All campaigns')}
        </a>
      </p>
      {q.isLoading ? (
        <Loading />
      ) : !c ? (
        <Fail q={q} />
      ) : c.error ? (
        <Card pad>
          <Notice kind="bad">
            {t('Meta could not be read:')} {c.error}
          </Notice>
        </Card>
      ) : (
        <>
          <Card pad>
            <div className="flxads-mgr__head">
              <div>
                <h2>
                  {c.name} <Status label={c.statusLabel} tone={c.statusTone} />
                </h2>
                <p className="flxads-muted">{c.summary}</p>
              </div>
              <Controls o={c} cur={c.currency} refresh={[FlxAdsCampaign]} />
            </div>
          </Card>
          <h2 className="flxads-h2">{t('Ad sets and ads')}</h2>
          <Card>
            <table className="flxads-table flxads-mgr">
              <thead>
                <tr>
                  <th>{t('Ad set / ad')}</th>
                  <th>{t('Budget')}</th>
                  <th className="num">{t('Spend')}</th>
                  <th className="num">{t('Impressions')}</th>
                  <th className="num">{t('Link clicks')}</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                {c.adsets.length === 0 && <Empty cols={6}>{t('No ad sets.')}</Empty>}
                {c.adsets.map((s) => (
                  <FragmentRows key={s.id}>
                    <tr className="flxads-mgr__set">
                      <Td l={t('Ad set')}>
                        <strong>{s.name}</strong> <Status label={s.statusLabel} tone={s.statusTone} />
                        {s.who !== '' && <small className="flxads-muted">{s.who}</small>}
                      </Td>
                      <Td l={t('Budget')}>{s.budgetLabel}</Td>
                      <MetricCells o={s} />
                      <td>
                        <Controls o={s} cur={c.currency} refresh={[FlxAdsCampaign]} />
                      </td>
                    </tr>
                    {s.ads.map((a) => (
                      <tr key={a.id} className="flxads-mgr__ad">
                        <Td l={t('Ad')}>
                          <div className="flxads-mgr__adcell">
                            {a.thumb && <img src={a.thumb} alt="" width={40} height={40} />}
                            <span>
                              {a.name} <Status label={a.statusLabel} tone={a.statusTone} />
                              {a.title !== '' && <small className="flxads-muted">{a.title}</small>}
                            </span>
                          </div>
                        </Td>
                        <td />
                        <MetricCells o={a} />
                        <td>
                          <Controls o={a} cur={c.currency} refresh={[FlxAdsCampaign]} />
                        </td>
                      </tr>
                    ))}
                  </FragmentRows>
                ))}
              </tbody>
            </table>
          </Card>
          <div className="flxads-bd">
            {[
              { title: t('Age and gender'), rows: c.ageGender },
              { title: t('Placement'), rows: c.placement },
            ].map((b) => {
              const max = Math.max(0.01, ...b.rows.map((r) => r.spend.amount));
              return (
                <Card key={b.title}>
                  <h3 className="flxads-h3">{b.title}</h3>
                  <table className="flxads-table">
                    <thead>
                      <tr>
                        <th />
                        <th className="num">{t('Spend')}</th>
                        <th className="num">{t('Link clicks')}</th>
                      </tr>
                    </thead>
                    <tbody>
                      {b.rows.length === 0 && <Empty cols={3}>{t('No data for this period.')}</Empty>}
                      {b.rows.slice(0, 12).map((r) => (
                        <tr key={r.label}>
                          <td>
                            <span className="flxads-bar" style={{ ['--w' as string]: Math.round((100 * r.spend.amount) / max) + '%' }} />
                            {r.label}
                          </td>
                          <td className="num">{r.spend.formatted}</td>
                          <td className="num">{num(r.clicks, locale)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </Card>
              );
            })}
          </div>
        </>
      )}
    </>
  );
}

const FragmentRows = ({ children }: { children: React.ReactNode }) => <>{children}</>;

function History() {
  const { t } = useEnv();
  const q = useFlxQuery(FlxAdsChangeHistory, { limit: 15 });
  const h = q.data?.flxAdsChangeHistory ?? [];
  if (h.length === 0) return null;
  return (
    <>
      <h2 className="flxads-h2">{t('Change history')}</h2>
      <Card>
        <table className="flxads-table">
          <thead>
            <tr>
              <th>{t('When')}</th>
              <th>{t('What')}</th>
              <th>{t('Change')}</th>
              <th>{t('Who')}</th>
            </tr>
          </thead>
          <tbody>
            {h.map((r, i) => (
              <tr key={i}>
                <Td l={t('When')}>{r.at?.relative ?? ''}</Td>
                <Td l={t('What')}>{r.name}</Td>
                <Td l={t('Change')}>{r.change}</Td>
                <Td l={t('Who')}>{[r.who, r.channel].filter(Boolean).join(' · ')}</Td>
              </tr>
            ))}
          </tbody>
        </table>
      </Card>
    </>
  );
}

export function SuggestionsTab() {
  const { t, tn, locale, ctx, ask, go } = useEnv();
  const q = useFlxQuery(FlxAdsSuggestions, {});
  const refresh = useAct(
    FlxAdsSuggestionsRefresh,
    (r) => r.flxAdsSuggestionsRefresh,
    (r) => tn('Checked: %d new suggestion.', 'Checked: %d new suggestions.', r.flxAdsSuggestionsRefresh?.newCount ?? 0, r.flxAdsSuggestionsRefresh?.newCount ?? 0),
    [FlxAdsSuggestions, FlxAdsContext],
  );
  const apply = useAct(FlxAdsSuggestionApply, (r) => r.flxAdsSuggestionApply, t('Applied in Meta and written to the change history.'), [FlxAdsSuggestions, FlxAdsContext, FlxAdsChangeHistory, FlxAdsCampaigns]);
  const dismiss = useAct(FlxAdsSuggestionDismiss, (r) => r.flxAdsSuggestionDismiss, t('Dismissed. It does not come back for 14 days.'), [FlxAdsSuggestions, FlxAdsContext]);
  const lead = <p className="flxads-lead">{t('Flux Ads checks your active Meta campaigns every day against what they brought on this site, and suggests what to change. Nothing changes until you press Apply; every change goes to the change history under Campaigns.')}</p>;
  if (!ctx.metaReady) return <>{lead}<Setup /></>;
  if (q.isLoading) return <Loading />;
  const p = q.data?.flxAdsSuggestions;
  if (!p) return <Fail q={q} />;
  const tone = (x: string) => (x === 'ok' ? 'good' : x);
  return (
    <>
      {lead}
      <div className="flxads-optbar">
        <p className="flxads-muted">
          {p.run?.at && t('Last check %s.', p.run.at.relative) + ' '}
          {p.rulesText}
          {ctx.canManage && (
            <>
              {' '}
              <a href="#" onClick={(e) => { e.preventDefault(); go('settings'); }}>
                {t('Change the rules')}
              </a>
            </>
          )}
        </p>
        {ctx.canManage && (
          <Btn disabled={refresh.isPending} onClick={() => refresh.mutate({ input: {} })}>
            {t('Check now')}
          </Btn>
        )}
      </div>
      {p.run && p.run.ok === false && (
        <Card pad>
          <Notice kind="bad">
            {t('Meta could not be read:')} {p.run.message}
          </Notice>
        </Card>
      )}
      {p.open.length === 0 && (
        <section className="flxads-card flxads-pad flxads-empty">{t('No suggestions now. Your active campaigns are within the target.')}</section>
      )}
      <div className="flxads-opts">
        {p.open.map((o) => (
          <article key={o.id} className={'flxads-card flxads-opt flxads-opt--' + tone(o.tone)}>
            <header>
              <span className={'flxads-pill flxads-pill--' + tone(o.tone)}>{o.typeLabel}</span> <small className="flxads-muted">{o.created?.relative}</small>
            </header>
            <h3>
              {o.level === 'season' ? (
                <a href="#" onClick={(e) => { e.preventDefault(); go('planner'); }}>{o.name}</a>
              ) : (
                <a href="#" onClick={(e) => { e.preventDefault(); go('campaigns'); }}>{o.name}</a>
              )}
            </h3>
            {o.context !== '' && <p className="flxads-muted flxads-opt__ctx">{o.context}</p>}
            <p>{o.reason}</p>
            {ctx.canManage && (
              <div className="flxads-actions flxads-actions--start">
                {o.action ? (
                  <Btn primary disabled={apply.isPending} onClick={() => ask({ text: o.action?.confirm ?? '', ok: o.action?.label ?? t('Apply'), run: () => apply.mutate({ input: { id: o.id } }) })}>
                    {o.action.label}
                  </Btn>
                ) : o.type === 'season' ? (
                  <Btn primary onClick={() => go('studio', { brief: o.brief, start: o.start, end: o.end })}>{t('Write a draft')}</Btn>
                ) : (
                  <Btn primary onClick={() => go('studio')}>{t('Open Studio')}</Btn>
                )}
                <Btn disabled={dismiss.isPending} onClick={() => dismiss.mutate({ input: { id: o.id } })}>
                  {t('Dismiss')}
                </Btn>
              </div>
            )}
          </article>
        ))}
      </div>
      {p.earlier.length > 0 && (
        <>
          <h2 className="flxads-h2">{t('Earlier suggestions')}</h2>
          <Card>
            <table className="flxads-table">
              <thead>
                <tr>
                  <th>{t('When')}</th>
                  <th>{t('Suggestion')}</th>
                  <th>{t('What')}</th>
                  <th>{t('Result')}</th>
                </tr>
              </thead>
              <tbody>
                {p.earlier.map((x) => (
                  <tr key={x.id}>
                    <Td l={t('When')}>{(x.decided ?? x.created)?.relative}</Td>
                    <Td l={t('Suggestion')}>{x.typeLabel}</Td>
                    <Td l={t('What')}>
                      {x.name} <small className="flxads-muted">{x.statusLabel}</small>
                    </Td>
                    <Td l={t('Result')}>{[x.who, x.result].filter(Boolean).join(' · ')}</Td>
                  </tr>
                ))}
              </tbody>
            </table>
          </Card>
        </>
      )}
      <span hidden>{locale}</span>
    </>
  );
}

export function ExperimentsTab() {
  const { t, ctx, ask } = useEnv();
  const q = useFlxQuery(FlxAdsExperiments, {});
  const ch = useFlxQuery(FlxAdsExperimentChoices, {}, { enabled: ctx.canManage && ctx.metaReady });
  const refresh = [FlxAdsExperiments, FlxAdsExperimentChoices];
  const create = useAct(FlxAdsExperimentCreate, (r) => r.flxAdsExperimentCreate, t('Test saved.'), refresh);
  const finish = useAct(FlxAdsExperimentFinish, (r) => r.flxAdsExperimentFinish, t('Test saved.'), refresh);
  const del = useAct(FlxAdsExperimentDelete, (r) => r.flxAdsExperimentDelete, t('Test deleted.'), refresh);
  const pause = useAct(FlxAdsExperimentPauseLoser, (r) => r.flxAdsExperimentPauseLoser, t('Status changed in Meta.'), [...refresh, FlxAdsCampaigns, FlxAdsChangeHistory]);
  const [ab, setAb] = useState({ a: '', b: '', start: '', end: '' });
  const [ho, setHo] = useState({ audience: '', pct: '10', start: '', end: '' });
  if (q.isLoading) return <Loading />;
  const list = q.data?.flxAdsExperiments;
  if (!list) return <Fail q={q} />;
  const c = ch.data?.flxAdsExperimentChoices;
  return (
    <>
      <p className="flxads-lead">{t('Find out what really works. A/B: two ads in the same campaign, compared by donations per click on this site. Holdout: a fixed share of an audience does not see the ads; comparing the two groups shows how many donations the ads really added.')}</p>
      {list.length === 0 && <section className="flxads-card flxads-pad flxads-empty">{t('No tests yet.')}</section>}
      {list.map((e) => (
        <section key={e.id} className="flxads-card flxads-pad flxads-exp">
          <header>
            <span className="flxads-pill flxads-pill--info">{e.typeLabel}</span> <span className={'flxads-pill flxads-pill--' + (e.running ? 'warn' : 'good')}>{e.running ? t('Running') : t('Ended')}</span>
          </header>
          <h3>{e.name}</h3>
          <p className="flxads-muted">
            {e.startLabel} – {e.endLabel} · {e.subject}
          </p>
          {e.ok ? (
            <>
              <table className="flxads-table flxads-exp__t">
                <thead>
                  <tr>
                    <th />
                    <th className={e.winner === 'a' ? 'num is-win' : 'num'}>{e.headA}</th>
                    <th className={e.winner === 'b' ? 'num is-win' : 'num'}>{e.headB}</th>
                  </tr>
                </thead>
                <tbody>
                  {e.table.map((r) => (
                    <tr key={r.label}>
                      <th scope="row">{r.label}</th>
                      <td className="num">{r.a}</td>
                      <td className="num">{r.b}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
              {e.confidence !== null && !e.small && (
                <div className="flxads-conf">
                  <span style={{ width: Math.min(100, Math.max(0, e.confidence)) + '%' }} />
                </div>
              )}
            </>
          ) : (
            e.error !== '' && <Notice kind="bad">{e.error}</Notice>
          )}
          <p className={'flxads-exp__verdict' + (e.winner !== '' ? ' is-win' : '')}>{e.verdict}</p>
          {ctx.canManage && (
            <div className="flxads-actions flxads-actions--start">
              {e.running && e.loserAd && (
                <Btn primary disabled={pause.isPending} onClick={() => ask({ text: t('Pause "%s" in Meta?', e.loserAd?.name ?? ''), ok: t('Pause "%s"', e.loserAd?.name ?? ''), run: () => pause.mutate({ input: { id: e.id } }) })}>
                  {t('Pause "%s"', e.loserAd.name)}
                </Btn>
              )}
              {e.running && (
                <Btn
                  disabled={finish.isPending}
                  onClick={() =>
                    ask({
                      text: e.type === 'holdout' ? t('End the test? The held-out people go back into the Meta audience.') : t('End the test? The result is kept.'),
                      ok: t('End test'),
                      run: () => finish.mutate({ input: { id: e.id } }),
                    })
                  }
                >
                  {t('End test')}
                </Btn>
              )}
              <Btn danger disabled={del.isPending} onClick={() => ask({ text: t('Delete this test and its result?'), ok: t('Delete'), danger: true, run: () => del.mutate({ input: { id: e.id } }) })}>
                {t('Delete')}
              </Btn>
            </div>
          )}
        </section>
      ))}
      {ctx.canManage && ctx.metaReady && c && (
        <>
          <h2 className="flxads-h2">{t('New A/B test')}</h2>
          <Card pad>
            {c.adGroups.length === 0 ? (
              <p className="flxads-muted">{t('No active campaign with ads. Create two variants in Studio first.')}</p>
            ) : (
              <>
                <p className="flxads-muted">{t('Pick two ads of the same campaign. Studio drafts already tag each variant (utm_content), so donations are counted per ad.')}</p>
                <form
                  className="flxads-builder"
                  onSubmit={(e) => {
                    e.preventDefault();
                    create.mutate({ input: { experiment: { type: 'ab', a: ab.a, b: ab.b, start: ab.start || c.today, end: ab.end } } });
                  }}
                >
                  {(['a', 'b'] as const).map((k) => (
                    <Field key={k} label={k === 'a' ? t('Ad A') : t('Ad B')}>
                      <select required value={ab[k]} onChange={(e) => setAb({ ...ab, [k]: e.target.value })}>
                        <option value="" />
                        {c.adGroups.map((g) => (
                          <optgroup key={g.campaign} label={g.campaign}>
                            {g.ads.map((a) => (
                              <option key={a.key} value={a.key}>
                                {a.label}
                              </option>
                            ))}
                          </optgroup>
                        ))}
                      </select>
                    </Field>
                  ))}
                  <Field label={t('Start')}>
                    <input type="date" value={ab.start || c.today} onChange={(e) => setAb({ ...ab, start: e.target.value })} />
                  </Field>
                  <Field label={t('End')}>
                    <input type="date" value={ab.end} onChange={(e) => setAb({ ...ab, end: e.target.value })} />
                  </Field>
                  <p className="flxads-actions flxads-actions--start">
                    <Btn type="submit" primary disabled={create.isPending}>
                      {t('Start test')}
                    </Btn>
                  </p>
                </form>
              </>
            )}
          </Card>
          <h2 className="flxads-h2">{t('New holdout test')}</h2>
          <Card pad>
            {c.audiences.length === 0 ? (
              <p className="flxads-muted">{t('Create a Meta audience that ads are shown to (Audiences) first. An audience runs one holdout test at a time.')}</p>
            ) : (
              <>
                <p className="flxads-muted">{t('The held-out people are removed from the Meta audience right away and put back when the test ends. Run it for at least 4 weeks; the bigger the audience, the sooner the answer.')}</p>
                <form
                  className="flxads-builder"
                  onSubmit={(e) => {
                    e.preventDefault();
                    ask({
                      text: t('Start the test? The held-out people are removed from the Meta audience now.'),
                      ok: t('Start test'),
                      run: () => create.mutate({ input: { experiment: { type: 'holdout', audience: ho.audience || c.audiences[0]?.id, pct: Number(ho.pct) || 10, start: ho.start || c.today, end: ho.end || c.defaultEnd } } }),
                    });
                  }}
                >
                  <Field label={t('Audience')}>
                    <select required value={ho.audience || c.audiences[0]?.id} onChange={(e) => setHo({ ...ho, audience: e.target.value })}>
                      {c.audiences.map((a) => (
                        <option key={a.id} value={a.id}>
                          {a.label}
                        </option>
                      ))}
                    </select>
                  </Field>
                  <Field label={t('Held out (%)')}>
                    <input type="number" min={5} max={50} value={ho.pct} onChange={(e) => setHo({ ...ho, pct: e.target.value })} />
                  </Field>
                  <Field label={t('Start')}>
                    <input type="date" value={ho.start || c.today} onChange={(e) => setHo({ ...ho, start: e.target.value })} />
                  </Field>
                  <Field label={t('End')}>
                    <input type="date" value={ho.end || c.defaultEnd} onChange={(e) => setHo({ ...ho, end: e.target.value })} />
                  </Field>
                  <p className="flxads-actions flxads-actions--start">
                    <Btn type="submit" primary disabled={create.isPending}>
                      {t('Start test')}
                    </Btn>
                  </p>
                </form>
              </>
            )}
          </Card>
        </>
      )}
      <span hidden>
        <Dot ch="" />
      </span>
    </>
  );
}
