// Donations list: receipts awaiting approval, the filter bar (the classic URL arguments, kept in the
// address bar), the table (the whole row opens the donation), bulk trash / restore / delete with a
// confirmation, the totals line, the trash link and pages. PHP preloads the first page of the filter in
// the address, so the list shows at once.
import { useEffect, useMemo, useState, type MouseEvent, type ReactNode } from 'react';
import { keepPreviousData, useQueryClient } from '@tanstack/react-query';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { FlxDonateDashboard, FlxDonateDonations, FlxDonationsBulk } from '@fluxwp/graphql/operations';
import type { FlxDonateDonationsQuery, FlxDonationBulkAction } from '@fluxwp/graphql/types';
import { DatePicker, ListBar, ListPages, Loading, Modal, Screen, listPerPage } from '@fluxwp/ui';
import { useHost } from '../host';
import { cursorAt, filterFromQuery, filterToQuery, filterVar, Go, Ico, useDonate } from './shared';

const AWAIT_SHOW = 5;
type Info = FlxDonateDonationsQuery['flxDonationListInfo'];
type Row = FlxDonateDonationsQuery['flxDonationList']['nodes'][number];
/** The classic list arguments as typed in the filter bar. */
type Draft = Record<'s' | 'status' | 'dtype' | 'type' | 'from' | 'to' | 'form' | 'ref' | 'donor_id', string>;
const KEYS: (keyof Draft)[] = ['s', 'status', 'dtype', 'type', 'from', 'to', 'form', 'ref', 'donor_id'];

const draftOf = (q: URLSearchParams): Draft => Object.fromEntries(KEYS.map((k) => [k, q.get(k) ?? ''])) as Draft;
const paramsOf = (d: Draft): URLSearchParams => {
  const q = new URLSearchParams();
  for (const k of KEYS) if (d[k] !== '') q.set(k, d[k]);
  return q;
};

export function Donations({ query, notice }: { query: string; notice: ReactNode }) {
  const host = useHost();
  const { t, tn, told } = useDonate();
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const [applied, setApplied] = useState<Draft>(() => draftOf(new URLSearchParams(query)));
  const [draft, setDraft] = useState<Draft>(applied);
  const [per, setPer] = useState(() => listPerPage('flxb-donations', 20));
  const [offset, setOffset] = useState(() => {
    const p = Number(new URLSearchParams(query).get('paged') ?? '1');
    return Number.isInteger(p) && p > 1 ? (p - 1) * per : 0;
  });
  const [picked, setPicked] = useState<Set<number>>(() => new Set());
  const [action, setAction] = useState<FlxDonationBulkAction | ''>('');
  const [confirm, setConfirm] = useState<{ action: FlxDonationBulkAction; ids: number[] } | null>(null);

  const filter = useMemo(() => filterFromQuery(paramsOf(applied)), [applied]);
  const vars = { first: per, after: cursorAt(offset), filter: filterVar(filter) };
  const q = useFlxQuery(FlxDonateDonations, vars, { placeholderData: keepPreviousData });
  const list = q.data?.flxDonationList;
  const info = q.data?.flxDonationListInfo;
  const inTrash = applied.status === 'trash';

  // The address bar follows the filter (bookmarks, back to the list from a donation).
  useEffect(() => {
    if (host.mode !== 'wp' || typeof history === 'undefined') return;
    const u = new URL(location.href);
    if (u.searchParams.get('page') !== 'flxb-donations') return;
    const next = new URLSearchParams({ page: 'flxb-donations' });
    filterToQuery(filter).forEach((v, k) => next.set(k, v));
    if (offset) next.set('paged', String(Math.floor(offset / per) + 1));
    const s = '?' + next.toString();
    if (u.search !== s) history.replaceState(history.state, '', u.pathname + s);
  }, [filter, offset, per, host.mode]);

  useEffect(() => setPicked(new Set()), [applied, offset]);

  const bulk = useFlxMutation(FlxDonationsBulk, {
    onSuccess: (r) => {
      const res = r.flxDonationsBulk;
      const err = res?.errors?.[0]?.message;
      if (err) told({ kind: 'error', text: err });
      else if (res) told({ kind: 'ok', text: res.message });
      setPicked(new Set());
      setAction('');
      void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxDonateDonations) });
      void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxDonateDashboard) });
    },
    onError: (e) => told({ kind: 'error', text: e.message || t('The action could not be completed.') }),
    onSettled: () => setConfirm(null),
  });

  const actions = info ? (
    <>
      <a className="flxdn-btn" href={info.csvUrl}>
        <Ico name="download" />
        {t('Export CSV')}
      </a>
      {info.canAdd ? (
        <Go className="flxdn-btn flxdn-btn--primary" href={info.addUrl}>
          <Ico name="plus" />
          {t('Add donation')}
        </Go>
      ) : null}
    </>
  ) : null;
  const own = info?.ownOnly ?? false;

  /** The classic list library applies a filter as soon as it changes; the search on Enter. */
  const apply = (p: Partial<Draft>) => {
    const d = { ...draft, ...p };
    setDraft(d);
    setApplied(d);
    setOffset(0);
  };
  const pick = (k: keyof Draft) => (v: string) => apply({ [k]: v, s: draft.s.trim() });
  /** Only one status, every other filter cleared (trash, receipts awaiting approval). */
  const only = (status: string) => {
    const d = { ...draftOf(new URLSearchParams()), status, donor_id: applied.donor_id };
    setDraft(d);
    setApplied(d);
    setOffset(0);
  };
  const set = (k: keyof Draft) => (v: string) => setDraft((d) => ({ ...d, [k]: v }));

  const bulkChoices: { key: FlxDonationBulkAction; label: string }[] =
    info && info.canManage && !own
      ? [
          ...(inTrash ? [{ key: 'RESTORE' as FlxDonationBulkAction, label: t('Restore') }] : [{ key: 'TRASH' as FlxDonationBulkAction, label: t('Move to trash') }]),
          ...(info.canDeletePermanently ? [{ key: 'DELETE' as FlxDonationBulkAction, label: t('Delete permanently') }] : []),
        ]
      : [];
  const ids = [...picked];
  const run = () => {
    const a = action || bulkChoices[0]?.key;
    if (!a || !ids.length) return;
    if (a === 'RESTORE') bulk.mutate({ ids, action: a });
    else setConfirm({ action: a, ids });
  };

  const rows = list?.nodes ?? [];
  const total = list?.totalCount ?? 0;
  const page = Math.floor(offset / per) + 1;
  const showRef = !own;

  return (
    <Screen
      className="flxdn"
      width="wide"
      icon={host.appIcon}
      title={own ? t('My referrals') : t('Donations')}
      subtitle={own ? t('Donations made through your referral link or recorded with you as the referral.') : t('Every gift received through your forms, one-time and recurring.')}
      actions={actions}
      notice={notice}
    >
      {info && offset === 0 && !inTrash && applied.status !== 'receipt' ? <Awaiting items={info.awaiting} onShow={() => only('receipt')} /> : null}
      {info ? (
        <ListBar
          id="flxb-donations"
          className="flxdn-filters flxdn-filters--lib"
          onSubmit={() => apply({ s: draft.s.trim() })}
          onClear={KEYS.some((k) => k !== 'donor_id' && applied[k] !== '') ? () => only('') : undefined}
          moreActive={countMore(applied)}
          search={<input type="search" value={draft.s} onChange={(e) => set('s')(e.target.value)} placeholder={t('Name, e-mail, phone, code…')} aria-label={t('Search')} />}
          primary={
            <>
              <Select label={t('Status')} all={t('All statuses')} value={draft.status} onChange={pick('status')} opts={withCurrent(info.statuses, draft.status, inTrash ? t('Trash') : draft.status)} />
              {info.donationTypes.length ? (
                <Select label={t('Donation type')} all={t('All donation types')} value={draft.dtype} onChange={pick('dtype')} opts={[{ value: '0', label: t('No donation type') }, ...info.donationTypes]} />
              ) : null}
            </>
          }
          more={
            <>
              <Select
                label={t('Type')}
                all={t('All types')}
                value={draft.type}
                onChange={pick('type')}
                opts={[
                  { value: 'one_time', label: t('One-time') },
                  { value: 'recurring', label: t('Recurring') },
                ]}
              />
              <label>
                <span>{t('From')}</span>
                <DatePicker value={draft.from} onChange={pick('from')} aria-label={t('From')} />
              </label>
              <label>
                <span>{t('To')}</span>
                <DatePicker value={draft.to} onChange={pick('to')} aria-label={t('To')} />
              </label>
              {info.forms.length ? <Select label={t('Form')} all={t('All forms')} value={draft.form} onChange={pick('form')} opts={info.forms} /> : null}
              {showRef && (info.referrals.length || draft.ref !== '') ? (
                <Select label={t('Referral')} all={t('All referrals')} value={draft.ref} onChange={pick('ref')} opts={[{ value: '0', label: t('No referral') }, ...info.referrals]} />
              ) : null}
            </>
          }
        />
      ) : null}

      <div className={'flxdn-panel' + (q.isFetching && list ? ' is-busy' : '')}>
        {!q.data ? (
          q.isError ? (
            <div className="flxdn-empty">
              <p>{t('The data could not be loaded.')}</p>
              <button type="button" className="flxdn-btn" onClick={() => void q.refetch()}>
                {t('Try again')}
              </button>
            </div>
          ) : (
            <Loading />
          )
        ) : !rows.length ? (
          <div className="flxdn-emptystate">
            <Ico name="heart" size={32} />
            <h2>{t('No donations found')}</h2>
            <p>{t('Try a wider date range or clear the filters.')}</p>
          </div>
        ) : (
          <>
            {bulkChoices.length && picked.size ? (
              <div className="flxdn-bulkbar">
                <span className="flxdn-bulkbar__n">{t('%s selected', picked.size)}</span>
                <label className="screen-reader-text" htmlFor="flxdn-bulk-do">
                  {t('Bulk action')}
                </label>
                <select id="flxdn-bulk-do" value={action || bulkChoices[0]?.key} onChange={(e) => setAction(e.target.value as FlxDonationBulkAction)}>
                  {bulkChoices.map((c) => (
                    <option key={c.key} value={c.key}>
                      {c.label}
                    </option>
                  ))}
                </select>
                <button type="button" className="flxdn-btn flxdn-btn--primary" disabled={bulk.isPending} onClick={run}>
                  {t('Apply')}
                </button>
                <button type="button" className="flxdn-btn" onClick={() => setPicked(new Set())}>
                  {t('Clear selection')}
                </button>
              </div>
            ) : null}
            <GiftTable rows={rows} showRef={showRef} bulk={bulkChoices.length > 0} picked={picked} setPicked={setPicked} />
            <div className="flxdn-tablefoot">
              <span>
                {tn('%1$s donation · %2$s received', '%1$s donations · %2$s received', total, total.toLocaleString(), info?.sumLabel ?? '')}
                {info && info.trashCount > 0 && !inTrash ? (
                  <>
                    {' · '}
                    <button
                      type="button"
                      className="flxdn-filters__trash flxdn-linkbtn"
                      onClick={() => only('trash')}
                    >
                      {t('Trash (%s)', info.trashCount.toLocaleString())}
                    </button>
                  </>
                ) : null}
              </span>
              <ListPages
              id="flxb-donations"
              total={total}
              per={per}
              page={page}
              onPage={(n) => setOffset((n - 1) * per)}
              onPer={(v) => {
                setPer(v);
                setOffset(0);
              }}
            />
            </div>
          </>
        )}
      </div>

      {confirm ? (
        <Modal title={confirm.action === 'DELETE' ? t('Delete permanently') : t('Move to trash')} onClose={() => !bulk.isPending && setConfirm(null)}>
          <p>
            {confirm.action === 'DELETE'
              ? t('Delete %s donations permanently? Their WooCommerce orders are deleted too. This cannot be undone.', confirm.ids.length)
              : t('Move %s donations to the trash? Their WooCommerce orders go to the trash too.', confirm.ids.length)}
          </p>
          <div className="flxdn-actions">
            <button type="button" className="flxdn-btn" disabled={bulk.isPending} onClick={() => setConfirm(null)}>
              {t('Cancel')}
            </button>
            <button type="button" className={'flxdn-btn flxdn-btn--primary' + (confirm.action === 'DELETE' ? ' flxdn-btn--danger-solid' : '')} disabled={bulk.isPending} onClick={() => bulk.mutate({ ids: confirm.ids, action: confirm.action })}>
              {confirm.action === 'DELETE' ? t('Delete permanently') : t('Move to trash')}
            </button>
          </div>
        </Modal>
      ) : null}
    </Screen>
  );
}

const countMore = (d: Draft) => (['type', 'from', 'to', 'form', 'ref'] as const).filter((k) => d[k] !== '').length;

/** The choices, plus the current value when it is not among them (trash, an old form). */
function withCurrent(opts: { value: string; label: string }[], value: string, label: string) {
  return value === '' || opts.some((o) => o.value === value) ? opts : [...opts, { value, label }];
}

function Select({ label, all, value, onChange, opts }: { label: string; all: string; value: string; onChange: (v: string) => void; opts: { value: string; label: string }[] }) {
  return (
    <label>
      <span>{label}</span>
      <select value={value} onChange={(e) => onChange(e.target.value)}>
        <option value="">{all}</option>
        {opts.map((o) => (
          <option key={o.value} value={o.value}>
            {o.label}
          </option>
        ))}
      </select>
    </label>
  );
}

function Awaiting({ items, onShow }: { items: Info['awaiting']; onShow: () => void }) {
  const { t, tn } = useDonate();
  if (!items.length) return null;
  const n = items.length;
  const item = (r: Info['awaiting'][number]) => (
    <li key={r.databaseId}>
      <Go className="flxdn-awaiting__item" href={r.url}>
        <span className="flxdn-awaiting__icon" aria-hidden="true">
          <Ico name="file" size={18} />
        </span>
        <span className="flxdn-awaiting__who">
          <strong>{r.donorName || t('Anonymous')}</strong>
          <small>{[r.what, r.date?.relative ?? ''].filter(Boolean).join(' · ')}</small>
        </span>
        <span className="flxdn-awaiting__amt">{r.amountLabel}</span>
        <span className="flxdn-awaiting__go">
          {t('Review')}
          <span aria-hidden="true">›</span>
        </span>
      </Go>
    </li>
  );
  return (
    <section className="flxdn-card flxdn-awaiting" aria-labelledby="flxdn-awaiting-title">
      <div className="flxdn-card__head">
        <div>
          <h2 className="flxdn-card__title" id="flxdn-awaiting-title">
            {t('Receipts awaiting approval')} <span className="flxdn-awaiting__n">{n.toLocaleString()}</span>
          </h2>
          <p className="flxdn-card__hint">{t('The donor sent a receipt. Open it, check the bank account, then approve or reject.')}</p>
        </div>
        {n > AWAIT_SHOW ? (
          <button type="button" className="flxdn-btn flxdn-btn--ghost flxdn-btn--sm" onClick={onShow}>
            {t('Show in the list')}
          </button>
        ) : null}
      </div>
      <ul className="flxdn-awaiting__list">{items.slice(0, AWAIT_SHOW).map(item)}</ul>
      {n > AWAIT_SHOW ? (
        <details className="flxdn-awaiting__more">
          <summary>{tn('%s more', '%s more', n - AWAIT_SHOW, (n - AWAIT_SHOW).toLocaleString())}</summary>
          <ul className="flxdn-awaiting__list">{items.slice(AWAIT_SHOW).map(item)}</ul>
        </details>
      ) : null}
    </section>
  );
}

/** The gift table; `compact` (donor card) keeps date, amount, type, status and donation type, as the classic card. */
export function GiftTable({ rows, showRef, bulk, picked, setPicked, compact = false }: { rows: Row[]; showRef: boolean; bulk: boolean; picked: Set<number>; setPicked: (s: Set<number>) => void; compact?: boolean }) {
  const { t, open } = useDonate();
  const allOn = rows.length > 0 && rows.every((r) => picked.has(r.databaseId));
  const toggle = (id: number, on: boolean) => {
    const s = new Set(picked);
    if (on) s.add(id);
    else s.delete(id);
    setPicked(s);
  };
  const rowClick = (e: MouseEvent<HTMLTableRowElement>, url: string) => {
    const el = e.target as HTMLElement;
    if (el.closest('a, button, input, label, select')) return;
    if (e.metaKey || e.ctrlKey) window.open(url, '_blank', 'noopener');
    else open(url);
  };
  return (
    <div className="flxdn-tablewrap">
      <table className={'flxdn-table flxdn-table--gifts' + (compact ? '' : ' flxdn-table--list') + (bulk ? ' flxdn-table--bulk' : '')}>
        <thead>
          <tr>
            {bulk ? (
              <th scope="col" className="flxdn-c-check">
                <input type="checkbox" checked={allOn} onChange={(e) => setPicked(e.target.checked ? new Set(rows.map((r) => r.databaseId)) : new Set())} aria-label={t('Select all')} />
              </th>
            ) : null}
            <th scope="col">{t('Date')}</th>
            {compact ? null : <th scope="col">{t('Donor')}</th>}
            <th scope="col" className="num">
              {t('Amount')}
            </th>
            <th scope="col">{t('Type')}</th>
            <th scope="col">{t('Status')}</th>
            <th scope="col">{t('Donation type / form')}</th>
            {compact ? null : (
              <>
                {showRef ? <th scope="col">{t('Referral')}</th> : null}
                <th scope="col">{t('Confirmation code')}</th>
                <th scope="col">
                  <span className="screen-reader-text">{t('Actions')}</span>
                </th>
              </>
            )}
          </tr>
        </thead>
        <tbody>
          {rows.map((r) => (
            <tr key={r.databaseId} className={'flxdn-row-link' + (picked.has(r.databaseId) ? ' is-selected' : '')} onClick={(e) => rowClick(e, r.url)}>
              {bulk ? (
                <td className="flxdn-c-check">
                  <input type="checkbox" checked={picked.has(r.databaseId)} onChange={(e) => toggle(r.databaseId, e.target.checked)} aria-label={t('Select the donation of %s', r.donorName)} />
                </td>
              ) : null}
              <td className="flxdn-col-date" data-label={t('Date')}>
                <span>{r.date?.short ?? ''}</span>
              </td>
              {compact ? null : (
                <td className="flxdn-col-main">
                  {!r.donorId ? (
                    <span className="flxdn-muted">{t('Anonymous')}</span>
                  ) : (
                    <Go className="flxdn-row-main" href={r.url}>
                      {r.donorName}
                    </Go>
                  )}
                  {r.contact && r.contact !== r.donorName ? <span className="flxdn-sub2">{r.contact}</span> : null}
                </td>
              )}
              <td className="num flxdn-c-amount" data-label={t('Amount')}>
                <span>{r.amountLabel}</span>
              </td>
              <td className="flxdn-c-type" data-label={t('Type')}>
                <span>
                  {r.kind === 'RECURRING' ? (
                    <span className="flxdn-pill flxdn-pill--recurring">
                      <Ico name="repeat" size={12} />
                      {t('Recurring')}
                    </span>
                  ) : (
                    <span className="flxdn-pill">{t('One-time')}</span>
                  )}
                </span>
              </td>
              <td className="flxdn-c-status" data-label={t('Status')}>
                <span>
                  <span className={'flxdn-pill flxdn-pill--' + r.tone.toLowerCase()}>{r.statusLabel}</span>
                </span>
              </td>
              <td className="flxdn-c-where" data-label={t('Donation type / form')}>
                <span>{r.where || '—'}</span>
              </td>
              {compact ? null : (
                <>
                {showRef ? (
                  <td className="flxdn-c-ref" data-label={t('Referral')}>
                    <span>
                      {r.referral ? (
                        r.referralFromDonor ? (
                          <span className="flxdn-ref-chip flxdn-ref-chip--donor" title={t("The donor's earlier gifts came through %s.", r.referral)}>
                            {r.referral}
                          </span>
                        ) : (
                          <span className="flxdn-ref-chip">{r.referral}</span>
                        )
                      ) : (
                        '—'
                      )}
                    </span>
                  </td>
                ) : null}
                <td className="flxdn-c-code" data-label={t('Confirmation code')}>
                  <span>{r.code ? <code>{r.code}</code> : '—'}</span>
                </td>
                <td className="flxdn-col-act">
                  <Go className="flxdn-btn flxdn-btn--sm flxdn-row-go" href={r.url}>
                    {t('Details')}
                    <span aria-hidden="true">›</span>
                  </Go>
                </td>
                </>
              )}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
