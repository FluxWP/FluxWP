// Report, Ad spend, Donor value, Send log and Ad link tabs.
import { useState } from 'react';
import { useFlxQuery } from '@fluxwp/graphql/react';
import {
  FlxAdsLinkBuilder,
  FlxAdsReport,
  FlxAdsSendLog,
  FlxAdsSendRetry,
  FlxAdsSpend,
  FlxAdsSpendAdd,
  FlxAdsSpendDelete,
  FlxAdsSpendSync,
  FlxAdsValue,
} from '@fluxwp/graphql/operations';
import { DatePicker, Loading } from '@fluxwp/ui';
import { Btn, Card, Dot, Empty, Field, FilterBar, Kpi, Muted, Notice, Pill, Roas, Td, num, roas, toVars, useAct, useEnv, useFilter } from './common';
import { Fail } from './AdsScreen';

const RANGES = ['7', '30', '90', '365', 'custom'];

export function ReportTab() {
  const { t, tn, locale, go } = useEnv();
  const f = useFilter({});
  const q = useFlxQuery(FlxAdsReport, { filter: toVars(f.applied) });
  if (q.isLoading) return <Loading />;
  const r = q.data?.flxAdsReport;
  if (!r) return <Fail q={q} />;
  const tot = r.totals;
  const base = r.base;
  const sc = r.showSpendColumns;
  return (
    <>
      <FilterBar f={f} dims={r.dimensions} ranges={RANGES} from={r.from} to={r.to} />
      {r.channel !== null && r.channel !== '' && (
        <p className="flxads-chip">
          {r.channelLabel}{' '}
          <a href="#" aria-label={t('Clear filter')} onClick={(e) => { e.preventDefault(); f.setApplied({ ...f.applied, channel: '' }); f.patch({ channel: '' }); }}>
            ×
          </a>
        </p>
      )}
      <div className="flxads-kpis">
        <Kpi label={t('Brought by ads')} value={tot.adsAmount.formatted} hint={tot.adsPercent !== null ? t('%s%% of all income', num(tot.adsPercent, locale, 1)) : ''} />
        <Kpi label={t('Payments from ads')} value={num(tot.adsCount, locale)} hint={t('of %s in total', num(tot.count, locale))} />
        <Kpi label={t('Total income')} value={tot.amount.formatted} hint={r.fromLabel + ' – ' + r.toLabel} />
        <Kpi label={t('New donors and customers')} value={num(tot.newCount, locale)} hint={t('first paid order')} />
      </div>
      {tot.hasSpend && tot.spend && (
        <div className="flxads-kpis flxads-kpis--spend">
          <Kpi label={t('Ad spend')} value={tot.spend.formatted} hint={tot.clicks ? t('%s link clicks', num(tot.clicks, locale)) : ''} />
          <Kpi label={t('Return on ad spend (ROAS)')} value={roas(tot.roas, locale)} hint={t('%s income from these ads', tot.spendIncome.formatted)} />
          <Kpi label={t('Cost per payment')} value={tot.cpa?.formatted ?? '—'} hint={t('%s payments', num(tot.spendCount, locale))} />
          <Kpi label={t('Cost per new donor')} value={tot.cpn?.formatted ?? '—'} hint={t('%s new', num(tot.spendNew, locale))} />
        </div>
      )}
      {tot.mixed && <p className="flxads-muted">{t('Some payments are in another currency and no exchange rate was found; their amount is shown per currency in the row but not added to the totals. Install Flux CRM for currency conversion.')}</p>}
      <Card>
        <table className="flxads-table">
          <thead>
            <tr>
              <th>{r.byLabel}</th>
              <th className="num">{t('Payments')}</th>
              <th className="num">{t('Amount')}</th>
              {sc && (
                <>
                  <th className="num">{t('Spend')}</th>
                  <th className="num">ROAS</th>
                </>
              )}
              <th className="num">{t('New')}</th>
              <th className="share">{t('Share')}</th>
            </tr>
          </thead>
          <tbody>
            {r.rows.length === 0 && <Empty cols={sc ? 7 : 5}>{t('No paid donation or order in this period yet. Use the Ad link tab to tag your ads.')}</Empty>}
            {r.rows.map((row) => (
              <tr key={row.key + row.channel}>
                <Td l={r.byLabel}>
                  <Dot ch={row.channel} />
                  {r.by === 'channel' ? (
                    <a
                      href="#"
                      onClick={(e) => {
                        e.preventDefault();
                        const n = { ...f.applied, channel: row.key === '' ? '-' : row.key, by: 'campaign' };
                        f.setApplied(n);
                        f.setDraft(n);
                      }}
                    >
                      {row.label}
                    </a>
                  ) : (
                    row.label
                  )}
                  {r.by === 'campaign' && row.key !== '' && <small className="flxads-muted"> {row.channelLabel}</small>}
                </Td>
                <Td l={t('Payments')} num>
                  {num(row.count, locale)}
                </Td>
                <Td l={t('Amount')} num>
                  <strong>{row.amount.formatted}</strong>
                  {(row.amounts.length > 1 || row.otherCurrency) && <small className="flxads-muted">{row.amounts.map((a) => a.formatted).join(' · ')}</small>}
                </Td>
                {sc && (
                  <>
                    <Td l={t('Spend')} num>
                      {row.hasSpend && row.spend ? (
                        <span>
                          {row.spend.formatted}
                          {row.cpa && <small className="flxads-muted">{t('%s per payment', row.cpa.formatted)}</small>}
                        </span>
                      ) : (
                        <Muted>—</Muted>
                      )}
                    </Td>
                    <Td l="ROAS" num>
                      {row.hasSpend ? <Roas v={row.roas} level={row.roasLevel} /> : <Muted>—</Muted>}
                    </Td>
                  </>
                )}
                <Td l={t('New')} num>
                  {num(row.newCount, locale)}
                </Td>
                <Td l={t('Share')} className="share">
                  <span className="flxads-bar">
                    <i style={{ width: Math.min(100, row.share) + '%' }} />
                  </span>
                  <small>{num(row.share, locale, 1)}%</small>
                </Td>
              </tr>
            ))}
          </tbody>
        </table>
      </Card>
      <h2 className="flxads-h2">{t('Latest payments from ads')}</h2>
      <Card>
        <table className="flxads-table">
          <thead>
            <tr>
              <th>{t('Date')}</th>
              <th>{t('Order')}</th>
              <th>{t('Ad')}</th>
              <th className="num">{t('Amount')}</th>
            </tr>
          </thead>
          <tbody>
            {r.recent.length === 0 && <Empty cols={4}>{t('None yet.')}</Empty>}
            {r.recent.map((o) => (
              <tr key={o.orderId}>
                <Td l={t('Date')}>{o.paidAt?.label ?? ''}</Td>
                <Td l={t('Order')}>
                  {o.editUrl ? <a href={o.editUrl}>#{o.orderNumber}</a> : '#' + o.orderNumber}{' '}
                  <small className="flxads-muted">
                    {o.isDonation ? t('Donation') : t('Order')}
                    {o.newPerson ? ' · ' + t('new') : ''}
                  </small>
                </Td>
                <Td l={t('Ad')}>
                  <Dot ch={o.channel} />
                  {o.summary}
                </Td>
                <Td l={t('Amount')} num>
                  {o.total.formatted}
                </Td>
              </tr>
            ))}
          </tbody>
        </table>
      </Card>
      <p className="flxads-muted">
        {tn('%s group', '%s groups', r.rows.length, num(r.rows.length, locale))} ·{' '}
        <a href="#" onClick={(e) => { e.preventDefault(); go('links'); }}>
          {t('Ad link')}
        </a>
      </p>
    </>
  );
}

export function SpendTab() {
  const { t, locale, ctx, go } = useEnv();
  const f = useFilter({});
  const q = useFlxQuery(FlxAdsSpend, { filter: toVars(f.applied) });
  const sync = useAct(FlxAdsSpendSync, (r) => r.flxAdsSpendSync, t('Meta spend updated.'), [FlxAdsSpend]);
  const add = useAct(FlxAdsSpendAdd, (r) => r.flxAdsSpendAdd, t('Spend added.'), [FlxAdsSpend, FlxAdsReport], () => setRow((o) => ({ ...o, campaign: '', ad: '', spend: '' })));
  const del = useAct(FlxAdsSpendDelete, (r) => r.flxAdsSpendDelete, t('Spend deleted.'), [FlxAdsSpend, FlxAdsReport]);
  const [row, setRow] = useState({ channel: 'google', day: new Date().toISOString().slice(0, 10), campaign: '', ad: '', spend: '', currency: '' });
  if (q.isLoading) return <Loading />;
  const p = q.data?.flxAdsSpend;
  if (!p) return <Fail q={q} />;
  const st = p.metaStatus;
  return (
    <>
      <Card pad>
        <div className="flxads-platform__head">
          <h2 className="flxads-h2">
            <Dot ch="meta" />
            {t('Meta ad spend')}
          </h2>
          {ctx.canManage && p.metaReady && (
            <Btn small disabled={sync.isPending} onClick={() => sync.mutate({ input: {} })}>
              {t('Update now')}
            </Btn>
          )}
        </div>
        {!p.metaReady ? (
          <>
            <p className="flxads-muted">{t('Enter the ad account ID in Settings › Meta. Spend per campaign and ad is then read every 6 hours, so the report shows ROAS and cost per donation.')}</p>
            {ctx.canManage && (
              <p>
                <Btn onClick={() => go('settings')}>{t('Settings')}</Btn>
              </p>
            )}
          </>
        ) : !st ? (
          <p className="flxads-muted">{t('Not read yet. It runs in the background after this page loads; refresh in a minute.')}</p>
        ) : st.ok ? (
          <Notice kind="ok">{t('Updated %1$s: %2$s – %3$s, %4$d rows. Updates every 6 hours.', st.at?.relative ?? '', st.sinceLabel, st.untilLabel, st.rows)}</Notice>
        ) : (
          <>
            <Notice kind="bad">
              {t('Last update failed:')} {st.message} <small>{st.at?.relative}</small>
            </Notice>
            <p className="flxads-muted">{t('The key needs the ads_read permission on this ad account: Business Settings › Users › System users › add the ad account › Generate token (ads_read).')}</p>
          </>
        )}
      </Card>
      <FilterBar f={f} ranges={['7', '30', '90', '365', 'custom']} from={p.from} to={p.to} showKind={false} />
      <Card>
        <table className="flxads-table">
          <thead>
            <tr>
              <th>{t('Campaign')}</th>
              <th>{t('Channel')}</th>
              <th className="num">{t('Spend')}</th>
            </tr>
          </thead>
          <tbody>
            {p.campaigns.length === 0 && <Empty cols={3}>{t('No spend in this period.')}</Empty>}
            {p.campaigns.map((c, i) => (
              <tr key={i}>
                <Td l={t('Campaign')}>{c.name}</Td>
                <Td l={t('Channel')}>
                  <Dot ch={c.channel} />
                  {c.channelLabel}
                </Td>
                <Td l={t('Spend')} num>
                  {c.spend.formatted}
                </Td>
              </tr>
            ))}
            {p.campaigns.length > 0 && (
              <tr className="flxads-total">
                <td>{t('Total')}</td>
                <td />
                <Td l={t('Total')} num>
                  <strong>{p.total.formatted}</strong>
                </Td>
              </tr>
            )}
          </tbody>
        </table>
      </Card>
      <p className="flxads-muted">{t('Spend is matched to donations by campaign name (utm_campaign) and ad name (utm_content). Use the Meta URL parameters from the Ad link tab so the names are the same.')}</p>
      {ctx.canManage && (
        <>
          <h2 className="flxads-h2">{t('Add spend by hand')}</h2>
          <Card pad>
            <p className="flxads-muted">{t('For Google Ads, TikTok, posters or anything not read automatically. Use the same campaign name as in the ad link.')}</p>
            <form
              className="flxads-builder"
              onSubmit={(e) => {
                e.preventDefault();
                add.mutate({ input: { ...row, currency: row.currency || p.base } });
              }}
            >
              <Field label={t('Channel')}>
                <select value={row.channel} onChange={(e) => setRow({ ...row, channel: e.target.value })}>
                  {p.channels.filter((c) => c.key !== '').map((c) => (
                    <option key={c.key} value={c.key}>
                      {c.label}
                    </option>
                  ))}
                </select>
              </Field>
              <Field label={t('Date')}>
                <DatePicker required value={row.day} onChange={(v) => setRow({ ...row, day: v })} />
              </Field>
              <Field label={t('Campaign')}>
                <input value={row.campaign} placeholder="fidan-kasim-2026" onChange={(e) => setRow({ ...row, campaign: e.target.value })} />
              </Field>
              <Field label={t('Ad (optional)')}>
                <input value={row.ad} placeholder="video-1" onChange={(e) => setRow({ ...row, ad: e.target.value })} />
              </Field>
              <Field label={t('Amount')}>
                <input inputMode="decimal" required value={row.spend} placeholder="1500" onChange={(e) => setRow({ ...row, spend: e.target.value })} />
              </Field>
              <Field label={t('Currency')}>
                <input maxLength={3} value={row.currency || p.base} onChange={(e) => setRow({ ...row, currency: e.target.value })} />
              </Field>
              <p>
                <Btn type="submit" primary disabled={add.isPending}>
                  {t('Add spend')}
                </Btn>
              </p>
            </form>
          </Card>
          {p.manual.length > 0 && (
            <Card>
              <table className="flxads-table">
                <thead>
                  <tr>
                    <th>{t('Date')}</th>
                    <th>{t('Channel')}</th>
                    <th>{t('Campaign')}</th>
                    <th className="num">{t('Amount')}</th>
                    <th />
                  </tr>
                </thead>
                <tbody>
                  {p.manual.map((m) => (
                    <tr key={m.id}>
                      <Td l={t('Date')}>{m.dayLabel}</Td>
                      <Td l={t('Channel')}>
                        <Dot ch={m.channel} />
                        {m.channelLabel}
                      </Td>
                      <Td l={t('Campaign')}>{(m.campaign + (m.ad ? ' · ' + m.ad : '')).trim()}</Td>
                      <Td l={t('Amount')} num>
                        {m.spend.formatted}
                      </Td>
                      <td>
                        <Btn small danger disabled={del.isPending} onClick={() => del.mutate({ input: { id: m.id } })}>
                          {t('Delete')}
                        </Btn>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </Card>
          )}
        </>
      )}
      <span hidden>{locale}</span>
    </>
  );
}

export function ValueTab() {
  const { t, locale } = useEnv();
  const f = useFilter({ range: '90', by: 'campaign' });
  const q = useFlxQuery(FlxAdsValue, { filter: toVars(f.applied) });
  if (q.isLoading) return <Loading />;
  const r = q.data?.flxAdsValue;
  if (!r) return <Fail q={q} />;
  const tot = r.totals;
  const label = r.by === 'channel' ? t('Channel') : t('Campaign');
  const dash = <Muted>—</Muted>;
  const max = Math.max(0, ...r.cohort.flatMap((c) => c.cells.map((x) => x?.amount ?? 0)));
  return (
    <>
      <p className="flxads-lead">{t('What the people an ad brought in gave in total, not only their first payment. A campaign whose first gifts are cheap but never come back shows up here.')}</p>
      <FilterBar
        f={f}
        ranges={['30', '90', '365', 'custom']}
        periodLabel={t('First payment in')}
        from={r.from}
        to={r.to}
        dims={[
          { key: 'campaign', label: t('Campaign') },
          { key: 'channel', label: t('Channel') },
        ]}
      />
      <div className="flxads-kpis">
        <Kpi label={t('New donors and customers')} value={num(tot.newCount, locale)} hint={t('%s of them from ads', num(tot.adsNew, locale))} />
        <Kpi label={t('Value per person from ads')} value={tot.adsAvg?.formatted ?? '—'} hint={t('all payments so far')} />
        <Kpi label={t('Cost to win one (CAC)')} value={tot.cac?.formatted ?? '—'} hint={tot.spend ? t('%s spend', tot.spend.formatted) : t('no spend recorded')} />
        <Kpi label={t('Value / cost')} value={roas(tot.ratio, locale)} hint={tot.rate === null ? '' : t('%s%% gave again', num(tot.rate, locale, 1))} />
      </div>
      <Card>
        <table className="flxads-table">
          <thead>
            <tr>
              <th>{label}</th>
              <th className="num">{t('New')}</th>
              <th className="num">{t('First payment')}</th>
              <th className="num">{t('90 days')}</th>
              <th className="num">{t('So far')}</th>
              <th className="num">{t('Per person')}</th>
              <th className="num">{t('Gave again')}</th>
              <th className="num">CAC</th>
              <th className="num">{t('Value / cost')}</th>
              <th className="num">{t('Paid back in')}</th>
            </tr>
          </thead>
          <tbody>
            {r.rows.length === 0 && <Empty cols={10}>{t('Nobody made a first payment in this period yet.')}</Empty>}
            {r.rows.map((row) => (
              <tr key={row.key + row.channel}>
                <Td l={label}>
                  <Dot ch={row.channel} />
                  {row.label}
                  {r.by === 'campaign' && row.channel !== '' && <small className="flxads-muted"> {row.channelLabel}</small>}
                </Td>
                <Td l={t('New')} num>{num(row.newCount, locale)}</Td>
                <Td l={t('First payment')} num>{row.first.formatted}</Td>
                <Td l={t('90 days')} num>{row.ltv90.formatted}</Td>
                <Td l={t('So far')} num><strong>{row.ltv.formatted}</strong></Td>
                <Td l={t('Per person')} num>{row.avg ? row.avg.formatted : dash}</Td>
                <Td l={t('Gave again')} num>{row.rate === null ? dash : num(row.rate, locale, 1) + '%'}</Td>
                <Td l="CAC" num>
                  {row.cac ? row.cac.formatted : row.spend ? <span>{row.spend.formatted}<small className="flxads-muted">{t('nobody new')}</small></span> : dash}
                </Td>
                <Td l={t('Value / cost')} num>{row.ratio === null ? dash : <Roas v={row.ratio} level={row.roasLevel} />}</Td>
                <Td l={t('Paid back in')} num>
                  {row.payback !== null ? t('%d days', row.payback) : row.spend && row.spend.amount > 0 ? <Muted>{t('not yet')}</Muted> : dash}
                </Td>
              </tr>
            ))}
          </tbody>
        </table>
      </Card>
      <p className="flxads-muted">{t('A person belongs to the campaign of their first payment. Their later payments count for that campaign even when they came back without an ad. CAC is the campaign spend in this period divided by the new people it brought.')}</p>
      <h2 className="flxads-h2">{t('Value by month of first payment')}</h2>
      <p className="flxads-muted">{t('Each row is the people who paid for the first time that month. The cells show what one person gave in total by the end of each month since then.')}</p>
      <Card>
        <div className="flxads-scroll">
          <table className="flxads-table flxads-cohort">
            <thead>
              <tr>
                <th>{t('Month')}</th>
                <th className="num">{t('People')}</th>
                {Array.from({ length: r.cohortMonths }, (_, i) => (
                  <th key={i} className="num">{t('M%d', i)}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {r.cohort.length === 0 && <Empty cols={r.cohortMonths + 2}>{t('None yet.')}</Empty>}
              {r.cohort.map((c) => (
                <tr key={c.month}>
                  <th scope="row">{c.label}</th>
                  <td className="num">{num(c.newCount, locale)}</td>
                  {c.cells.map((x, i) =>
                    x === null ? (
                      <td key={i} className="num flxads-cohort__na" />
                    ) : (
                      <td key={i} className="num" style={{ ['--a' as string]: max > 0 ? (0.04 + (0.32 * x.amount) / max).toFixed(2) : 0 }}>
                        {x.formatted}
                      </td>
                    ),
                  )}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </Card>
    </>
  );
}

export function LogTab() {
  const { t, locale, ctx } = useEnv();
  const q = useFlxQuery(FlxAdsSendLog, {});
  const retry = useAct(FlxAdsSendRetry, (r) => r.flxAdsSendRetry, t('Sent again. The row shows the result.'), [FlxAdsSendLog]);
  if (q.isLoading) return <Loading />;
  const l = q.data?.flxAdsSendLog;
  if (!l) return <Fail q={q} />;
  const why: Record<string, string> = {
    no_consent: t('visitor did not accept cookies'),
    browser_sent: t('already sent from the browser'),
    platform_off: t('platform turned off'),
    order_missing: t('order deleted'),
  };
  return (
    <>
      <div className="flxads-kpis">
        <Kpi label={t('Sent')} value={num(l.sent, locale)} />
        <Kpi label={t('Waiting')} value={num(l.pending, locale)} />
        <Kpi label={t('Failed')} value={num(l.failed, locale)} />
        <Kpi label={t('Not sent')} value={num(l.skipped, locale)} hint={t('no consent, or sent from the browser')} />
      </div>
      <Card>
        <table className="flxads-table">
          <thead>
            <tr>
              <th>{t('Date')}</th>
              <th>{t('Platform')}</th>
              <th>{t('Order')}</th>
              <th className="num">{t('Value')}</th>
              <th>{t('Status')}</th>
              <th />
            </tr>
          </thead>
          <tbody>
            {l.rows.length === 0 && <Empty cols={6}>{t('Nothing sent yet. When a donation is paid, a Purchase event is sent to every platform that has its server key.')}</Empty>}
            {l.rows.map((r) => (
              <tr key={r.id}>
                <Td l={t('Date')}>{r.at?.label ?? ''}</Td>
                <Td l={t('Platform')}>{r.platformLabel}</Td>
                <Td l={t('Order')}>{r.editUrl ? <a href={r.editUrl}>#{r.orderNumber}</a> : '#' + r.orderNumber}</Td>
                <Td l={t('Value')} num>{r.value.formatted}</Td>
                <Td l={t('Status')}>
                  <span className={'flxads-pill flxads-pill--' + r.status}>{r.statusLabel}</span>
                  {r.reason !== '' && <small className="flxads-muted"> {why[r.reason] ?? r.reason}</small>}
                  {r.attempts > 1 && <small className="flxads-muted"> {t('%d attempts', r.attempts)}</small>}
                </Td>
                <td>
                  {ctx.canManage && r.canRetry && (
                    <Btn small disabled={retry.isPending} onClick={() => retry.mutate({ input: { id: r.id } })}>
                      {t('Send again')}
                    </Btn>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </Card>
    </>
  );
}

const slug = (v: string) =>
  v
    .trim()
    .toLowerCase()
    .replace(/[çÇ]/g, 'c')
    .replace(/[ğĞ]/g, 'g')
    .replace(/[ıİ]/g, 'i')
    .replace(/[öÖ]/g, 'o')
    .replace(/[şŞ]/g, 's')
    .replace(/[üÜ]/g, 'u')
    .replace(/[^a-z0-9_-]+/g, '-')
    .replace(/^-+|-+$/g, '');

export function LinksTab() {
  const { t, notify } = useEnv();
  const q = useFlxQuery(FlxAdsLinkBuilder, {});
  const [v, setV] = useState({ url: '', source: 'facebook', medium: 'paid_social', campaign: '', content: '' });
  if (q.isLoading) return <Loading />;
  const b = q.data?.flxAdsLinkBuilder;
  if (!b) return <Fail q={q} />;
  let out = '';
  try {
    const u = new URL(v.url || b.homeUrl);
    for (const [k, val] of [['utm_source', v.source], ['utm_medium', v.medium], ['utm_campaign', v.campaign], ['utm_content', v.content]]) {
      const s = slug(val ?? '');
      if (s) u.searchParams.set(k ?? '', s);
    }
    out = u.toString();
  } catch {
    out = '';
  }
  const copy = () => {
    void navigator.clipboard?.writeText(out).then(() => notify('success', t('Copied')));
  };
  return (
    <Card pad>
      <h2 className="flxads-h2">{t('Tag each ad with its own link')}</h2>
      <p className="flxads-muted">{t("Meta, Google and TikTok add their click ID by themselves. A UTM link also names the campaign and the ad, so the report can show \"which ad brought which donation\". Paste the link as the ad's website URL; use a different \"Ad\" value for each ad or video.")}</p>
      <div className="flxads-builder">
        <Field label={t('Page')}>
          <input type="url" list="flxads-pages" value={v.url || b.homeUrl} onChange={(e) => setV({ ...v, url: e.target.value })} />
          <datalist id="flxads-pages">
            {b.pages.map((p) => (
              <option key={p} value={p} />
            ))}
          </datalist>
        </Field>
        <Field label={t('Platform')}>
          <select value={v.source} onChange={(e) => setV({ ...v, source: e.target.value })}>
            {b.sources.map((o) => (
              <option key={o.key} value={o.key}>{o.label}</option>
            ))}
          </select>
        </Field>
        <Field label={t('Type')}>
          <select value={v.medium} onChange={(e) => setV({ ...v, medium: e.target.value })}>
            {b.mediums.map((o) => (
              <option key={o.key} value={o.key}>{o.label}</option>
            ))}
          </select>
        </Field>
        <Field label={t('Campaign')}>
          <input value={v.campaign} placeholder="fidan-kasim-2026" onChange={(e) => setV({ ...v, campaign: e.target.value })} />
        </Field>
        <Field label={t('Ad')}>
          <input value={v.content} placeholder="video-1" onChange={(e) => setV({ ...v, content: e.target.value })} />
        </Field>
        <div className="flxads-out">
          <code>{out}</code>
          <Btn primary onClick={copy}>{t('Copy')}</Btn>
        </div>
      </div>
      <p className="flxads-muted">{t("Meta tip: instead of one link per ad you can put this in the ad's URL parameters field: utm_source={{site_source_name}}&utm_medium=paid_social&utm_campaign={{campaign.name}}&utm_content={{ad.name}}")}</p>
    </Card>
  );
}

export { Pill };
