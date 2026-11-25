// Tracking (tracking module, ?page=flxb-track): the list of tracked items with its status tabs, filters and
// bulk steps, and one item's page (?item=N): flow strip, details, donation and payment, status buttons,
// files and history. PHP preloads the page of the address (FLXB_Admin::preload_track()). A file upload stays
// a form post (the file goes with it); the extra detail tabs are printed by PHP as an island.
import { useEffect, useRef, useState, type ReactNode } from 'react';
import { keepPreviousData, useQueryClient } from '@tanstack/react-query';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import {
  FlxDonateTrack,
  FlxDonateTrackItem,
  FlxDonationTrackApply,
  FlxDonationTrackEdit,
  FlxDonationTrackFileRemove,
  FlxDonationTrackFileSend,
  FlxDonationTrackNote,
} from '@fluxwp/graphql/operations';
import type { FlxDonateTrackItemQuery, FlxDonateTrackQuery, FlxDonationTrackFilter } from '@fluxwp/graphql/types';
import { Check, DatePicker, ListBar, Loading, Modal, Screen } from '@fluxwp/ui';
import { useHost } from '../host';
import { IslandNode } from './Detail';
import { Go, Ico, Pager, useDonate } from './shared';
import { Failed } from './Subscriptions';
import { useStartNotice } from './Partners';

const PAGE = 'admin.php?page=flxb-track';
const STATUSES = ['pending', 'approved', 'sent', 'done', 'delivered', 'canceled'];
type L = FlxDonateTrackQuery['flxDonationTrack'];
type It = NonNullable<FlxDonateTrackItemQuery['flxDonationTrackItem']>;

/** The classic list arguments (status, place, type, paid, due, s) → the GraphQL filter; only set keys (as PHP preloads it). */
export function trackFilter(q: URLSearchParams): FlxDonationTrackFilter {
  const f: FlxDonationTrackFilter = {};
  const st = q.get('status') ?? '';
  if (STATUSES.includes(st)) f.status = st;
  const place = (q.get('place') ?? '').trim();
  if (place) f.place = place;
  const type = Number(q.get('type'));
  if (type > 0) f.typeId = type;
  const paid = q.get('paid') ?? '';
  if (paid === '0' || paid === '1') f.paid = paid;
  const due = q.get('due') ?? '';
  if (due && due !== '0') f.due = true;
  const s = (q.get('s') ?? '').trim();
  if (s) f.search = s;
  return f;
}

function trackQuery(f: FlxDonationTrackFilter, page: number): string {
  const q = new URLSearchParams({ page: 'flxb-track' });
  if (f.status) q.set('status', f.status);
  if (f.place) q.set('place', f.place);
  if (f.typeId) q.set('type', String(f.typeId));
  if (f.paid) q.set('paid', f.paid);
  if (f.due) q.set('due', '1');
  if (f.search) q.set('s', f.search);
  if (page > 1) q.set('paged', String(page));
  return '?' + q.toString();
}

const Pill = ({ tone, children }: { tone: string; children: ReactNode }) => <span className={'flxdn-pill flxdn-pill--' + tone}>{children}</span>;

/** Mutation result → notice; true when it went through. */
function useDone() {
  const { t, told } = useDonate();
  return (res: { message?: string | null; warning?: boolean | null; errors?: { message: string }[] | null } | null | undefined): boolean => {
    const err = res?.errors?.[0]?.message;
    if (err || !res?.message) {
      told({ kind: 'error', text: err || t('The action could not be completed.') });
      return false;
    }
    told({ kind: res.warning ? 'error' : 'ok', text: res.message });
    return true;
  };
}

export function Track({ query, notice }: { query: string; notice: ReactNode }) {
  const host = useHost();
  const { t, told } = useDonate();
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const done = useDone();
  useStartNotice(query);
  const [filter, setFilter] = useState<FlxDonationTrackFilter>(() => trackFilter(new URLSearchParams(query)));
  const [page, setPage] = useState(() => Math.max(1, Number(new URLSearchParams(query).get('paged')) || 1));
  const [search, setSearch] = useState(filter.search ?? '');
  const [sel, setSel] = useState<number[]>([]);
  const [bulk, setBulk] = useState({ step: 'approve', place: '', note: '' });
  const vars = { filter: Object.keys(filter).length ? filter : undefined, page };
  const q = useFlxQuery(FlxDonateTrack, vars, { placeholderData: keepPreviousData });
  const d = q.data?.flxDonationTrack;

  useEffect(() => {
    if (host.mode !== 'wp' || typeof history === 'undefined') return;
    const u = new URL(location.href);
    if (u.searchParams.get('page') !== 'flxb-track') return;
    const s = trackQuery(filter, page);
    if (u.search !== s) history.replaceState(history.state, '', u.pathname + s);
  }, [filter, page, host.mode]);

  const apply = useFlxMutation(FlxDonationTrackApply, {
    onSuccess: (r) => {
      if (done(r.flxDonationTrackApply)) {
        setSel([]);
        setBulk((b) => ({ ...b, note: '' }));
      }
      void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxDonateTrack) });
    },
    onError: (e) => told({ kind: 'error', text: e.message || t('The action could not be completed.') }),
  });

  const set = (f: FlxDonationTrackFilter) => {
    setFilter(f);
    setPage(1);
    setSel([]);
  };
  const patch = (k: keyof FlxDonationTrackFilter, v: string | number | boolean | undefined) => {
    const f: FlxDonationTrackFilter = { ...filter };
    if (v === '' || v === undefined || v === false || v === 0) delete f[k];
    else (f as Record<string, unknown>)[k] = v;
    set(f);
  };

  const body = !d ? (
    q.isError ? <Failed retry={() => void q.refetch()} /> : <Loading />
  ) : (
    <List d={d} filter={filter} search={search} setSearch={setSearch} patch={patch} sel={sel} setSel={setSel} bulk={bulk} setBulk={setBulk} apply={() => apply.mutate({ input: { ids: sel, ...bulk } })} busy={apply.isPending} onPage={(p) => setPage(p)} fetching={q.isFetching} />
  );
  return (
    <Screen
      className="flxdn"
      width="wide"
      icon={host.appIcon}
      title={d?.title ?? t('Tracked items')}
      subtitle={t('Every item with its code, payment, partner, target date and files. Open one to see its whole flow.')}
      actions={
        d?.actions.length ? (
          <>
            {d.actions.map((a) => (
              <Go key={a.url} className={'flxdn-btn' + (a.primary ? ' flxdn-btn--primary' : '')} href={a.url}>
                {a.primary ? <Ico name="plus" size={15} /> : null} {a.label}
              </Go>
            ))}
          </>
        ) : null
      }
      notice={notice}
    >
      {body}
    </Screen>
  );
}

function List(p: {
  d: L;
  filter: FlxDonationTrackFilter;
  search: string;
  setSearch: (s: string) => void;
  patch: (k: keyof FlxDonationTrackFilter, v: string | number | boolean | undefined) => void;
  sel: number[];
  setSel: (s: number[]) => void;
  bulk: { step: string; place: string; note: string };
  setBulk: (b: { step: string; place: string; note: string }) => void;
  apply: () => void;
  busy: boolean;
  onPage: (p: number) => void;
  fetching: boolean;
}) {
  const { t } = useDonate();
  const { d, filter, sel, setSel, bulk, setBulk } = p;
  const can = d.canEdit;
  const ids = d.items.map((x) => x.databaseId);
  const all = ids.length > 0 && ids.every((x) => sel.includes(x));
  return (
    <div className={p.fetching ? 'is-busy' : undefined}>
      <div className="flxdn-seg flxdn-kseg">
        {d.tabs.map((x) => (
          <button key={x.value} type="button" className={(filter.status ?? '') === x.value ? 'is-on' : undefined} onClick={() => p.patch('status', x.value)}>
            {x.label} <b>{x.count}</b>
          </button>
        ))}
      </div>
      {/* Classic list library: the first two selects stay visible, the rest go under "Filters". */}
      <ListBar
        id="flxb-track"
        className="flxdn-filters flxdn-filters--lib"
        onSubmit={() => p.patch('search', p.search.trim())}
        moreActive={(d.types.length ? [filter.paid ?? '', filter.due ? '1' : ''] : [filter.due ? '1' : '']).filter(Boolean).length}
        search={<input type="search" value={p.search} onChange={(e) => p.setSearch(e.target.value)} placeholder={t('Code, name, phone, donation no…')} aria-label={t('Search')} />}
        primary={
          d.types.length ? (
            <>
            <label>
              <span className="screen-reader-text">{t('Donation type')}</span>
              <select value={String(filter.typeId ?? '')} onChange={(e) => p.patch('typeId', Number(e.target.value) || undefined)}>
                <option value="">{t('All types')}</option>
                {d.types.map((x) => (
                  <option key={x.value} value={x.value}>
                    {x.label}
                  </option>
                ))}
              </select>
            </label>
            <label>
              <span className="screen-reader-text">{d.partnerLabel}</span>
              <select value={filter.place ?? ''} onChange={(e) => p.patch('place', e.target.value)}>
                <option value="">— {d.partnerLabel} —</option>
                {d.places.map((x) => (
                  <option key={x.value} value={x.value}>
                    {x.label}
                  </option>
                ))}
              </select>
            </label>
            </>
          ) : (
            <>
            <label>
              <span className="screen-reader-text">{d.partnerLabel}</span>
              <select value={filter.place ?? ''} onChange={(e) => p.patch('place', e.target.value)}>
                <option value="">— {d.partnerLabel} —</option>
                {d.places.map((x) => (
                  <option key={x.value} value={x.value}>
                    {x.label}
                  </option>
                ))}
              </select>
            </label>
            <label>
              <span className="screen-reader-text">{t('Payment')}</span>
              <select value={filter.paid ?? ''} onChange={(e) => p.patch('paid', e.target.value)}>
                <option value="">{t('Any payment')}</option>
                <option value="1">{t('Paid')}</option>
                <option value="0">{t('Not paid')}</option>
              </select>
            </label>
            </>
          )
        }
        more={
          d.types.length ? (
            <>
            <label>
              <span className="screen-reader-text">{t('Payment')}</span>
              <select value={filter.paid ?? ''} onChange={(e) => p.patch('paid', e.target.value)}>
                <option value="">{t('Any payment')}</option>
                <option value="1">{t('Paid')}</option>
                <option value="0">{t('Not paid')}</option>
              </select>
            </label>
            <Check checked={!!filter.due} onChange={(x) => p.patch('due', x)}>
              {t('Due now')}
            </Check>
            </>
          ) : (
            <Check checked={!!filter.due} onChange={(x) => p.patch('due', x)}>
              {t('Due now')}
            </Check>
          )
        }
      >
        <div className="flxdn-filters__end">
          <button className="flxdn-btn flxdn-btn--primary" type="submit">
            <Ico name="search" /> {t('Search')}
          </button>
        </div>
      </ListBar>
      <div className="flxdn-panel">
        {!d.items.length ? (
          <div className="flxdn-emptystate">
            <Ico name="heart" size={32} />
            <h2>{t('Nothing to track yet')}</h2>
            <p>{t('An item is created for every unit of a donation type that is tracked one by one (Donation types › Tracking).')}</p>
          </div>
        ) : (
          <>
            {can ? (
              <div className="flxdn-kbulk">
                <label>
                  <span>{t('Selected')}</span>
                  <select value={bulk.step} onChange={(e) => setBulk({ ...bulk, step: e.target.value })}>
                    {d.steps.map((x) => (
                      <option key={x.value} value={x.value}>
                        {x.label}
                      </option>
                    ))}
                  </select>
                </label>
                {bulk.step === 'assign' ? (
                  <label>
                    <span>{d.partnerLabel}</span>
                    <select value={bulk.place} onChange={(e) => setBulk({ ...bulk, place: e.target.value })}>
                      <option value="">—</option>
                      {d.places.map((x) => (
                        <option key={x.value} value={x.value}>
                          {x.label}
                        </option>
                      ))}
                    </select>
                  </label>
                ) : null}
                <label className="flxdn-kbulk__note">
                  <span>{t('Note')}</span>
                  <input type="text" maxLength={300} value={bulk.note} onChange={(e) => setBulk({ ...bulk, note: e.target.value })} />
                </label>
                <button type="button" className="flxdn-btn flxdn-btn--primary" disabled={p.busy || !sel.length} onClick={p.apply}>
                  {t('Apply')}
                </button>
              </div>
            ) : null}
            <div className="flxdn-tablewrap">
              <table className="flxdn-table">
                <thead>
                  <tr>
                    {can ? (
                      <th scope="col" className="flxdn-col-cb">
                        <Check checked={all} indeterminate={!all && sel.length > 0} onChange={(x) => setSel(x ? ids : [])} aria-label={t('Select all')} />
                      </th>
                    ) : null}
                    <th scope="col">{t('Code')}</th>
                    <th scope="col">{t('On behalf of')}</th>
                    <th scope="col">{t('Donation')}</th>
                    <th scope="col">{t('Payment')}</th>
                    <th scope="col">{t('Target date')}</th>
                    <th scope="col">{d.partnerLabel}</th>
                    <th scope="col">{t('Status')}</th>
                    <th scope="col">{d.filesLabel}</th>
                    {d.columns.map((c) => (
                      <th key={c} scope="col">
                        {c}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {d.items.map((it) => (
                    <tr key={it.databaseId}>
                      {can ? (
                        <td className="flxdn-col-cb">
                          <Check checked={sel.includes(it.databaseId)} onChange={(x) => setSel(x ? [...sel, it.databaseId] : sel.filter((s) => s !== it.databaseId))} aria-label={it.code} />
                        </td>
                      ) : null}
                      <td className="flxdn-col-main">
                        <Go className="flxdn-mono" href={PAGE + '&item=' + it.databaseId}>
                          {it.code}
                        </Go>
                        <span className="flxdn-sub2">{it.option}</span>
                      </td>
                      <td data-label={t('On behalf of')}>
                        <span>
                          {it.beneficiary || '—'}
                          {it.phone ? <span className="flxdn-sub2">{it.phone}</span> : null}
                        </span>
                      </td>
                      <td data-label={t('Donation')}>
                        <span>
                          <Go href={it.donationUrl}>#{it.donationId}</Go> {it.donor}
                        </span>
                      </td>
                      <td data-label={t('Payment')}>
                        <span>{it.pay ? <Pill tone={it.pay.tone}>{it.pay.label}</Pill> : '—'}</span>
                      </td>
                      <td data-label={t('Target date')}>
                        <span>
                          <Target t={it.target} />
                        </span>
                      </td>
                      <td data-label={d.partnerLabel}>
                        <span>{it.place || '—'}</span>
                      </td>
                      <td data-label={t('Status')}>
                        <span>
                          <Pill tone={it.tone}>{it.status}</Pill>
                        </span>
                      </td>
                      <td data-label={d.filesLabel}>
                        <span>{it.files || '—'}</span>
                      </td>
                      {d.columns.map((c, i) => (
                        <td key={c} data-label={c}>
                          <span dangerouslySetInnerHTML={{ __html: it.cells[i] ?? '' }} />
                        </td>
                      ))}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </>
        )}
        <div className="flxdn-tablefoot">
          <span>{d.totalLabel}</span>
          <Pager offset={(d.page - 1) * d.perPage} per={d.perPage} total={d.total} hasNext={d.page * d.perPage < d.total} onGo={(o) => p.onPage(o / d.perPage + 1)} />
        </div>
      </div>
    </div>
  );
}

function Target({ t: x }: { t: { date: string; state: string; label: string } }) {
  if (!x.date) return <>—</>;
  return (
    <>
      {x.date} {x.label ? <Pill tone={x.state === 'late' ? 'failed' : 'pending'}>{x.label}</Pill> : null}
    </>
  );
}

export function TrackItem({ id, query, notice }: { id: number; query: string; notice: ReactNode }) {
  const host = useHost();
  const { t } = useDonate();
  useStartNotice(query);
  const q = useFlxQuery(FlxDonateTrackItem, { id });
  const it = q.data?.flxDonationTrackItem;
  // The islands are printed with the page only: keep the first list (a refetch has none over GraphQL elsewhere).
  const islands = useRef<string[] | null>(null);
  if (it && islands.current === null) islands.current = it.islands;
  const isl = islands.current ?? [];
  const back = (title: string) => (
    <Go className="flxdn-back" href={PAGE}>
      <span aria-hidden="true">‹</span> {title}
    </Go>
  );
  if (!q.data) {
    return (
      <Screen className="flxdn" width="wide" icon={host.appIcon} title={t('Tracked items')} notice={notice}>
        {q.isError ? <Failed retry={() => void q.refetch()} /> : <Loading />}
      </Screen>
    );
  }
  if (!it) {
    return (
      <Screen className="flxdn" width="wide" icon={host.appIcon} title={t('Item not found.')} notice={notice} actions={back(t('Tracked items'))}>
        {null}
      </Screen>
    );
  }
  return (
    <Screen className="flxdn" width="wide" icon={host.appIcon} title={it.title} subtitle={it.subtitle} actions={back(it.listTitle)} notice={notice}>
      <ItemPage it={it} islands={isl} busy={q.isFetching} />
    </Screen>
  );
}

function Dl({ rows }: { rows: { label: string; html: string }[] }) {
  return (
    <dl className="flxdn-dl">
      {rows.map((r, i) => (
        <div key={r.label + i} style={{ display: 'contents' }}>
          <dt>{r.label}</dt>
          <dd dangerouslySetInnerHTML={{ __html: r.html }} />
        </div>
      ))}
    </dl>
  );
}

function ItemPage({ it, islands, busy }: { it: It; islands: string[]; busy: boolean }) {
  const host = useHost();
  const { t, told } = useDonate();
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const done = useDone();
  const can = it.canEdit;
  const [v, setV] = useState({ name: it.beneficiary, phone: it.phone, targetDate: it.targetDate });
  const [assign, setAssign] = useState({ place: '', note: '' });
  const [note, setNote] = useState('');
  const [ask, setAsk] = useState<{ text: string; run: () => void; danger: boolean } | null>(null);
  useEffect(() => setV({ name: it.beneficiary, phone: it.phone, targetDate: it.targetDate }), [it.beneficiary, it.phone, it.targetDate]);

  const reload = () => {
    void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxDonateTrackItem) });
    void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxDonateTrack) });
  };
  const fail = (e: Error) => told({ kind: 'error', text: e.message || t('The action could not be completed.') });
  const after = (res: Parameters<ReturnType<typeof useDone>>[0]) => {
    setAsk(null);
    const ok = done(res);
    reload();
    return ok;
  };
  const apply = useFlxMutation(FlxDonationTrackApply, {
    onSuccess: (r) => {
      if (after(r.flxDonationTrackApply)) setAssign({ place: '', note: '' });
    },
    onError: fail,
  });
  const edit = useFlxMutation(FlxDonationTrackEdit, { onSuccess: (r) => void after(r.flxDonationTrackEdit), onError: fail });
  const addNote = useFlxMutation(FlxDonationTrackNote, {
    onSuccess: (r) => {
      if (after(r.flxDonationTrackNote)) setNote('');
    },
    onError: fail,
  });
  const send = useFlxMutation(FlxDonationTrackFileSend, { onSuccess: (r) => void after(r.flxDonationTrackFileSend), onError: fail });
  const remove = useFlxMutation(FlxDonationTrackFileRemove, { onSuccess: (r) => void after(r.flxDonationTrackFileRemove), onError: fail });
  const pending = apply.isPending || edit.isPending || addNote.isPending || send.isPending || remove.isPending;
  const step = (s: string, extra: { place?: string; note?: string } = {}) => apply.mutate({ input: { ids: [it.databaseId], step: s, place: extra.place ?? '', note: extra.note ?? '' } });

  return (
    <div className={busy ? 'is-busy' : undefined}>
      <ol className={'flxdn-kflow' + (it.canceled ? ' is-canceled' : '')}>
        {it.flow.map((f) => (
          <li key={f.label} className={f.state ? 'is-' + f.state : undefined}>
            <b>{f.label}</b>
            <span>{f.date}</span>
          </li>
        ))}
      </ol>
      {it.canceled ? (
        <div className="flxdn-notice flxdn-notice--warn">
          <p>{t('This item is canceled.')}</p>
        </div>
      ) : null}
      {it.late ? (
        <div className="flxdn-notice flxdn-notice--warn">
          <p>{t('The target date has passed and it is not completed yet.')}</p>
        </div>
      ) : null}

      <div className="flxdn-grid flxdn-grid--1-1">
        <section className="flxdn-card">
          <div className="flxdn-card__head">
            <h2 className="flxdn-card__title">{it.itemLabel}</h2>
            <Pill tone={it.tone}>{it.status}</Pill>
          </div>
          <Dl rows={it.details} />
          {can ? (
            <form
              className="flxdn-form flxdn-kform"
              onSubmit={(e) => {
                e.preventDefault();
                edit.mutate({ input: { id: it.databaseId, ...v } });
              }}
            >
              <div className="flxdn-fields-2">
                <div className="flxdn-field">
                  <label htmlFor="flxdn-kn">{t('On behalf of')}</label>
                  <input id="flxdn-kn" type="text" maxLength={190} value={v.name} onChange={(e) => setV({ ...v, name: e.target.value })} />
                </div>
                <div className="flxdn-field">
                  <label htmlFor="flxdn-kp">{t('Owner phone')}</label>
                  <input id="flxdn-kp" type="tel" maxLength={32} value={v.phone} placeholder={t('Empty = donor phone')} onChange={(e) => setV({ ...v, phone: e.target.value })} />
                </div>
              </div>
              <div className="flxdn-field">
                <label htmlFor="flxdn-kt">{t('Target date')}</label>
                <DatePicker id="flxdn-kt" value={v.targetDate} onChange={(x: string) => setV({ ...v, targetDate: x })} />
              </div>
              <div className="flxdn-formactions">
                <button type="submit" className="flxdn-btn flxdn-btn--sm" disabled={pending}>
                  {t('Save')}
                </button>
              </div>
            </form>
          ) : (
            <dl className="flxdn-dl">
              <dt>{t('On behalf of')}</dt>
              <dd>{it.beneficiary}</dd>
            </dl>
          )}
        </section>

        <section className="flxdn-card">
          <div className="flxdn-card__head">
            <h2 className="flxdn-card__title">{t('Donation and payment')}</h2>
            {it.donationStatus ? <Pill tone={it.donationTone}>{it.donationStatus}</Pill> : null}
          </div>
          <Dl rows={it.payment} />
          {it.unpaid ? (
            <p className="flxdn-notice flxdn-notice--warn">{t('Payment not received: it cannot be sent to a partner until the payment is approved.')}</p>
          ) : null}
        </section>
      </div>

      <div className="flxdn-grid flxdn-grid--1-1">
        {can ? (
          <section className="flxdn-card">
            <div className="flxdn-card__head">
              <h2 className="flxdn-card__title">{t('Status')}</h2>
            </div>
            {it.canAssign ? (
              <form
                className="flxdn-form flxdn-kform"
                onSubmit={(e) => {
                  e.preventDefault();
                  step('assign', assign);
                }}
              >
                <div className="flxdn-field">
                  <label htmlFor="flxdn-kpl">{it.assignLabel}</label>
                  <select id="flxdn-kpl" value={assign.place} onChange={(e) => setAssign({ ...assign, place: e.target.value })}>
                    <option value="">— {it.partnerLabel} —</option>
                    {it.places.map((x) => (
                      <option key={x.value} value={x.value}>
                        {x.label}
                      </option>
                    ))}
                  </select>
                </div>
                <div className="flxdn-field">
                  <label htmlFor="flxdn-kan">{t('Note')}</label>
                  <input id="flxdn-kan" type="text" maxLength={300} value={assign.note} onChange={(e) => setAssign({ ...assign, note: e.target.value })} />
                </div>
                <div className="flxdn-formactions">
                  <button type="submit" className="flxdn-btn flxdn-btn--primary" disabled={pending}>
                    {it.assignLabel}
                  </button>
                </div>
              </form>
            ) : null}
            <div className="flxdn-kbtns">
              {it.actions.map((a) => (
                <button
                  key={a.step}
                  type="button"
                  className={'flxdn-btn' + (a.tone === 'primary' ? ' flxdn-btn--primary' : a.tone === 'danger' ? ' flxdn-btn--danger' : '')}
                  disabled={pending}
                  onClick={() => (a.confirm ? setAsk({ text: a.confirm, run: () => step(a.step), danger: a.tone === 'danger' }) : step(a.step))}
                >
                  {a.label}
                </button>
              ))}
            </div>
            <form
              className="flxdn-form"
              onSubmit={(e) => {
                e.preventDefault();
                addNote.mutate({ input: { id: it.databaseId, note } });
              }}
            >
              <div className="flxdn-field">
                <label htmlFor="flxdn-knote">{t('Add a note to the history')}</label>
                <input id="flxdn-knote" type="text" maxLength={300} required value={note} onChange={(e) => setNote(e.target.value)} />
              </div>
              <div className="flxdn-formactions">
                <button type="submit" className="flxdn-btn flxdn-btn--sm" disabled={pending}>
                  {t('Add note')}
                </button>
              </div>
            </form>
          </section>
        ) : null}

        <section className="flxdn-card">
          <div className="flxdn-card__head">
            <div>
              <h2 className="flxdn-card__title">{it.filesLabel}</h2>
              <p className="flxdn-card__hint">{it.filesHint}</p>
            </div>
          </div>
          {!it.files.length ? <p className="flxdn-muted">{t('No file yet.')}</p> : null}
          {it.files.map((f) => (
            <div key={f.att} className="flxdn-kvideo">
              <Preview f={f} />
              <div className="flxdn-kvideo__meta">
                <span>{f.meta}</span>
                {f.sent.map((s) => (
                  <Pill key={s} tone="ok">
                    {s}
                  </Pill>
                ))}
                {can ? (
                  <div className="flxdn-kbtns">
                    {it.canSend ? (
                      <button
                        type="button"
                        className="flxdn-btn flxdn-btn--primary flxdn-btn--sm"
                        disabled={pending}
                        onClick={() => setAsk({ text: t('Send this file to %s over WhatsApp?', it.ownerPhone), run: () => send.mutate({ input: { id: it.databaseId, att: f.att } }), danger: false })}
                      >
                        {t('Send to owner')}
                      </button>
                    ) : null}
                    <button
                      type="button"
                      className="flxdn-linkbtn flxdn-linkbtn--danger"
                      disabled={pending}
                      onClick={() => setAsk({ text: t('Remove this file from the item? It stays in the media library.'), run: () => remove.mutate({ input: { id: it.databaseId, att: f.att } }), danger: true })}
                    >
                      {t('Remove')}
                    </button>
                  </div>
                ) : null}
              </div>
            </div>
          ))}
          {can && it.sendHint ? <p className="flxdn-muted">{it.sendHint}</p> : null}
          {can && it.uploadUrl && host.mode === 'wp' ? (
            <form className="flxdn-form" method="post" action={it.uploadUrl} encType="multipart/form-data">
              <input type="hidden" name="action" value="flxb_track_file_upload" />
              <input type="hidden" name="id" value={it.databaseId} />
              <input type="hidden" name="_wpnonce" value={it.uploadNonce} />
              <div className="flxdn-field">
                <label htmlFor="flxdn-kfile">{t('Upload a file')}</label>
                <input id="flxdn-kfile" type="file" name="file" accept="video/*,image/*,audio/*,application/pdf" required />
              </div>
              <div className="flxdn-formactions">
                <button type="submit" className="flxdn-btn">
                  {t('Upload')}
                </button>
              </div>
            </form>
          ) : null}
        </section>
        {!can ? <div /> : null}
      </div>

      {islands.includes('xtabs') ? <IslandNode id="xtabs" /> : null}

      <section className="flxdn-card flxdn-card--gap">
        <div className="flxdn-card__head">
          <h2 className="flxdn-card__title">{t('History')}</h2>
        </div>
        <ol className="flxdn-ktime">
          {it.history.map((h, i) => (
            <li key={i} className={'flxdn-ktime--' + h.event}>
              <time>{h.date}</time>
              <div>
                <b>{h.label}</b>
                {h.more ? ' · ' + h.more : null}
                {h.note ? <p>{h.note}</p> : null}
                <span className="flxdn-sub2">{h.user}</span>
              </div>
            </li>
          ))}
        </ol>
      </section>

      {ask ? (
        <Modal title={t('Are you sure?')} onClose={() => !pending && setAsk(null)}>
          <p>{ask.text}</p>
          <div className="flxdn-actions">
            <button type="button" className="flxdn-btn" disabled={pending} onClick={() => setAsk(null)}>
              {t('Close')}
            </button>
            <button type="button" className={'flxdn-btn flxdn-btn--primary' + (ask.danger ? ' flxdn-btn--danger-solid' : '')} disabled={pending} onClick={ask.run}>
              {t('OK')}
            </button>
          </div>
        </Modal>
      ) : null}
    </div>
  );
}

function Preview({ f }: { f: It['files'][number] }) {
  if (f.kind === 'video') return <video controls preload="metadata" src={f.url} />;
  if (f.kind === 'image')
    return (
      <a href={f.url} target="_blank" rel="noopener noreferrer">
        <img src={f.url} alt="" loading="lazy" />
      </a>
    );
  if (f.kind === 'audio') return <audio controls preload="metadata" src={f.url} />;
  return (
    <a className="flxdn-kfile" href={f.url} target="_blank" rel="noopener noreferrer">
      <Ico name="file" /> {f.name}
    </a>
  );
}
