// Add donation (?page=flxb-add[&donor=N]). The manager records a gift paid or promised by bank transfer, in
// cash or another way (one "type · option" choice, live total, people on behalf of with one certificate each);
// staff with the request right open a donation request instead and get the payment link. PHP preloads the form
// (FLXB_Admin::preload_add()); the phone fields are the flx-brand phone widget (flx_phone_field markup).
import { useMemo, useRef, useState, type FormEvent, type ReactNode } from 'react';
import { useFlxMutation, useFlxQuery } from '@fluxwp/graphql/react';
import { FlxDonateAdd, FlxDonationAdd, FlxDonationRequest } from '@fluxwp/graphql/operations';
import type { FlxDonateAddQuery } from '@fluxwp/graphql/types';
import { Check, Loading, Screen } from '@fluxwp/ui';
import { useHost } from '../host';
import { Go, useDonate } from './shared';
import { Failed } from './Subscriptions';

type F = NonNullable<FlxDonateAddQuery['flxDonationAddForm']>;
type T = F['types'][number];
type O = T['options'][number];
type Person = { id: number; name: string; email: string; phone: string };

const NONE = '';
const numOf = (s: string) => Number(s.replace(',', '.')) || 0;

export function Add({ query, notice }: { query: string; notice: ReactNode }) {
  const host = useHost();
  const { t } = useDonate();
  const donorId = Number(new URLSearchParams(query).get('donor')) || 0;
  const q = useFlxQuery(FlxDonateAdd, { donorId });
  const f = q.data?.flxDonationAddForm;
  if (!f) {
    return (
      <Screen className="flxdn" width="wide" icon={host.appIcon} title={t('Add donation')} notice={notice}>
        {q.isError ? <Failed retry={() => void q.refetch()} /> : <Loading />}
      </Screen>
    );
  }
  return f.request ? <Request f={f} notice={notice} /> : <Record f={f} notice={notice} />;
}

/** Money as the classic form shows it: 1.234,50 ₺. */
function useMoney(lang: string) {
  return useMemo(() => {
    const loc = lang.replace('_', '-') || undefined;
    return (v: number, c: string) => {
      let sym = c;
      try {
        new Intl.NumberFormat(loc, { style: 'currency', currency: c || 'TRY', currencyDisplay: 'narrowSymbol' }).formatToParts(0).forEach((x) => {
          if (x.type === 'currency') sym = x.value;
        });
      } catch {
        /* unknown currency: the code */
      }
      try {
        return new Intl.NumberFormat(loc, { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v) + ' ' + sym;
      } catch {
        return v.toFixed(2) + ' ' + sym;
      }
    };
  }, [lang]);
}

/**
 * The flx-brand phone widget (flx_phone_field): phone.js turns it into a country picker and keeps the E.164
 * number in the hidden input. Without the script the visible input is a plain phone field.
 */
function Phone({ id, k, value, label, placeholder, onType }: { id?: string; k: string; value?: string; label: string; placeholder?: string; onType?: (v: string) => void }) {
  const { t } = useDonate();
  return (
    <div className="flx-ph" data-flx-phone="" data-flxdn-phone={k} data-placeholder={placeholder ? '' : undefined} onInput={(e) => onType?.((e.target as HTMLInputElement).value)}>
      <button type="button" className="flx-ph__cc" aria-haspopup="listbox" aria-expanded="false" aria-label={t('Country')}>
        <span className="flx-ph__flag" aria-hidden="true" />
        <span className="flx-ph__dial" />
        <svg className="flx-ph__chev" viewBox="0 0 12 12" aria-hidden="true">
          <path d="M3 4.5l3 3 3-3" />
        </svg>
      </button>
      <span className="flx-ph__box">
        <input type="tel" className="flx-ph__num" id={id} defaultValue={value} inputMode="tel" autoComplete="tel-national" aria-label={label} placeholder={placeholder} maxLength={32} />
        <span className="flx-ph__state" aria-hidden="true" />
      </span>
      <input type="hidden" className="flx-ph__val" defaultValue={value} />
    </div>
  );
}

/** The number of a phone field: the widget's E.164 value, or what was typed without the widget. */
function phoneOf(root: HTMLElement | null, k: string): string {
  const el = root?.querySelector<HTMLElement>(`[data-flxdn-phone="${k}"]`);
  if (!el) return '';
  const num = el.querySelector<HTMLInputElement>('.flx-ph__num')?.value.trim() ?? '';
  const val = el.querySelector<HTMLInputElement>('.flx-ph__val')?.value.trim() ?? '';
  return el.hasAttribute('data-flx-phone-on') ? val || num : num;
}

function Stepper({ id, value, onChange }: { id: string; value: number; onChange: (n: number) => void }) {
  const { t } = useDonate();
  const set = (n: number) => onChange(Math.min(1000, Math.max(1, n || 1)));
  return (
    <div className="flxdn-stepper">
      <button type="button" className="flxdn-stepper__btn" data-flxb-step="-1" aria-label={t('Decrease')} onClick={() => set(value - 1)}>
        −
      </button>
      <input type="number" id={id} min={1} max={1000} step={1} value={value} inputMode="numeric" onChange={(e) => set(parseInt(e.target.value, 10))} />
      <button type="button" className="flxdn-stepper__btn" data-flxb-step="1" aria-label={t('Increase')} onClick={() => set(value + 1)}>
        +
      </button>
    </div>
  );
}

function Seg({ name, value, opts, onChange }: { name: string; value: string; opts: { value: string; label: string }[]; onChange: (v: string) => void }) {
  return (
    <div className="flxdn-seg flxdn-seg--radio" role="radiogroup">
      {opts.map((o) => (
        <label key={o.value}>
          <input type="radio" name={name} value={o.value} checked={value === o.value} onChange={() => onChange(o.value)} />
          <span>{o.label}</span>
        </label>
      ))}
    </div>
  );
}

function Back({ f }: { f: F }) {
  const { t } = useDonate();
  return f.listUrl ? (
    <Go className="flxdn-back" href={f.listUrl}>
      <span aria-hidden="true">‹</span> {f.listLabel || t('Donations')}
    </Go>
  ) : null;
}

function Record({ f, notice }: { f: F; notice: ReactNode }) {
  const host = useHost();
  const { t, tn, lang, told } = useDonate();
  const money = useMoney(lang);
  const root = useRef<HTMLFormElement>(null);
  const picks = useMemo(() => {
    const out: { value: string; label: string }[] = [];
    f.types.forEach((ty) => {
      ty.options.forEach((o) => {
        const label = ty.options.length > 1 || ty.free ? ty.title + ' · ' + o.label : ty.title;
        out.push({ value: ty.databaseId + '|' + o.key, label: label + (o.price > 0 ? ' · ' + money(o.price, ty.currency) : '') });
      });
      if (ty.free || !ty.options.length) out.push({ value: ty.databaseId + '|', label: ty.title + ' · ' + t('Free amount') });
    });
    out.push({ value: '0|', label: t('No donation type (free amount)') });
    return out;
  }, [f.types, money, t]);
  const [pick, setPick] = useState(picks[0]?.value ?? '0|');
  const [qty, setQty] = useState(1);
  const [priceOpen, setPriceOpen] = useState(false);
  const [unitIn, setUnitIn] = useState(NONE);
  const [reason, setReason] = useState(NONE);
  const [amount, setAmount] = useState(NONE);
  const [currency, setCurrency] = useState(f.currency);
  const [ben, setBen] = useState(NONE);
  const [proxyOk, setProxyOk] = useState(false);
  const [name, setName] = useState(f.donor.name);
  const [email, setEmail] = useState(f.donor.email);
  const [consent, setConsent] = useState({ kvkk: f.donor.consentKvkk, email: f.donor.consentEmail, sms: f.donor.consentSms });
  const [forOther, setForOther] = useState(false);
  const [people, setPeople] = useState<Person[]>([{ id: 0, name: NONE, email: NONE, phone: NONE }]);
  const [nextId, setNextId] = useState(1);
  const [method, setMethod] = useState('bank_transfer');
  const [account, setAccount] = useState(NONE);
  const [paid, setPaid] = useState('completed');
  const [date, setDate] = useState(f.now);
  const [reference, setReference] = useState(NONE);
  const [ref, setRef] = useState('0');
  const [note, setNote] = useState(NONE);

  const [tid, okey] = pick.split('|');
  const d: T | null = f.types.find((x) => String(x.databaseId) === tid) ?? null;
  const o: O | null = (d && okey ? d.options.find((x) => x.key === okey) : null) ?? null;
  const free = !o;
  const n = free ? 1 : qty;
  const cur = d && o ? d.currency : currency;
  const unit = o ? (unitIn !== '' ? numOf(unitIn) : o.price) : 0;
  const total = free ? numOf(amount) : unit * n;
  const proxy = !!d?.proxy;
  const on = forOther && !proxy;
  const rows = people.slice(0, Math.max(1, n));
  const per = o ? o.per : 1;
  const unitWord = (k: number) => (o?.unitOne ? k + ' ' + (k === 1 ? o.unitOne : o.unitMany || o.unitOne) : tn('%s piece', '%s pieces', k, k.toLocaleString()));
  const share = (i: number) => (i === 0 ? n - (rows.length - 1) : 1) * per;
  const how = (p: Person) => (p.phone && p.email ? t('WhatsApp + e-mail') : p.phone ? t('WhatsApp') : p.email ? t('e-mail') : t('to the donor'));
  const certs: { name: string; note: string }[] = on
    ? rows.filter((p) => p.name.trim()).map((p) => ({ name: p.name.trim(), note: unitWord(share(rows.indexOf(p))) + ' · ' + how(p) }))
    : [];
  if (!certs.length && d && !proxy && name.trim()) certs.push({ name: name.trim(), note: unitWord(n * per) });
  const showCerts = certs.length > 0 && (!!d?.cert || on);

  const setPerson = (id: number, k: 'name' | 'email' | 'phone', v: string) => setPeople((ps) => ps.map((p) => (p.id === id ? { ...p, [k]: v } : p)));
  const addPerson = () => {
    setPeople((ps) => [...ps.slice(0, Math.max(1, n)), { id: nextId, name: NONE, email: NONE, phone: NONE }]);
    setNextId((x) => x + 1);
  };
  const togglePrice = () => {
    if (priceOpen) {
      setUnitIn(NONE);
      setReason(NONE);
    }
    setPriceOpen(!priceOpen);
  };

  const save = useFlxMutation(FlxDonationAdd, {
    onSuccess: (r) => {
      const res = r.flxDonationAdd;
      const err = res?.errors?.[0]?.message;
      if (err || !res?.url) {
        told({ kind: 'error', text: err || t('The action could not be completed.') });
        return;
      }
      if (host.mode === 'wp' && typeof location !== 'undefined') location.assign(res.url + (res.url.includes('?') ? '&' : '?') + 'flxb_msg=saved');
      else host.openUrl(res.url);
    },
    onError: (e) => told({ kind: 'error', text: e.message || t('The action could not be completed.') }),
  });

  const submit = (e: FormEvent) => {
    e.preventDefault();
    const form = root.current;
    save.mutate({
      input: {
        typeId: d ? d.databaseId : 0,
        optionKey: o ? o.key : '',
        quantity: n,
        unitPrice: o && priceOpen && unitIn.trim() !== '' ? numOf(unitIn) : null,
        priceReason: reason,
        amount: free ? numOf(amount) : null,
        currency: cur,
        beneficiaries: proxy ? ben : '',
        proxyConsent: proxy && proxyOk,
        donor: { name, phone: phoneOf(form, 'donor'), email, consentKvkk: consent.kvkk, consentEmail: consent.email, consentSms: consent.sms },
        forOther: on,
        people: on ? rows.map((p) => ({ name: p.name, phone: phoneOf(form, 'p-' + p.id), email: p.email })) : [],
        method,
        account: method === 'bank_transfer' ? account : '',
        paid: paid === 'completed',
        date,
        reference,
        refUserId: Number(ref) || 0,
        note,
      },
    });
  };

  const sumType = d ? d.title + (o && d.options.length > 1 ? ' · ' + o.label : '') : t('Free amount');
  return (
    <Screen
      className="flxdn"
      width="wide"
      icon={host.appIcon}
      title={t('Add donation')}
      subtitle={t('Record a gift paid or promised by bank transfer, in cash or another way.')}
      actions={<Back f={f} />}
      notice={notice}
    >
      <form ref={root} className="flxdn-form flxdn-add2" onSubmit={submit}>
        <div className="flxdn-add2__grid">
          <div className="flxdn-add2__main">
            <section className="flxdn-card">
              <div className="flxdn-add2__head">
                <h2 className="flxdn-card__title">{t('Donation')}</h2>
              </div>
              <div className="flxdn-add2__gift">
                <div className="flxdn-field">
                  <label htmlFor="flxdn-a-pick">{t('Donation type')}</label>
                  <select id="flxdn-a-pick" value={pick} onChange={(e) => setPick(e.target.value)}>
                    {picks.map((p) => (
                      <option key={p.value} value={p.value}>
                        {p.label}
                      </option>
                    ))}
                  </select>
                  {!free ? (
                    <button type="button" className="flxdn-linkbtn flxdn-add2__more" onClick={togglePrice} aria-expanded={priceOpen}>
                      {t('Different unit price')}
                    </button>
                  ) : null}
                </div>
                {!free ? (
                  <div className="flxdn-field">
                    <label htmlFor="flxdn-a-qty">{t('Quantity')}</label>
                    <Stepper id="flxdn-a-qty" value={qty} onChange={setQty} />
                  </div>
                ) : null}
              </div>
              {!free && priceOpen ? (
                <div className="flxdn-add2__price">
                  <div className="flxdn-field">
                    <label htmlFor="flxdn-a-unit">{t('Unit price')}</label>
                    <input type="number" id="flxdn-a-unit" min={0} step="0.01" value={unitIn} placeholder={o ? String(o.price) : undefined} onChange={(e) => setUnitIn(e.target.value)} autoFocus />
                  </div>
                  <div className="flxdn-field">
                    <label htmlFor="flxdn-a-reason">{t('Reason for a different price')}</label>
                    <input type="text" id="flxdn-a-reason" maxLength={190} value={reason} onChange={(e) => setReason(e.target.value)} />
                  </div>
                </div>
              ) : null}
              {free ? (
                <div className="flxdn-add2__price">
                  <div className="flxdn-field">
                    <label htmlFor="flxdn-a-amount">{t('Amount')}</label>
                    <input type="number" id="flxdn-a-amount" min={0} step="0.01" value={amount} onChange={(e) => setAmount(e.target.value)} />
                  </div>
                  <div className="flxdn-field">
                    <label htmlFor="flxdn-a-cur">{t('Currency')}</label>
                    <select id="flxdn-a-cur" value={currency} onChange={(e) => setCurrency(e.target.value)}>
                      {f.currencies.map((c) => (
                        <option key={c.value} value={c.value}>
                          {c.label}
                        </option>
                      ))}
                    </select>
                  </div>
                </div>
              ) : null}
              {proxy ? (
                <div className="flxdn-field flxdn-add2__proxy">
                  <label htmlFor="flxdn-a-ben">{t('On behalf of')}</label>
                  <textarea id="flxdn-a-ben" rows={3} value={ben} onChange={(e) => setBen(e.target.value)} />
                  <p className="flxdn-help">{t('One name per line, one per unit. Units without a name are given on behalf of the donor.')}</p>
                  {f.proxyVersion != null ? (
                    <Check checked={proxyOk} onChange={setProxyOk}>
                      {t('The donor accepted the proxy (vekâlet) text, version %s', f.proxyVersion.toLocaleString())}
                    </Check>
                  ) : null}
                </div>
              ) : null}

              <hr className="flxdn-add2__hr" />
              <div className="flxdn-add2__head">
                <h2 className="flxdn-card__title">{t('Donor')}</h2>
                <span className="flxdn-tag">{t('Matched on phone or e-mail')}</span>
              </div>
              <div className="flxdn-field">
                <label htmlFor="flxdn-a-name">{t('Full name')}</label>
                <input type="text" id="flxdn-a-name" maxLength={190} value={name} onChange={(e) => setName(e.target.value)} />
              </div>
              <div className="flxdn-fields-2">
                <div className="flxdn-field">
                  <label htmlFor="flxdn-a-phone">{t('Phone')}</label>
                  <Phone id="flxdn-a-phone" k="donor" value={f.donor.phone} label={t('Phone')} />
                </div>
                <div className="flxdn-field">
                  <label htmlFor="flxdn-a-mail">{t('E-mail')}</label>
                  <input type="email" id="flxdn-a-mail" maxLength={190} value={email} onChange={(e) => setEmail(e.target.value)} />
                </div>
              </div>
              <div className="flxdn-chips flxdn-add2__consents">
                <Check checked={consent.kvkk} onChange={(v) => setConsent({ ...consent, kvkk: v })}>
                  {t('Privacy notice (KVKK) accepted')}
                </Check>
                <Check checked={consent.email} onChange={(v) => setConsent({ ...consent, email: v })}>
                  {t('E-mail allowed')}
                </Check>
                <Check checked={consent.sms} onChange={(v) => setConsent({ ...consent, sms: v })}>
                  {t('SMS allowed')}
                </Check>
              </div>
            </section>

            {!proxy ? (
              <section className="flxdn-card">
                <div className="flxdn-add2__head">
                  <h2 className="flxdn-card__title">{t('On behalf of')}</h2>
                  {on ? <span className="flxdn-tag">{t('%1$s / %2$s', (n * per).toLocaleString(), unitWord(n * per))}</span> : null}
                </div>
                <Check checked={forOther} onChange={setForOther}>
                  {t('This donation is on behalf of other people (one certificate each)')}
                </Check>
                {on ? (
                  <div className="flxdn-people">
                    <div>
                      {rows.map((p, i) => (
                        <div className="flxdn-ppl-row" key={p.id}>
                          <span className="flxdn-ppl-row__n">{i + 1}</span>
                          <div className="flxdn-ppl-row__f">
                            <input type="text" maxLength={80} placeholder={t('Full name')} aria-label={t('Full name')} value={p.name} onChange={(e) => setPerson(p.id, 'name', e.target.value)} />
                          </div>
                          <div className="flxdn-ppl-row__f">
                            <Phone k={'p-' + p.id} label={t('WhatsApp')} placeholder={t('WhatsApp')} onType={(v) => setPerson(p.id, 'phone', v.trim())} />
                          </div>
                          <div className="flxdn-ppl-row__f">
                            <input type="email" maxLength={190} placeholder={t('E-mail')} aria-label={t('E-mail')} value={p.email} onChange={(e) => setPerson(p.id, 'email', e.target.value)} />
                          </div>
                          <span className="flxdn-ppl-row__end">
                            <span className="flxdn-tag">{unitWord(share(i))}</span>
                            {i > 0 ? (
                              <button type="button" className="flxdn-ppl-row__x" aria-label={t('Remove')} onClick={() => setPeople((ps) => ps.filter((x) => x.id !== p.id))}>
                                ×
                              </button>
                            ) : null}
                          </span>
                        </div>
                      ))}
                    </div>
                    <div className="flxdn-people__foot">
                      {rows.length < n ? (
                        <button type="button" className="flxdn-btn flxdn-btn--sm" onClick={addPerson}>
                          {t('+ Add person')}
                        </button>
                      ) : null}
                      <p className="flxdn-help">{t('Each person gets one unit and their own certificate; the remaining units go to the first person. A certificate without WhatsApp or e-mail goes to the donor.')}</p>
                    </div>
                  </div>
                ) : null}
              </section>
            ) : null}
          </div>

          <aside className="flxdn-add2__side">
            <section className="flxdn-card">
              <div className="flxdn-add2__sum" aria-live="polite">
                <span className="flxdn-add2__sumlbl">{t('Total')}</span>
                <div className="flxdn-add2__total">{total > 0 ? money(total, cur) : '—'}</div>
                <dl className="flxdn-add2__line">
                  <dt>{sumType}</dt>
                  <dd>{o ? t('%1$s × %2$s', n.toLocaleString(), money(unit, cur)) : ''}</dd>
                </dl>
                {showCerts ? (
                  <ul className="flxdn-add2__certs">
                    {certs.map((c, i) => (
                      <li key={i}>
                        <span>{c.name}</span>
                        <small>{c.note}</small>
                      </li>
                    ))}
                  </ul>
                ) : null}
              </div>
              <div className="flxdn-field">
                <span className="flxdn-label">{t('Payment method')}</span>
                <Seg name="flxdn-method" value={method} opts={f.methods} onChange={setMethod} />
              </div>
              {f.accounts.length && method === 'bank_transfer' ? (
                <div className="flxdn-field">
                  <label htmlFor="flxdn-a-acc">{t('Account')}</label>
                  <select id="flxdn-a-acc" value={account} onChange={(e) => setAccount(e.target.value)}>
                    <option value="">—</option>
                    {f.accounts.map((a) => (
                      <option key={a.value} value={a.value}>
                        {a.label}
                      </option>
                    ))}
                  </select>
                </div>
              ) : null}
              <div className="flxdn-field">
                <span className="flxdn-label">{t('Status')}</span>
                <Seg
                  name="flxdn-status"
                  value={paid}
                  opts={[
                    { value: 'completed', label: t('Paid') },
                    { value: 'pending', label: t('Awaiting payment') },
                  ]}
                  onChange={setPaid}
                />
              </div>
              <div className="flxdn-field">
                <label htmlFor="flxdn-a-date">{t('Date')}</label>
                <input type="datetime-local" id="flxdn-a-date" value={date} onChange={(e) => setDate(e.target.value)} />
              </div>
              <div className="flxdn-field">
                <label htmlFor="flxdn-a-ref">{t('Receipt or transaction no.')}</label>
                <input type="text" id="flxdn-a-ref" maxLength={120} placeholder={t('Optional')} value={reference} onChange={(e) => setReference(e.target.value)} />
              </div>
              <details className="flxdn-add2__extra">
                <summary>{f.referrals.length ? t('+ Add referral and note') : t('+ Add note')}</summary>
                {f.referrals.length ? (
                  <div className="flxdn-field">
                    <label htmlFor="flxdn-a-refuser">{t('Referral')}</label>
                    <select id="flxdn-a-refuser" value={ref} onChange={(e) => setRef(e.target.value)}>
                      <option value="0">{t('None')}</option>
                      {f.referrals.map((r) => (
                        <option key={r.value} value={r.value}>
                          {r.label}
                        </option>
                      ))}
                    </select>
                    <p className="flxdn-help">{t('The staff member who brought this donation. They see it under their referrals.')}</p>
                  </div>
                ) : null}
                <div className="flxdn-field">
                  <label htmlFor="flxdn-a-note">{t('Notes')}</label>
                  <textarea id="flxdn-a-note" rows={3} value={note} onChange={(e) => setNote(e.target.value)} />
                </div>
              </details>
              <button type="submit" className="flxdn-btn flxdn-btn--primary flxdn-add2__save" disabled={save.isPending}>
                {t('Save donation')}
              </button>
            </section>
          </aside>
        </div>
      </form>
    </Screen>
  );
}

function Request({ f, notice }: { f: F; notice: ReactNode }) {
  const host = useHost();
  const { t, lang, told } = useDonate();
  const money = useMoney(lang);
  const root = useRef<HTMLFormElement>(null);
  const [tid, setTid] = useState(String(f.types[0]?.databaseId ?? 0));
  const d = f.types.find((x) => String(x.databaseId) === tid) ?? null;
  const [okey, setOkey] = useState(d?.options[0]?.key ?? NONE);
  const [qty, setQty] = useState(1);
  const [amount, setAmount] = useState(NONE);
  const [ben, setBen] = useState(NONE);
  const [name, setName] = useState(NONE);
  const [email, setEmail] = useState(NONE);
  const [mail, setMail] = useState(true);
  const [note, setNote] = useState(NONE);
  const [done, setDone] = useState<{ payUrl: string; whatsappUrl: string; emailed: boolean } | null>(null);
  const [copied, setCopied] = useState(false);
  const [round, setRound] = useState(0);
  const opts = d ? d.options : [];
  const pickType = (v: string) => {
    setTid(v);
    setOkey(f.types.find((x) => String(x.databaseId) === v)?.options[0]?.key ?? NONE);
  };

  const send = useFlxMutation(FlxDonationRequest, {
    onSuccess: (r) => {
      const res = r.flxDonationRequest;
      const err = res?.errors?.[0]?.message;
      if (err || !res?.payUrl) {
        told({ kind: 'error', text: err || t('The action could not be completed.') });
        return;
      }
      told(null);
      setCopied(false);
      setDone({ payUrl: res.payUrl, whatsappUrl: res.whatsappUrl ?? '', emailed: !!res.emailed });
      // A clean form for the next request (the phone field is redrawn with it).
      setQty(1);
      setAmount(NONE);
      setBen(NONE);
      setName(NONE);
      setEmail(NONE);
      setNote(NONE);
      setRound((x) => x + 1);
      window.scrollTo?.({ top: 0, behavior: 'smooth' });
    },
    onError: (e) => told({ kind: 'error', text: e.message || t('The action could not be completed.') }),
  });
  const submit = (e: FormEvent) => {
    e.preventDefault();
    send.mutate({
      input: {
        typeId: d ? d.databaseId : 0,
        optionKey: okey,
        quantity: qty,
        amount: okey ? null : numOf(amount),
        beneficiaries: ben,
        donor: { name, phone: phoneOf(root.current, 'donor'), email },
        sendEmail: mail,
        note,
      },
    });
  };
  const copy = () => {
    void navigator.clipboard?.writeText(done?.payUrl ?? '');
    setCopied(true);
  };

  return (
    <Screen
      className="flxdn"
      width="wide"
      icon={host.appIcon}
      title={t('New donation request')}
      subtitle={t('Open a donation on behalf of a donor. The donor gets a payment link and you are the referral.')}
      actions={<Back f={f} />}
      notice={notice}
    >
      {done ? (
        <section className="flxdn-card flxdn-reqdone">
          <div className="flxdn-card__head">
            <h2 className="flxdn-card__title">{t('Request created')}</h2>
          </div>
          <div className="flxdn-form">
            <div className="flxdn-field">
              <label htmlFor="flxdn-req-url">{t('Payment link')}</label>
              <input type="text" id="flxdn-req-url" className="flxdn-input code" readOnly value={done.payUrl} onFocus={(e) => e.target.select()} />
            </div>
            <div className="flxdn-formactions">
              <button type="button" className="flxdn-btn" onClick={copy}>
                {copied ? t('Copied') : t('Copy link')}
              </button>
              {done.whatsappUrl ? (
                <a className="flxdn-btn flxdn-btn--primary" target="_blank" rel="noopener noreferrer" href={done.whatsappUrl}>
                  {t('Send on WhatsApp')}
                </a>
              ) : null}
            </div>
            {done.emailed ? <p className="flxdn-help">{t('The payment link was also e-mailed to the donor.')}</p> : null}
          </div>
        </section>
      ) : null}
      {!f.types.length ? (
        <div className="flxdn-panel">
          <p className="flxdn-empty">{t('There is no open donation type yet.')}</p>
        </div>
      ) : (
        <form ref={root} key={round} className="flxdn-form flxdn-addform" onSubmit={submit}>
          <div className="flxdn-grid flxdn-grid--2-1">
            <div className="flxdn-stack">
              <section className="flxdn-card">
                <div className="flxdn-card__head">
                  <h2 className="flxdn-card__title">{t('Donation')}</h2>
                </div>
                <div className="flxdn-form">
                  <div className="flxdn-fields-2">
                    <div className="flxdn-field">
                      <label htmlFor="flxdn-r-type">{t('Donation type')}</label>
                      <select id="flxdn-r-type" value={tid} onChange={(e) => pickType(e.target.value)}>
                        {f.types.map((ty) => (
                          <option key={ty.databaseId} value={String(ty.databaseId)}>
                            {ty.title}
                          </option>
                        ))}
                      </select>
                    </div>
                    <div className="flxdn-field">
                      <label htmlFor="flxdn-r-opt">{t('Option')}</label>
                      <select id="flxdn-r-opt" value={okey} onChange={(e) => setOkey(e.target.value)}>
                        {!d || d.free || !opts.length ? <option value="">{t('Free amount')}</option> : null}
                        {opts.map((o) => (
                          <option key={o.key} value={o.key}>
                            {o.label + (o.price > 0 && d ? ' — ' + money(o.price, d.currency) : '')}
                          </option>
                        ))}
                      </select>
                    </div>
                  </div>
                  <div className="flxdn-fields-2">
                    <div className="flxdn-field">
                      <label htmlFor="flxdn-r-qty">{t('Quantity')}</label>
                      <Stepper id="flxdn-r-qty" value={qty} onChange={setQty} />
                    </div>
                    <div className="flxdn-field">
                      <label htmlFor="flxdn-r-amount">{t('Amount')}</label>
                      <input type="number" id="flxdn-r-amount" min={0} step="0.01" value={amount} disabled={!!okey} onChange={(e) => setAmount(e.target.value)} />
                      <p className="flxdn-help">{t('Only for a free amount.')}</p>
                    </div>
                  </div>
                  <div className="flxdn-field">
                    <label htmlFor="flxdn-r-ben">{t('On behalf of')}</label>
                    <textarea id="flxdn-r-ben" rows={3} value={ben} onChange={(e) => setBen(e.target.value)} />
                    <p className="flxdn-help">{t("One name per line, one per unit; this name goes on the certificate. Empty = the donor's name.")}</p>
                  </div>
                </div>
              </section>
              <section className="flxdn-card">
                <div className="flxdn-card__head">
                  <h2 className="flxdn-card__title">{t('Donor')}</h2>
                </div>
                <div className="flxdn-form">
                  <div className="flxdn-field">
                    <label htmlFor="flxdn-r-name">{t('Name')}</label>
                    <input type="text" id="flxdn-r-name" maxLength={190} required value={name} onChange={(e) => setName(e.target.value)} />
                  </div>
                  <div className="flxdn-fields-2">
                    <div className="flxdn-field">
                      <label htmlFor="flxdn-r-phone">{t('Phone')}</label>
                      <Phone id="flxdn-r-phone" k="donor" label={t('Phone')} />
                    </div>
                    <div className="flxdn-field">
                      <label htmlFor="flxdn-r-mail">{t('E-mail')}</label>
                      <input type="email" id="flxdn-r-mail" maxLength={190} value={email} onChange={(e) => setEmail(e.target.value)} />
                    </div>
                  </div>
                  <p className="flxdn-help">{t('Phone or e-mail is required: the payment link goes there.')}</p>
                  <Check checked={mail} onChange={setMail}>
                    {t('E-mail the payment link to the donor')}
                  </Check>
                </div>
              </section>
            </div>
            <div className="flxdn-stack">
              <section className="flxdn-card">
                <div className="flxdn-form">
                  <div className="flxdn-field">
                    <label htmlFor="flxdn-r-note">{t('Notes')}</label>
                    <textarea id="flxdn-r-note" rows={3} value={note} onChange={(e) => setNote(e.target.value)} />
                  </div>
                  <p className="flxdn-help">{t('The donor pays by card or bank transfer on the payment page. You are told when the donation is paid or cancelled.')}</p>
                  <div className="flxdn-formactions">
                    <button type="submit" className="flxdn-btn flxdn-btn--primary" disabled={send.isPending}>
                      {t('Create request')}
                    </button>
                  </div>
                </div>
              </section>
            </div>
          </div>
        </form>
      )}
    </Screen>
  );
}
