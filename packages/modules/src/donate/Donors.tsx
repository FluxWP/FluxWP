// Donor list: search, kind (recurring / may be contacted) and sort, kept in the address bar; the whole row
// opens the donor card. Staff with flxb_donors_own see "My donors" with only their referrals' figures.
// PHP preloads the first page of the filter in the address (FLXB_Admin::preload_donors()).
import { useEffect, useMemo, useState, type MouseEvent, type ReactNode } from 'react';
import { keepPreviousData } from '@tanstack/react-query';
import { useFlxQuery } from '@fluxwp/graphql/react';
import { FlxDonateDonors } from '@fluxwp/graphql/operations';
import type { FlxDonateDonorsQuery, FlxDonorListFilter } from '@fluxwp/graphql/types';
import { ListBar, Loading, Screen, listPerPage } from '@fluxwp/ui';
import { useHost } from '../host';
import { cursorAt, Go, Ico, Pager, useDonate } from './shared';
import { Failed } from './Subscriptions';

type Row = FlxDonateDonorsQuery['flxDonorList']['nodes'][number];
type Draft = { s: string; kind: string; orderby: string };

/** Address sort key → FlxDonorOrder (FLXB_Admin::DONOR_ORDERS). */
const ORDERS: Record<string, NonNullable<FlxDonorListFilter['orderBy']>> = {
  last_donation_at: 'LAST_GIFT',
  total_amount: 'TOTAL',
  donation_count: 'GIFTS',
  first_donation_at: 'FIRST_GIFT',
  name: 'NAME',
};

/** The address arguments (s, kind, orderby) → the filter; only set keys, as PHP preloads it. */
export function donorFilter(d: Draft): FlxDonorListFilter {
  const f: FlxDonorListFilter = {};
  const s = d.s.trim();
  if (s) f.search = s;
  if (d.kind === 'recurring' || d.kind === 'consent') f.kind = d.kind.toUpperCase() as FlxDonorListFilter['kind'];
  if (d.orderby && d.orderby !== 'last_donation_at' && ORDERS[d.orderby]) f.orderBy = ORDERS[d.orderby];
  return f;
}

export function Donors({ query, notice }: { query: string; notice: ReactNode }) {
  const host = useHost();
  const { t, tn } = useDonate();
  const start = new URLSearchParams(query);
  const [applied, setApplied] = useState<Draft>({ s: start.get('s') ?? '', kind: start.get('kind') ?? '', orderby: start.get('orderby') ?? '' });
  const [draft, setDraft] = useState<Draft>(applied);
  const [per, setPer] = useState(() => listPerPage('flxb-donors', 20));
  const [offset, setOffset] = useState(() => {
    const p = Number(start.get('paged') ?? '1');
    return Number.isInteger(p) && p > 1 ? (p - 1) * per : 0;
  });
  const filter = useMemo(() => donorFilter(applied), [applied]);
  const q = useFlxQuery(FlxDonateDonors, { first: per, after: cursorAt(offset), filter: Object.keys(filter).length ? filter : undefined }, { placeholderData: keepPreviousData });
  const list = q.data?.flxDonorList;
  const own = q.data?.flxDonorListInfo.ownOnly ?? false;

  useEffect(() => {
    if (host.mode !== 'wp' || typeof history === 'undefined') return;
    const u = new URL(location.href);
    if (u.searchParams.get('page') !== 'flxb-donors' || u.searchParams.get('donor')) return;
    const next = new URLSearchParams({ page: 'flxb-donors' });
    if (applied.s.trim()) next.set('s', applied.s.trim());
    if (applied.kind) next.set('kind', applied.kind);
    if (applied.orderby && applied.orderby !== 'last_donation_at') next.set('orderby', applied.orderby);
    if (offset) next.set('paged', String(Math.floor(offset / per) + 1));
    const s = '?' + next.toString();
    if (u.search !== s) history.replaceState(history.state, '', u.pathname + s);
  }, [applied, offset, per, host.mode]);

  const go = (d: Draft) => {
    setOffset(0);
    setDraft(d);
    setApplied({ ...d, s: d.s.trim() });
  };
  const kinds = [
    { value: '', label: t('All') },
    { value: 'recurring', label: t('Recurring') },
    { value: 'consent', label: t('Contact allowed') },
  ];
  const sorts = [
    { value: 'last_donation_at', label: t('Latest gift') },
    { value: 'total_amount', label: t('Total given') },
    { value: 'donation_count', label: t('Number of gifts') },
    { value: 'first_donation_at', label: t('First gift') },
    { value: 'name', label: t('Name') },
  ];
  const rows = list?.nodes ?? [];
  const total = list?.totalCount ?? 0;

  return (
    <Screen
      className="flxdn"
      width="wide"
      icon={host.appIcon}
      title={own ? t('My donors') : t('Donors')}
      subtitle={own ? t('People who gave through you. Totals count only the gifts you referred.') : t('One card per person, matched on phone number or e-mail address.')}
      notice={notice}
    >
      <ListBar
        id="flxb-donors"
        className="flxdn-filters flxdn-filters--lib"
        onSubmit={() => go(draft)}
        search={<input type="search" value={draft.s} onChange={(e) => setDraft({ ...draft, s: e.target.value })} placeholder={t('Name, e-mail or phone…')} aria-label={t('Search')} />}
        primary={
          <>
            <label>
              <span>{t('Show')}</span>
              <select value={draft.kind} onChange={(e) => go({ ...draft, kind: e.target.value })}>
                {kinds.map((o) => (
                  <option key={o.value} value={o.value}>
                    {o.label}
                  </option>
                ))}
              </select>
            </label>
            <label>
              <span>{t('Sort by')}</span>
              <select value={draft.orderby || 'last_donation_at'} onChange={(e) => go({ ...draft, orderby: e.target.value })}>
                {sorts.map((o) => (
                  <option key={o.value} value={o.value}>
                    {o.label}
                  </option>
                ))}
              </select>
            </label>
          </>
        }
      />
      <div className={'flxdn-panel' + (q.isFetching && list ? ' is-busy' : '')}>
        {!q.data ? (
          q.isError ? (
            <Failed retry={() => void q.refetch()} />
          ) : (
            <Loading />
          )
        ) : !rows.length ? (
          <div className="flxdn-emptystate">
            <Ico name="users" size={32} />
            <h2>{t('No donors found')}</h2>
          </div>
        ) : (
          <>
            <Table rows={rows} />
            <div className="flxdn-tablefoot">
              <span>{tn('%s donor', '%s donors', total, total.toLocaleString())}</span>
              <Pager id="flxb-donors" offset={offset} per={per} total={total} onGo={setOffset} onPer={(v) => (setPer(v), setOffset(0))} />
            </div>
          </>
        )}
      </div>
    </Screen>
  );
}

function Table({ rows }: { rows: Row[] }) {
  const { t, open } = useDonate();
  const rowClick = (e: MouseEvent<HTMLTableRowElement>, url: string) => {
    if ((e.target as HTMLElement).closest('a, button, input, label, select')) return;
    if (e.metaKey || e.ctrlKey) window.open(url, '_blank', 'noopener');
    else open(url);
  };
  return (
    <div className="flxdn-tablewrap">
      <table className="flxdn-table flxdn-table--donors">
        <thead>
          <tr>
            <th scope="col">{t('Donor')}</th>
            <th scope="col">{t('Phone')}</th>
            <th scope="col" className="num">
              {t('Total given')}
            </th>
            <th scope="col" className="num">
              {t('Gifts')}
            </th>
            <th scope="col">{t('Latest gift')}</th>
            <th scope="col">{t('Recurring')}</th>
            <th scope="col">
              <span className="screen-reader-text">{t('Actions')}</span>
            </th>
          </tr>
        </thead>
        <tbody>
          {rows.map((d) => (
            <tr key={d.databaseId} className="flxdn-row-link" onClick={(e) => rowClick(e, d.url)}>
              <td className="flxdn-col-main">
                <Go className="flxdn-row-main" href={d.url}>
                  {d.name}
                </Go>
                {d.email ? <span className="flxdn-sub2">{d.email}</span> : null}
              </td>
              <td data-label={t('Phone')}>
                <span className="flxdn-nowrap">{d.phone || '—'}</span>
              </td>
              <td className="num" data-label={t('Total given')}>
                <span>{d.totalLabel}</span>
              </td>
              <td className="num" data-label={t('Gifts')}>
                <span>{d.gifts.toLocaleString()}</span>
              </td>
              <td className="flxdn-col-date" data-label={t('Latest gift')}>
                <span>{d.lastGift?.day ?? '—'}</span>
              </td>
              <td data-label={t('Recurring')}>
                <span>
                  {d.recurring ? (
                    <span className="flxdn-pill flxdn-pill--recurring">
                      <Ico name="repeat" size={12} />
                      {t('Active')}
                    </span>
                  ) : (
                    <span className="flxdn-muted">—</span>
                  )}
                </span>
              </td>
              <td className="flxdn-col-act">
                <Go className="flxdn-btn flxdn-btn--sm flxdn-row-go" href={d.url}>
                  {t('Open card')}
                </Go>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
