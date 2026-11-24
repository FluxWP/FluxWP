// Partners (tracking module, ?page=flxb-partners): the list and the Settlement tab (per period what was sent to
// each partner or came in through it, its cost or gift price, what was paid and what is open; the month cards
// and one partner's records), and the partner editor (?id=N, ?tab=edit) with its agreements and payments.
// PHP preloads the page of the address (FLXB_Admin::preload_partners()); the CSV stays a download.
import { useEffect, useState, type FormEvent, type ReactNode } from 'react';
import { keepPreviousData, useQueryClient } from '@tanstack/react-query';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import {
  FlxDonatePartner,
  FlxDonatePartners,
  FlxDonatePartnerSettlement,
  FlxDonationPartnerPaymentAdd,
  FlxDonationPartnerPaymentDelete,
  FlxDonationPartnerPriceAdd,
  FlxDonationPartnerPriceDelete,
  FlxDonationPartnerPriceToggle,
  FlxDonationPartnerReprice,
  FlxDonationPartnerSave,
} from '@fluxwp/graphql/operations';
import type { FlxDonatePartnerQuery, FlxDonatePartnersQuery } from '@fluxwp/graphql/types';
import { Check, DatePicker, Loading, Modal, Screen } from '@fluxwp/ui';
import { useHost } from '../host';
import { Go, Ico, useDonate } from './shared';
import { Failed } from './Subscriptions';

type Tab = 'list' | 'settle';
type Settle = { kind: 'service' | 'source'; period: string; partner: number };
const NONE = '';
const PAGE = 'admin.php?page=flxb-partners';

/** A redirect notice in the start page (notice=ok:<text>) shows once; the address loses flxb_msg. */
function useStartNotice(query: string) {
  const host = useHost();
  const { told } = useDonate();
  useEffect(() => {
    const n = new URLSearchParams(query).get('notice') ?? '';
    const i = n.indexOf(':');
    if (i > 0) told({ kind: n.slice(0, i) === 'ok' ? 'ok' : 'error', text: n.slice(i + 1) });
    if (host.mode === 'wp' && typeof history !== 'undefined') {
      const u = new URL(location.href);
      if (u.searchParams.has('flxb_msg')) {
        u.searchParams.delete('flxb_msg');
        u.searchParams.delete('flxb_err');
        history.replaceState(history.state, '', u.pathname + u.search);
      }
    }
  }, []); // eslint-disable-line react-hooks/exhaustive-deps
}

/** start page 'settle?kind=&period=&partner=' → the settlement filter (as PHP preloads it). */
function settleOf(query: string): Settle {
  const q = new URLSearchParams(query);
  return { kind: q.get('kind') === 'source' ? 'source' : 'service', period: q.get('period') ?? '', partner: Number(q.get('partner')) || 0 };
}

export function Partners({ page, query, notice }: { page: 'partners' | 'settle'; query: string; notice: ReactNode }) {
  const host = useHost();
  const { t } = useDonate();
  useStartNotice(query);
  const [tab, setTab] = useState<Tab>(page === 'settle' ? 'settle' : 'list');
  const [settle, setSettle] = useState<Settle>(() => settleOf(page === 'settle' ? query : ''));
  const list = useFlxQuery(FlxDonatePartners, {}, { enabled: tab === 'list' });
  const label = list.data?.flxDonationPartners.partnerLabel ?? '';

  // The address follows the tab and the settlement filter (classic arguments: tab, kind, month / from+to, partner).
  useEffect(() => {
    if (host.mode !== 'wp' || typeof history === 'undefined') return;
    const u = new URL(location.href);
    if (u.searchParams.get('page') !== 'flxb-partners') return;
    const next = new URLSearchParams({ page: 'flxb-partners' });
    if (tab === 'settle') {
      next.set('tab', 'settle');
      next.set('kind', settle.kind);
      const m = settle.period.match(/^(\d{4}-\d{2}-\d{2})\.\.(\d{4}-\d{2}-\d{2})$/);
      if (m) {
        next.set('from', m[1] ?? '');
        next.set('to', m[2] ?? '');
      } else if (settle.period) next.set('month', settle.period);
      if (settle.partner) next.set('partner', String(settle.partner));
    }
    const s = '?' + next.toString();
    if (u.search !== s) history.replaceState(history.state, '', u.pathname + s);
  }, [tab, settle, host.mode]);

  const canManage = list.data?.flxDonationPartners.canManage ?? false;
  return (
    <Screen<Tab>
      className="flxdn"
      width="wide"
      icon={host.appIcon}
      title={t('Partners')}
      subtitle={label ? t('Who brings gifts in and who carries the work out (%s), their agreed prices and payments.', label) : undefined}
      actions={
        canManage ? (
          <Go className="flxdn-btn flxdn-btn--primary" href={PAGE + '&tab=edit'}>
            <Ico name="plus" size={15} /> {t('New partner')}
          </Go>
        ) : null
      }
      notice={notice}
      tabs={[
        { id: 'list', label: t('Partners') },
        { id: 'settle', label: t('Settlement') },
      ]}
      tab={tab}
      onTab={setTab}
    >
      {tab === 'settle' ? <Settlement value={settle} onChange={setSettle} /> : list.data ? <List data={list.data.flxDonationPartners} /> : list.isError ? <Failed retry={() => void list.refetch()} /> : <Loading />}
    </Screen>
  );
}

function List({ data }: { data: FlxDonatePartnersQuery['flxDonationPartners'] }) {
  const { t } = useDonate();
  if (!data.items.length) {
    return (
      <div className="flxdn-panel">
        <div className="flxdn-emptystate">
          <Ico name="users" size={32} />
          <h2>{t('No partners yet')}</h2>
          <p>{t('Add the places or organisations that carry the work out, and the offices that bring gifts in.')}</p>
        </div>
      </div>
    );
  }
  return (
    <div className="flxdn-panel">
      <div className="flxdn-tablewrap">
        <table className="flxdn-table">
          <thead>
            <tr>
              <th scope="col">{t('Name')}</th>
              <th scope="col">{t('Role')}</th>
              <th scope="col">{t('Donation types')}</th>
              <th scope="col">{t('Agreement')}</th>
              <th scope="col">{t('Contact')}</th>
              <th scope="col">{t('Status')}</th>
            </tr>
          </thead>
          <tbody>
            {data.items.map((p) => (
              <tr key={p.databaseId}>
                <td className="flxdn-col-main">
                  <Go href={PAGE + '&id=' + p.databaseId}>{p.name}</Go>
                  {p.group ? <span className="flxdn-sub2">{p.group}</span> : null}
                </td>
                <td data-label={t('Role')}>
                  <span>{p.roles}</span>
                </td>
                <td data-label={t('Donation types')}>
                  <span>{p.types}</span>
                </td>
                <td data-label={t('Agreement')}>
                  <span>{p.agreement}</span>
                </td>
                <td data-label={t('Contact')}>
                  <span>{p.contact}</span>
                </td>
                <td data-label={t('Status')}>
                  <span>{p.active ? <span className="flxdn-pill flxdn-pill--ok">{t('Active')}</span> : <span className="flxdn-pill flxdn-pill--muted">{t('Passive')}</span>}</span>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

function Settlement({ value, onChange }: { value: Settle; onChange: (s: Settle) => void }) {
  const { t, told } = useDonate();
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const vars = { kind: value.kind.toUpperCase(), period: value.period, partnerId: value.partner };
  const q = useFlxQuery(FlxDonatePartnerSettlement, vars, { placeholderData: keepPreviousData });
  const s = q.data?.flxDonationPartnerSettlement;
  const [from, setFrom] = useState(NONE);
  const [to, setTo] = useState(NONE);
  const [pid, setPid] = useState(String(value.partner || ''));
  useEffect(() => {
    if (s) {
      setFrom(s.from);
      setTo(s.to);
    }
  }, [s?.from, s?.to]); // eslint-disable-line react-hooks/exhaustive-deps

  const reprice = useFlxMutation(FlxDonationPartnerReprice, {
    onSuccess: (r) => {
      const err = r.flxDonationPartnerReprice?.errors?.[0]?.message;
      told(err ? { kind: 'error', text: err } : { kind: 'ok', text: r.flxDonationPartnerReprice?.message || t('Saved.') });
      void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxDonatePartnerSettlement) });
    },
    onError: (e) => told({ kind: 'error', text: e.message || t('The action could not be completed.') }),
  });

  if (!s) return q.isError ? <Failed retry={() => void q.refetch()} /> : <Loading />;
  const service = value.kind === 'service';
  const show = (e: FormEvent) => {
    e.preventDefault();
    const day = /^\d{4}-\d{2}-\d{2}$/;
    onChange({ ...value, period: day.test(from) && day.test(to) ? from + '..' + to : value.period, partner: Number(pid) || 0 });
  };
  const kinds: { k: Settle['kind']; l: string }[] = [
    { k: 'service', l: t('%s (cost)', s.partnerLabel) },
    { k: 'source', l: t('Brought in (gift price)') },
  ];
  return (
    <div className={q.isFetching ? 'is-busy' : undefined}>
      <div className="flxdn-seg">
        {kinds.map((x) => (
          <button key={x.k} type="button" className={x.k === value.kind ? 'is-on' : undefined} onClick={() => onChange({ kind: x.k, period: /^\d{4}-\d{2}$/.test(value.period) ? value.period : NONE, partner: 0 })}>
            {x.l}
          </button>
        ))}
      </div>
      <div className="flxdn-months">
        {s.months.map((m) => (
          <button key={m.key} type="button" className={'flxdn-month' + (m.key === s.period ? ' is-on' : '')} onClick={() => onChange({ ...value, period: m.key })}>
            <b>{m.label}</b>
            <span>{m.count}</span>
            <small>{m.sum}</small>
          </button>
        ))}
      </div>
      <form className="flxdn-filters" onSubmit={show}>
        <label>
          {t('From')} <DatePicker value={from} onChange={setFrom} aria-label={t('From')} />
        </label>
        <label>
          {t('To')} <DatePicker value={to} onChange={setTo} aria-label={t('To')} />
        </label>
        <label>
          <select value={pid} onChange={(e) => setPid(e.target.value)} aria-label={t('Partner')}>
            <option value="">{t('All partners')}</option>
            {s.partners.map((p) => (
              <option key={p.value} value={p.value}>
                {p.label}
              </option>
            ))}
          </select>
        </label>
        <div className="flxdn-filters__end">
          <button className="flxdn-btn flxdn-btn--primary" type="submit">
            {t('Show')}
          </button>{' '}
          <a className="flxdn-btn" href={s.csvUrl}>
            <Ico name="download" /> {t('CSV')}
          </a>
        </div>
      </form>
      <div className="flxdn-panel">
        {!s.rows.length ? (
          <div className="flxdn-emptystate">
            <h2>{t('Nothing in this period')}</h2>
          </div>
        ) : (
          <div className="flxdn-tablewrap">
            <table className="flxdn-table">
              <thead>
                <tr>
                  <th scope="col">{t('Partner')}</th>
                  <th scope="col" className="num">
                    {t('Records')}
                  </th>
                  <th scope="col" className="num">
                    {service ? t('Cost') : t('Gift price')}
                  </th>
                  <th scope="col" className="num">
                    {service ? t('Paid') : t('Received')}
                  </th>
                  <th scope="col" className="num">
                    {t('Open')}
                  </th>
                </tr>
              </thead>
              <tbody>
                {s.rows.map((r) => (
                  <tr key={r.partnerId}>
                    <td className="flxdn-col-main">
                      <button type="button" className="flxdn-linkbtn" onClick={() => onChange({ ...value, period: s.period, partner: r.partnerId })}>
                        {r.name}
                      </button>
                      {r.uncosted ? <span className="flxdn-sub2">{r.uncosted}</span> : null}
                    </td>
                    <td className="num" data-label={t('Records')}>
                      {r.count}
                    </td>
                    <td className="num" data-label={service ? t('Cost') : t('Gift price')}>
                      {r.sum}
                    </td>
                    <td className="num" data-label={service ? t('Paid') : t('Received')}>
                      {r.moved}
                    </td>
                    <td className="num" data-label={t('Open')}>
                      <b>{r.open}</b>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
      {s.detail && s.detail.items.length ? (
        <section className="flxdn-card flxdn-card--gap">
          <div className="flxdn-card__head">
            <h2 className="flxdn-card__title">{s.detail.name}</h2>
            {s.detail.canReprice ? (
              <button type="button" className="flxdn-btn flxdn-btn--sm" disabled={reprice.isPending} onClick={() => reprice.mutate({ input: { partnerId: value.partner, period: s.period } })}>
                {t('Re-read prices from the agreements')}
              </button>
            ) : null}
          </div>
          <div className="flxdn-tablewrap">
            <table className="flxdn-table flxdn-table--compact">
              <thead>
                <tr>
                  <th scope="col">{t('Date')}</th>
                  <th scope="col">{t('Code')}</th>
                  <th scope="col">{t('On behalf of')}</th>
                  <th scope="col">{t('Option')}</th>
                  <th scope="col">{t('Status')}</th>
                  <th scope="col" className="num">
                    {t('Price')}
                  </th>
                </tr>
              </thead>
              <tbody>
                {s.detail.items.map((x, i) => (
                  <tr key={x.code + i}>
                    <td data-label={t('Date')}>{x.date}</td>
                    <td data-label={t('Code')}>
                      <a className="flxdn-mono" href={x.trackUrl}>
                        {x.code}
                      </a>
                    </td>
                    <td data-label={t('On behalf of')}>{x.beneficiary}</td>
                    <td data-label={t('Option')}>{x.option}</td>
                    <td data-label={t('Status')}>
                      <span className={'flxdn-pill flxdn-pill--' + x.tone}>{x.status}</span>
                    </td>
                    <td className="num" data-label={t('Price')}>
                      {x.price}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>
      ) : null}
    </div>
  );
}

type P = NonNullable<FlxDonatePartnerQuery['flxDonationPartner']>;

export function Partner({ id, query, notice }: { id: number; query: string; notice: ReactNode }) {
  const host = useHost();
  const { t } = useDonate();
  useStartNotice(query);
  const q = useFlxQuery(FlxDonatePartner, { id });
  const p = q.data?.flxDonationPartner;
  const back = (
    <Go className="flxdn-back" href={PAGE}>
      <span aria-hidden="true">‹</span> {t('Partners')}
    </Go>
  );
  if (!q.data) {
    return (
      <Screen className="flxdn" width="wide" icon={host.appIcon} title={id ? t('Partners') : t('New partner')} notice={notice}>
        {q.isError ? <Failed retry={() => void q.refetch()} /> : <Loading />}
      </Screen>
    );
  }
  if (!p) {
    return (
      <Screen className="flxdn" width="wide" icon={host.appIcon} title={t('Partner not found.')} notice={notice} actions={back}>
        {null}
      </Screen>
    );
  }
  return <Editor key={p.databaseId} p={p} back={back} notice={notice} />;
}

function Editor({ p, back, notice }: { p: P; back: ReactNode; notice: ReactNode }) {
  const host = useHost();
  const { t, told } = useDonate();
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const can = p.canManage;
  const [v, setV] = useState({
    name: p.name,
    pkey: p.pkey,
    group: p.group,
    isService: p.isService,
    isSource: p.isSource,
    typeIds: p.typeIds,
    contactName: p.contactName,
    phone: p.phone,
    email: p.email,
    currency: p.currency,
    active: p.active,
    note: p.note,
  });
  const set = <K extends keyof typeof v>(k: K, x: (typeof v)[K]) => setV((d) => ({ ...d, [k]: x }));
  const [pay, setPay] = useState({ received: false, date: p.today, amount: NONE, currency: p.currency, note: NONE });
  const [agr, setAgr] = useState({ typeOption: '0|', validFrom: p.today, price: NONE, currency: p.currency, note: NONE });
  const [ask, setAsk] = useState<{ kind: 'price' | 'payment'; id: number } | null>(null);

  const fail = (e: Error) => told({ kind: 'error', text: e.message || t('The action could not be completed.') });
  const reload = () => {
    void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxDonatePartner) });
    void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxDonatePartners) });
  };
  /** A mutation result: the first error, or the message and a refetch; true when it went through. */
  const done = (res: { message?: string | null; errors?: { message: string }[] | null } | null | undefined): boolean => {
    const err = res?.errors?.[0]?.message;
    if (err || !res?.message) {
      told({ kind: 'error', text: err || t('The action could not be completed.') });
      return false;
    }
    told({ kind: 'ok', text: res.message });
    reload();
    return true;
  };

  const save = useFlxMutation(FlxDonationPartnerSave, {
    onSuccess: (r) => {
      const res = r.flxDonationPartnerSave;
      if (!p.databaseId && res?.databaseId && !res.errors?.length) {
        if (host.mode === 'wp' && typeof location !== 'undefined') location.assign(PAGE + '&id=' + res.databaseId + '&flxb_msg=saved');
        else host.openUrl(PAGE + '&id=' + res.databaseId);
        return;
      }
      done(res);
    },
    onError: fail,
  });
  const priceAdd = useFlxMutation(FlxDonationPartnerPriceAdd, {
    onSuccess: (r) => {
      if (done(r.flxDonationPartnerPriceAdd)) setAgr((a) => ({ ...a, price: NONE, note: NONE }));
    },
    onError: fail,
  });
  const priceToggle = useFlxMutation(FlxDonationPartnerPriceToggle, { onSuccess: (r) => void done(r.flxDonationPartnerPriceToggle), onError: fail });
  const priceDelete = useFlxMutation(FlxDonationPartnerPriceDelete, {
    onSuccess: (r) => {
      setAsk(null);
      done(r.flxDonationPartnerPriceDelete);
    },
    onError: fail,
  });
  const payAdd = useFlxMutation(FlxDonationPartnerPaymentAdd, {
    onSuccess: (r) => {
      if (done(r.flxDonationPartnerPaymentAdd)) setPay((a) => ({ ...a, amount: NONE, note: NONE }));
    },
    onError: fail,
  });
  const payDelete = useFlxMutation(FlxDonationPartnerPaymentDelete, {
    onSuccess: (r) => {
      setAsk(null);
      done(r.flxDonationPartnerPaymentDelete);
    },
    onError: fail,
  });

  const submit = (e: FormEvent) => {
    e.preventDefault();
    save.mutate({ input: { id: p.databaseId, ...v } });
  };
  const toggleType = (tid: number, on: boolean) => set('typeIds', on ? [...v.typeIds, tid] : v.typeIds.filter((x) => x !== tid));
  const curSelect = (id: string, value: string, onChange: (c: string) => void) => (
    <select id={id} value={value} onChange={(e) => onChange(e.target.value)}>
      {(p.currencies.includes(value) ? p.currencies : [...p.currencies, value]).map((c) => (
        <option key={c} value={c}>
          {c}
        </option>
      ))}
    </select>
  );
  const busyDel = priceDelete.isPending || payDelete.isPending;

  return (
    <Screen className="flxdn" width="wide" icon={host.appIcon} title={p.databaseId ? p.name : t('New partner')} actions={back} notice={notice}>
      <div className="flxdn-grid flxdn-grid--1-1">
        <section className="flxdn-card">
          <div className="flxdn-card__head">
            <h2 className="flxdn-card__title">{t('Partner')}</h2>
          </div>
          <form className="flxdn-form" onSubmit={submit}>
            <fieldset disabled={!can} className="flxdn-fieldset">
              <div className="flxdn-fields-2">
                <div className="flxdn-field">
                  <label htmlFor="flxdn-pn">{t('Name')}</label>
                  <input id="flxdn-pn" type="text" maxLength={190} required value={v.name} onChange={(e) => set('name', e.target.value)} />
                </div>
                <div className="flxdn-field">
                  <label htmlFor="flxdn-pg">{t('Group')}</label>
                  <input id="flxdn-pg" type="text" maxLength={190} value={v.group} onChange={(e) => set('group', e.target.value)} />
                  <p className="flxdn-help">{t('Optional, for reports (e.g. country or region).')}</p>
                </div>
              </div>
              <div className="flxdn-field">
                <span className="flxdn-label">{t('Role')}</span>
                <Check switch checked={v.isService} onChange={(x) => set('isService', x)}>
                  {t('Carries the work out (%s)', p.partnerLabel)}
                </Check>
                <Check switch checked={v.isSource} onChange={(x) => set('isSource', x)}>
                  {t('Brings gifts in (office, organization)')}
                </Check>
              </div>
              <div className="flxdn-field">
                <span className="flxdn-label">{t('Donation types')}</span>
                <div className="flxdn-checks">
                  {p.types.map((ty) => (
                    <Check key={ty.value} checked={v.typeIds.includes(Number(ty.value))} onChange={(x) => toggleType(Number(ty.value), x)}>
                      {ty.label}
                    </Check>
                  ))}
                </div>
                <p className="flxdn-help">{t('None ticked = all types.')}</p>
              </div>
              <div className="flxdn-fields-2">
                <div className="flxdn-field">
                  <label htmlFor="flxdn-pc">{t('Contact person')}</label>
                  <input id="flxdn-pc" type="text" maxLength={190} value={v.contactName} onChange={(e) => set('contactName', e.target.value)} />
                </div>
                <div className="flxdn-field">
                  <label htmlFor="flxdn-pp">{t('Phone (WhatsApp)')}</label>
                  <input id="flxdn-pp" type="tel" maxLength={32} value={v.phone} onChange={(e) => set('phone', e.target.value)} />
                </div>
              </div>
              <div className="flxdn-fields-amount">
                <div className="flxdn-field">
                  <label htmlFor="flxdn-pe">{t('E-mail')}</label>
                  <input id="flxdn-pe" type="email" maxLength={190} value={v.email} onChange={(e) => set('email', e.target.value)} />
                </div>
                <div className="flxdn-field">
                  <label htmlFor="flxdn-pcur">{t('Currency')}</label>
                  {curSelect('flxdn-pcur', v.currency, (c) => set('currency', c))}
                </div>
              </div>
              <div className="flxdn-field">
                <label htmlFor="flxdn-pnote">{t('Note')}</label>
                <textarea id="flxdn-pnote" rows={2} value={v.note} onChange={(e) => set('note', e.target.value)} />
              </div>
              <div className="flxdn-fields-2">
                <div className="flxdn-field">
                  <label htmlFor="flxdn-pk">{t('Key')}</label>
                  <input id="flxdn-pk" type="text" maxLength={64} value={v.pkey} onChange={(e) => set('pkey', e.target.value)} />
                  <p className="flxdn-help">{t('Optional. Presets and imports find the partner by it.')}</p>
                </div>
                <div className="flxdn-field flxdn-field--switch">
                  <Check switch checked={v.active} onChange={(x) => set('active', x)}>
                    {t('Active')}
                  </Check>
                </div>
              </div>
            </fieldset>
            {can ? (
              <div className="flxdn-formactions">
                <button type="submit" className="flxdn-btn flxdn-btn--primary" disabled={save.isPending}>
                  {t('Save')}
                </button>
              </div>
            ) : null}
          </form>
        </section>

        {p.databaseId ? (
          <section className="flxdn-card">
            <div className="flxdn-card__head">
              <div>
                <h2 className="flxdn-card__title">{t('Payments')}</h2>
                <p className="flxdn-card__hint">{t('Money paid to the partner, or received from it. The settlement subtracts it.')}</p>
              </div>
            </div>
            {p.payments.length ? (
              <div className="flxdn-tablewrap flxdn-tablewrap--mid">
                <table className="flxdn-table flxdn-table--compact">
                  <thead>
                    <tr>
                      <th scope="col">{t('Date')}</th>
                      <th scope="col">{t('Direction')}</th>
                      <th scope="col" className="num">
                        {t('Amount')}
                      </th>
                      <th scope="col">{t('Note')}</th>
                      <th scope="col">
                        <span className="screen-reader-text">{t('Actions')}</span>
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    {p.payments.map((py) => (
                      <tr key={py.databaseId}>
                        <td data-label={t('Date')}>{py.date}</td>
                        <td data-label={t('Direction')}>{py.direction}</td>
                        <td className="num" data-label={t('Amount')}>
                          {py.amount}
                        </td>
                        <td data-label={t('Note')}>{py.note}</td>
                        <td>
                          {can ? (
                            <button type="button" className="flxdn-linkbtn flxdn-linkbtn--danger" onClick={() => setAsk({ kind: 'payment', id: py.databaseId })}>
                              {t('Delete')}
                            </button>
                          ) : null}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : null}
            {can ? (
              <form
                className="flxdn-form"
                onSubmit={(e) => {
                  e.preventDefault();
                  payAdd.mutate({ input: { partnerId: p.databaseId, ...pay } });
                }}
              >
                <div className="flxdn-fields-2">
                  <div className="flxdn-field">
                    <label htmlFor="flxdn-pay-dir">{t('Direction')}</label>
                    <select id="flxdn-pay-dir" value={pay.received ? 'in' : 'out'} onChange={(e) => setPay({ ...pay, received: e.target.value === 'in' })}>
                      <option value="out">{t('We paid')}</option>
                      <option value="in">{t('We received')}</option>
                    </select>
                  </div>
                  <div className="flxdn-field">
                    <label htmlFor="flxdn-pay-date">{t('Date')}</label>
                    <DatePicker id="flxdn-pay-date" value={pay.date} onChange={(x: string) => setPay({ ...pay, date: x })} />
                  </div>
                </div>
                <div className="flxdn-fields-amount">
                  <div className="flxdn-field">
                    <label htmlFor="flxdn-pay-amt">{t('Amount')}</label>
                    <input id="flxdn-pay-amt" type="text" inputMode="decimal" required value={pay.amount} onChange={(e) => setPay({ ...pay, amount: e.target.value })} />
                  </div>
                  <div className="flxdn-field">
                    <label htmlFor="flxdn-pay-cur">{t('Currency')}</label>
                    {curSelect('flxdn-pay-cur', pay.currency, (c) => setPay({ ...pay, currency: c }))}
                  </div>
                </div>
                <div className="flxdn-field">
                  <label htmlFor="flxdn-pay-note">{t('Note')}</label>
                  <input id="flxdn-pay-note" type="text" maxLength={190} value={pay.note} onChange={(e) => setPay({ ...pay, note: e.target.value })} />
                </div>
                <div className="flxdn-formactions">
                  <button type="submit" className="flxdn-btn" disabled={payAdd.isPending}>
                    {t('Add payment')}
                  </button>
                </div>
              </form>
            ) : null}
          </section>
        ) : (
          <div />
        )}
      </div>

      {p.databaseId ? (
        <section className="flxdn-card flxdn-card--gap">
          <div className="flxdn-card__head">
            <div>
              <h2 className="flxdn-card__title">{t('Agreements')}</h2>
              <p className="flxdn-card__hint">
                {t('Agreed cost per type and option. The price valid on the day an item is sent is kept on the item. A partner whose agreements for a type are all switched off gets no new work for it.')}
              </p>
            </div>
          </div>
          {p.agreements.length ? (
            <div className="flxdn-tablewrap flxdn-tablewrap--mid">
              <table className="flxdn-table flxdn-table--compact">
                <thead>
                  <tr>
                    <th scope="col">{t('Donation type')}</th>
                    <th scope="col">{t('Option')}</th>
                    <th scope="col" className="num">
                      {t('Price')}
                    </th>
                    <th scope="col">{t('Valid from')}</th>
                    <th scope="col">{t('Status')}</th>
                    <th scope="col">{t('Note')}</th>
                    <th scope="col">
                      <span className="screen-reader-text">{t('Actions')}</span>
                    </th>
                  </tr>
                </thead>
                <tbody>
                  {p.agreements.map((a) => {
                    const pill = a.active ? <span className="flxdn-pill flxdn-pill--ok">{t('Active')}</span> : <span className="flxdn-pill flxdn-pill--muted">{t('Passive')}</span>;
                    return (
                      <tr key={a.databaseId}>
                        <td data-label={t('Donation type')}>{a.type}</td>
                        <td data-label={t('Option')}>{a.option}</td>
                        <td className="num" data-label={t('Price')}>
                          {a.price}
                        </td>
                        <td data-label={t('Valid from')}>{a.validFrom}</td>
                        <td data-label={t('Status')}>
                          {can ? (
                            <button type="button" className="flxdn-linkbtn" disabled={priceToggle.isPending} onClick={() => priceToggle.mutate({ input: { id: a.databaseId } })}>
                              {pill}
                            </button>
                          ) : (
                            pill
                          )}
                        </td>
                        <td data-label={t('Note')}>{a.note}</td>
                        <td>
                          {can ? (
                            <button type="button" className="flxdn-linkbtn flxdn-linkbtn--danger" onClick={() => setAsk({ kind: 'price', id: a.databaseId })}>
                              {t('Delete')}
                            </button>
                          ) : null}
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          ) : null}
          {can ? (
            <form
              className="flxdn-form"
              onSubmit={(e) => {
                e.preventDefault();
                priceAdd.mutate({ input: { partnerId: p.databaseId, ...agr } });
              }}
            >
              <div className="flxdn-fields-2">
                <div className="flxdn-field">
                  <label htmlFor="flxdn-agr-type">{t('Donation type and option')}</label>
                  <select id="flxdn-agr-type" value={agr.typeOption} onChange={(e) => setAgr({ ...agr, typeOption: e.target.value })}>
                    {p.typeOptions.map((o) => (
                      <option key={o.value} value={o.value}>
                        {o.label}
                      </option>
                    ))}
                  </select>
                </div>
                <div className="flxdn-field">
                  <label htmlFor="flxdn-agr-from">{t('Valid from')}</label>
                  <DatePicker id="flxdn-agr-from" value={agr.validFrom} onChange={(x: string) => setAgr({ ...agr, validFrom: x })} />
                </div>
              </div>
              <div className="flxdn-fields-amount">
                <div className="flxdn-field">
                  <label htmlFor="flxdn-agr-price">{t('Price')}</label>
                  <input id="flxdn-agr-price" type="text" inputMode="decimal" required value={agr.price} onChange={(e) => setAgr({ ...agr, price: e.target.value })} />
                </div>
                <div className="flxdn-field">
                  <label htmlFor="flxdn-agr-cur">{t('Currency')}</label>
                  {curSelect('flxdn-agr-cur', agr.currency, (c) => setAgr({ ...agr, currency: c }))}
                </div>
              </div>
              <div className="flxdn-field">
                <label htmlFor="flxdn-agr-note">{t('Note')}</label>
                <input id="flxdn-agr-note" type="text" maxLength={190} value={agr.note} onChange={(e) => setAgr({ ...agr, note: e.target.value })} />
              </div>
              <div className="flxdn-formactions">
                <button type="submit" className="flxdn-btn flxdn-btn--primary" disabled={priceAdd.isPending}>
                  {t('Add agreement')}
                </button>
              </div>
            </form>
          ) : null}
        </section>
      ) : null}

      {ask ? (
        <Modal title={t('Delete')} onClose={() => !busyDel && setAsk(null)}>
          <p>{ask.kind === 'price' ? t('Delete this agreement? Items already sent keep their price.') : t('Delete this payment?')}</p>
          <div className="flxdn-actions">
            <button type="button" className="flxdn-btn" disabled={busyDel} onClick={() => setAsk(null)}>
              {t('Close')}
            </button>
            <button
              type="button"
              className="flxdn-btn flxdn-btn--primary flxdn-btn--danger-solid"
              disabled={busyDel}
              onClick={() => (ask.kind === 'price' ? priceDelete.mutate({ input: { id: ask.id } }) : payDelete.mutate({ input: { id: ask.id } }))}
            >
              {t('Delete')}
            </button>
          </div>
        </Modal>
      ) : null}
    </Screen>
  );
}
