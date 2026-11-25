// Recurring donations: the KPIs (monthly value, active, past due, canceled), status + search, the table
// (also on the donor card) and cancel at the payment provider with a confirmation. PHP preloads the first
// page of the filter in the address (FLXB_Admin::preload_subscriptions()).
import { useEffect, useMemo, useState, type ReactNode } from 'react';
import { keepPreviousData, useQueryClient } from '@tanstack/react-query';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { FlxDonateDashboard, FlxDonateDonor, FlxDonateSubscriptions, FlxSubscriptionCancel } from '@fluxwp/graphql/operations';
import type { FlxDonateSubscriptionsQuery, FlxSubscriptionListFilter } from '@fluxwp/graphql/types';
import { ListBar, Loading, Modal, Screen, listPerPage } from '@fluxwp/ui';
import { useHost } from '../host';
import { cursorAt, Go, Ico, Pager, useDonate } from './shared';

export type Sub = FlxDonateSubscriptionsQuery['flxSubscriptionList']['nodes'][number];
const STATUSES = ['active', 'past_due', 'canceled'] as const;

/** Address arguments (s, status) → the filter; only set keys, as PHP preloads it. */
export function subFilter(q: URLSearchParams): FlxSubscriptionListFilter {
  const f: FlxSubscriptionListFilter = {};
  const s = (q.get('s') ?? '').trim();
  if (s) f.search = s;
  const st = q.get('status') ?? '';
  if ((STATUSES as readonly string[]).includes(st)) f.status = st.toUpperCase() as FlxSubscriptionListFilter['status'];
  return f;
}

export function Subscriptions({ query, notice }: { query: string; notice: ReactNode }) {
  const host = useHost();
  const { t, tn } = useDonate();
  const start = new URLSearchParams(query);
  const [applied, setApplied] = useState({ s: start.get('s') ?? '', status: start.get('status') ?? '' });
  const [search, setSearch] = useState(applied.s);
  const [per, setPer] = useState(() => listPerPage('flxb-subscriptions', 20));
  const [offset, setOffset] = useState(() => {
    const p = Number(start.get('paged') ?? '1');
    return Number.isInteger(p) && p > 1 ? (p - 1) * per : 0;
  });
  const filter = useMemo(() => subFilter(new URLSearchParams(Object.entries(applied).filter(([, v]) => v !== ''))), [applied]);
  const q = useFlxQuery(FlxDonateSubscriptions, { first: per, after: cursorAt(offset), filter: Object.keys(filter).length ? filter : undefined }, { placeholderData: keepPreviousData });
  const list = q.data?.flxSubscriptionList;
  const tot = q.data?.flxSubscriptionTotals;

  useEffect(() => {
    if (host.mode !== 'wp' || typeof history === 'undefined') return;
    const u = new URL(location.href);
    if (u.searchParams.get('page') !== 'flxb-subscriptions') return;
    const next = new URLSearchParams({ page: 'flxb-subscriptions' });
    if (applied.s.trim()) next.set('s', applied.s.trim());
    if (applied.status) next.set('status', applied.status);
    if (offset) next.set('paged', String(Math.floor(offset / per) + 1));
    const s = '?' + next.toString();
    if (u.search !== s) history.replaceState(history.state, '', u.pathname + s);
  }, [applied, offset, per, host.mode]);

  const seg: { key: string; label: string }[] = [
    { key: '', label: t('All') },
    { key: 'active', label: t('Active') },
    { key: 'past_due', label: t('Past due') },
    { key: 'canceled', label: t('Canceled') },
  ];
  const total = list?.totalCount ?? 0;

  return (
    <Screen className="flxdn" width="wide" icon={host.appIcon} title={t('Recurring donations')} subtitle={t('Monthly, weekly or yearly gifts charged automatically by the payment provider.')} notice={notice}>
      {tot ? (
        <div className="flxdn-kpis">
          <div className="flxdn-kpi">
            <div className="flxdn-kpi__label">{t('Recurring (monthly)')}</div>
            <div className="flxdn-kpi__value">{tot.mrrLabel}</div>
            <div className="flxdn-kpi__sub">{t('monthly value of active ones')}</div>
          </div>
          <Count label={t('Active')} n={tot.active} accent="#2F6FA3" />
          <Count label={t('Past due')} n={tot.pastDue} accent="#E0A526" />
          <Count label={t('Canceled')} n={tot.canceled} accent="#7A8C96" />
        </div>
      ) : null}
      <ListBar
        id="flxb-subscriptions"
        className="flxdn-filters flxdn-filters--lib"
        onSubmit={() => {
          setOffset(0);
          setApplied({ ...applied, s: search.trim() });
        }}
        search={<input type="search" value={search} onChange={(e) => setSearch(e.target.value)} placeholder={t('Name, e-mail, phone, reference…')} aria-label={t('Search')} />}
        primary={
          <label>
            <span>{t('Status')}</span>
            <select
              value={applied.status}
              onChange={(e) => {
                setOffset(0);
                setApplied({ s: search.trim(), status: e.target.value });
              }}
            >
              {seg.map((o) => (
                <option key={o.key} value={o.key}>
                  {o.label}
                </option>
              ))}
            </select>
          </label>
        }
      />
      <div className={'flxdn-panel' + (q.isFetching && list ? ' is-busy' : '')}>
        {!q.data ? (
          q.isError ? (
            <Failed retry={() => void q.refetch()} />
          ) : (
            <Loading />
          )
        ) : !list?.nodes.length ? (
          <div className="flxdn-emptystate">
            <Ico name="repeat" size={32} />
            <h2>{t('No recurring donations yet')}</h2>
            <p>{t('A recurring donation is created when a payment form is set to charge every day, week, month or year.')}</p>
          </div>
        ) : (
          <>
            <SubsTable rows={list.nodes} showDonor />
            <div className="flxdn-tablefoot">
              <span>{tn('%s recurring donation', '%s recurring donations', total, total.toLocaleString())}</span>
              <Pager id="flxb-subscriptions" offset={offset} per={per} total={total} onGo={setOffset} onPer={(v) => (setPer(v), setOffset(0))} />
            </div>
          </>
        )}
      </div>
    </Screen>
  );
}

function Count({ label, n, accent }: { label: string; n: number; accent: string }) {
  return (
    <div className="flxdn-kpi" style={{ '--accent': accent } as React.CSSProperties}>
      <div className="flxdn-kpi__label">{label}</div>
      <div className="flxdn-kpi__value">{n.toLocaleString()}</div>
    </div>
  );
}

export function Failed({ retry }: { retry: () => void }) {
  const { t } = useDonate();
  return (
    <div className="flxdn-empty">
      <p>{t('The data could not be loaded.')}</p>
      <button type="button" className="flxdn-btn" onClick={retry}>
        {t('Try again')}
      </button>
    </div>
  );
}

/** The recurring donation table; the donor column is left out on the donor card. */
export function SubsTable({ rows, showDonor }: { rows: Sub[]; showDonor: boolean }) {
  const { t, told } = useDonate();
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const [ask, setAsk] = useState<Sub | null>(null);
  const cancel = useFlxMutation(FlxSubscriptionCancel, {
    onSuccess: (r) => {
      const res = r.flxSubscriptionCancel;
      const err = res?.errors?.[0]?.message;
      if (err) told({ kind: 'error', text: err });
      else if (res?.message) told({ kind: 'ok', text: res.message });
      for (const op of [FlxDonateSubscriptions, FlxDonateDonor, FlxDonateDashboard]) void qc.invalidateQueries({ queryKey: flxQueryKey(scope, op) });
    },
    onError: (e) => told({ kind: 'error', text: e.message || t('The action could not be completed.') }),
    onSettled: () => setAsk(null),
  });
  const act = rows.some((r) => r.canCancel);
  return (
    <div className="flxdn-tablewrap">
      <table className="flxdn-table flxdn-table--subs">
        <thead>
          <tr>
            {showDonor ? <th scope="col">{t('Donor')}</th> : null}
            <th scope="col" className="num">
              {t('Amount')}
            </th>
            <th scope="col">{t('Interval')}</th>
            <th scope="col">{t('Status')}</th>
            <th scope="col">{t('Started')}</th>
            <th scope="col">{t('Next charge')}</th>
            <th scope="col">{t('Gifts')}</th>
            {act ? (
              <th scope="col">
                <span className="screen-reader-text">{t('Actions')}</span>
              </th>
            ) : null}
          </tr>
        </thead>
        <tbody>
          {rows.map((s) => (
            <tr key={s.databaseId}>
              {showDonor ? (
                <td className="flxdn-col-main">
                  {s.donorUrl ? <Go href={s.donorUrl}>{s.donorName}</Go> : <span className="flxdn-muted">{t('Anonymous')}</span>}
                  {s.reference ? (
                    <span className="flxdn-sub2">
                      <code>{s.reference}</code>
                    </span>
                  ) : null}
                </td>
              ) : null}
              <td className={'num' + (showDonor ? '' : ' flxdn-col-main')} data-label={t('Amount')}>
                <span>{s.amountLabel}</span>
              </td>
              <td data-label={t('Interval')}>
                <span>{s.intervalLabel}</span>
              </td>
              <td data-label={t('Status')}>
                <span>
                  <span className={'flxdn-pill flxdn-pill--' + s.tone.toLowerCase()}>{s.statusLabel}</span>
                  {s.note ? <span className="flxdn-sub2">{s.note}</span> : null}
                </span>
              </td>
              <td className="flxdn-col-date" data-label={t('Started')}>
                <span>{s.startedLabel || '—'}</span>
              </td>
              <td className="flxdn-col-date" data-label={t('Next charge')}>
                <span>{s.nextLabel || '—'}</span>
              </td>
              <td data-label={t('Gifts')}>
                <span>
                  <Go href={s.giftsUrl}>{s.gifts.toLocaleString()}</Go>
                </span>
              </td>
              {act ? (
                <td className="flxdn-col-act">
                  {s.canCancel ? (
                    <button type="button" className="flxdn-linkbtn flxdn-linkbtn--danger" onClick={() => setAsk(s)}>
                      {t('Cancel')}
                    </button>
                  ) : null}
                </td>
              ) : null}
            </tr>
          ))}
        </tbody>
      </table>
      {ask ? (
        <Modal title={t('Cancel recurring donation')} onClose={() => !cancel.isPending && setAsk(null)}>
          <p>{t('Cancel this recurring donation at the payment provider? No further charges will be made.')}</p>
          <div className="flxdn-actions">
            <button type="button" className="flxdn-btn" disabled={cancel.isPending} onClick={() => setAsk(null)}>
              {t('Close')}
            </button>
            <button type="button" className="flxdn-btn flxdn-btn--primary flxdn-btn--danger-solid" disabled={cancel.isPending} onClick={() => cancel.mutate({ id: ask.databaseId })}>
              {t('Cancel recurring donation')}
            </button>
          </div>
        </Modal>
      ) : null}
    </div>
  );
}
