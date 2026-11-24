// Flux POS. Same screen in wp-admin (wp-embed) and the FluxWP app; every read and write goes through the
// Flux POS GraphQL operations (plugins/flx-pos/graphql/operations). It is the only admin page:
//   Payment methods: every WooCommerce payment method (any plugin): switch, drag to sort, the one-card
//                    rule, what each can do, and a preview of the checkout payment step.
//   POS accounts:    pick a bank / payment institution, enter its keys (secrets are write-only), test.
//   Transactions:    filters, refund.
//   Settings:        card method title, routes per currency, display.
import { useEffect, useMemo, useRef, useState, type DragEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { useQueryClient } from '@tanstack/react-query';
import { createGraphQLClient } from '@fluxwp/graphql';
import { FlxGraphQLProvider, flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import {
  FlxPosAccountDelete,
  FlxPosAccounts,
  FlxPosAccountSave,
  FlxPosAccountTest,
  FlxPosMethods,
  FlxPosMethodsUpdate,
  FlxPosSettingsUpdate,
  FlxPosTxnRefund,
  FlxPosTxns,
} from '@fluxwp/graphql/operations';
import type { FlxPosAccountsQuery, FlxPosMethodsQuery, FlxPosProviderGroup, FlxPosTxnStatus, FlxPosTxnsQuery } from '@fluxwp/graphql/types';
import { Check, Icon, Loading, Modal, Screen } from '@fluxwp/ui';
import { useHost } from '../host';
import { translator, type Translate } from './i18n';
import './pos.css';

type Methods = FlxPosMethodsQuery['flxPosMethods'];
type Method = Methods['methods'][number];
type AccountsData = FlxPosAccountsQuery;
type Account = AccountsData['flxPosAccounts'][number];
type Provider = AccountsData['flxPosProviders'][number];
type Settings = AccountsData['flxPosSettings'];
type Txn = FlxPosTxnsQuery['flxPosTxns']['nodes'][number];
type Tab = 'methods' | 'accounts' | 'txns' | 'settings';
type Notice = { kind: 'ok' | 'warn' | 'bad'; text: string };

const TABS: Tab[] = ['methods', 'accounts', 'txns', 'settings'];
const GROUPS: FlxPosProviderGroup[] = ['BANK', 'PSP', 'WALLET', 'MEAL'];
const STATUSES: FlxPosTxnStatus[] = ['PAID', 'PENDING', 'FAILED', 'REFUNDED', 'PARTIAL', 'VOIDED', 'REVIEW'];
const msg = (e: unknown, t: Translate) => (e instanceof Error && e.message ? e.message : t('The action could not be completed.'));

export function PosScreen() {
  const host = useHost();
  const client = useMemo(() => createGraphQLClient({ api: host.client }), [host.client]);
  return (
    <FlxGraphQLProvider client={client} scope={host.scope}>
      <Pos />
    </FlxGraphQLProvider>
  );
}

function Pos() {
  const host = useHost();
  const { i18n } = useTranslation();
  const { t } = useMemo(() => translator(i18n.language), [i18n.language]);
  const start = (TABS as string[]).includes(host.startPage ?? '') ? (host.startPage as Tab) : 'methods';
  const [tab, setTab] = useState<Tab>(start);
  const [notice, setNotice] = useState<Notice | null>(null);
  const say = (n: Notice | null) => {
    setNotice(n);
    if (n) window.scrollTo?.({ top: 0, behavior: 'smooth' });
  };
  const titles: Record<Tab, string> = { methods: t('Payment methods'), accounts: t('POS accounts'), txns: t('Transactions'), settings: t('Settings') };
  return (
    <Screen
      title={t('Virtual POS')}
      subtitle={t('Every payment method of the site, bank and payment institution accounts, card transactions.')}
      icon={host.appIcon}
      width="wide"
      className="flxpos-app"
      tabs={TABS.map((id) => ({ id, label: titles[id] }))}
      tab={tab}
      onTab={(id) => {
        setTab(id);
        setNotice(null);
      }}
      notice={
        notice && (
          <div className={'flxpos-notice is-' + notice.kind} role="status">
            <span>{notice.text}</span>
            <button type="button" className="flxpos-x" aria-label={t('Close')} onClick={() => setNotice(null)}>
              ×
            </button>
          </div>
        )
      }
    >
      {tab === 'methods' && <MethodsTab t={t} say={say} />}
      {tab === 'accounts' && <AccountsTab t={t} say={say} />}
      {tab === 'txns' && <TxnsTab t={t} say={say} />}
      {tab === 'settings' && <SettingsTab t={t} say={say} />}
    </Screen>
  );
}

function Failed({ t, error }: { t: Translate; error: unknown }) {
  return <div className="flxpos-notice is-bad">{msg(error, t)}</div>;
}

/* ------------------------------------------------------------------ */
/* Payment methods                                                     */
/* ------------------------------------------------------------------ */

function MethodsTab({ t, say }: { t: Translate; say: (n: Notice | null) => void }) {
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const q = useFlxQuery(FlxPosMethods, {});
  const [order, setOrder] = useState<string[] | null>(null);
  const [on, setOn] = useState<Record<string, boolean>>({});
  const [card, setCard] = useState<string | null>(null);
  const [drag, setDrag] = useState<string | null>(null);
  const [pick, setPick] = useState<string | null>(null);
  const save = useFlxMutation(FlxPosMethodsUpdate, {
    onSuccess: (r) => {
      const p = r.flxPosMethodsUpdate;
      if (!p) return say({ kind: 'bad', text: t('The action could not be completed.') });
      if (p.errors.length) return say({ kind: 'bad', text: p.errors.map((e) => e.message).join(' ') });
      qc.setQueryData<FlxPosMethodsQuery>(flxQueryKey(scope, FlxPosMethods, {}), { flxPosMethods: p.methods });
      setOrder(null);
      setOn({});
      setCard(null);
      say({ kind: 'ok', text: t('Saved. The checkout shows the methods in this order.') });
    },
    onError: (e) => say({ kind: 'bad', text: msg(e, t) }),
  });

  if (q.isLoading) return <Loading />;
  if (q.isError || !q.data) return <Failed t={t} error={q.error} />;
  const d = q.data.flxPosMethods;
  const byId = new Map(d.methods.map((m) => [m.id, m]));
  const ids = (order ?? d.methods.map((m) => m.id)).filter((id) => byId.has(id));
  const list = ids.map((id) => byId.get(id) as Method);
  const enabled = (m: Method) => on[m.id] ?? m.enabled;
  const dirty = order !== null || Object.keys(on).length > 0 || card !== null;

  const move = (id: string, to: number) => {
    const next = ids.filter((x) => x !== id);
    next.splice(Math.max(0, Math.min(to, next.length)), 0, id);
    setOrder(next);
  };
  const onDrop = (e: DragEvent, target: string) => {
    e.preventDefault();
    if (drag && drag !== target) move(drag, ids.indexOf(target));
    setDrag(null);
  };
  const submit = () =>
    save.mutate({
      input: {
        order: ids,
        switches: Object.entries(on).map(([id, enabled]) => ({ id, enabled })),
        ...(card !== null ? { cardMethod: card } : {}),
      },
    });

  const preview = list.filter((m) => enabled(m) && !m.hidden);
  const chosen = preview.find((m) => m.id === pick) ?? preview[0];

  return (
    <div className="flxpos-grid">
      <section className="flxpos-card flxpos-span-2">
        <div className="flxpos-card__head">
          <h2>
            <Icon name="credit-card" size={16} />
            {t('Payment methods')}
          </h2>
          <span className="flxpos-muted">{t('Drag to change the checkout order. Methods of any plugin are listed.')}</span>
        </div>
        {d.blockCheckout && <p className="flxpos-muted">{t('The checkout page uses the WooCommerce checkout block.')}</p>}
        <ul className="flxpos-methods">
          {list.map((m, i) => (
            <li
              key={m.id}
              className={'flxpos-method' + (drag === m.id ? ' is-drag' : '') + (enabled(m) ? '' : ' is-off')}
              draggable
              onDragStart={(e) => {
                setDrag(m.id);
                e.dataTransfer.effectAllowed = 'move';
              }}
              onDragOver={(e) => e.preventDefault()}
              onDrop={(e) => onDrop(e, m.id)}
              onDragEnd={() => setDrag(null)}
            >
              <span className="flxpos-grip" aria-hidden="true">
                ⋮⋮
              </span>
              <div className="flxpos-method__body">
                <div className="flxpos-method__title">
                  <strong>{m.title || m.methodTitle}</strong>
                  {m.title && m.methodTitle && m.title !== m.methodTitle && <small>{m.methodTitle}</small>}
                  {m.flux && <span className="flxpos-badge is-ok">FluxWP</span>}
                  {m.offline && <span className="flxpos-badge">{t('offline')}</span>}
                  {m.hidden && enabled(m) && (
                    <span className="flxpos-badge is-warn" title={t('Another card method is shown at checkout (one-card rule).')}>
                      {t('hidden by the card rule')}
                    </span>
                  )}
                </div>
                {m.caps.length > 0 && (
                  <div className="flxpos-caps">
                    {m.caps.map((c) => (
                      <span key={c.key} className={'flxpos-cap is-' + c.state.toLowerCase()} title={[c.help, c.note].filter(Boolean).join(' · ')}>
                        {c.label}
                      </span>
                    ))}
                  </div>
                )}
              </div>
              <div className="flxpos-method__tools">
                <button type="button" className="flxpos-icon-btn" disabled={i === 0} aria-label={t('Move up')} onClick={() => move(m.id, i - 1)}>
                  ↑
                </button>
                <button type="button" className="flxpos-icon-btn" disabled={i === list.length - 1} aria-label={t('Move down')} onClick={() => move(m.id, i + 1)}>
                  ↓
                </button>
                {m.settingsUrl && (
                  <a className="flxpos-icon-btn" href={m.settingsUrl} aria-label={t('Settings')} title={t('Settings')}>
                    <Icon name="settings" size={14} />
                  </a>
                )}
                <Check switch checked={enabled(m)} onChange={(v) => setOn({ ...on, [m.id]: v })} aria-label={m.title || m.methodTitle} />
              </div>
            </li>
          ))}
        </ul>
        {d.cardChoices.length > 1 && (
          <label className="flxpos-field flxpos-field--inline">
            <span>{t('Card payments at checkout')}</span>
            <select value={card ?? d.cardMethod} onChange={(e) => setCard(e.currentTarget.value)}>
              {d.cardChoices.map((o) => (
                <option key={o.value} value={o.value}>
                  {o.label}
                </option>
              ))}
            </select>
            <small>
              {d.cardMethodOwner === 'donate'
                ? t('Flux Donate rule: only one card method is shown so donors do not see two card forms.')
                : t('Only one card method is shown so customers do not see two card forms.')}
            </small>
          </label>
        )}
        <div className="flxpos-actions">
          <button type="button" className="flxpos-btn is-primary is-large" disabled={!dirty || save.isPending} onClick={submit}>
            {t('Save')}
          </button>
          {dirty && (
            <>
              <span className="flxpos-dirty">{t('Unsaved changes')}</span>
              <button
                type="button"
                className="flxpos-btn"
                onClick={() => {
                  setOrder(null);
                  setOn({});
                  setCard(null);
                }}
              >
                {t('Undo')}
              </button>
            </>
          )}
        </div>
      </section>

      <aside className="flxpos-card flxpos-preview" aria-label={t('Preview')}>
        <div className="flxpos-card__head">
          <h2>
            <Icon name="visibility" size={16} />
            {t('Preview')}
          </h2>
          {d.checkoutUrl && (
            <a href={d.checkoutUrl} target="_blank" rel="noopener">
              {t('Open checkout')}
            </a>
          )}
        </div>
        <p className="flxpos-muted">{t('How the payment step looks at checkout (with unsaved changes).')}</p>
        <div className="flxpos-pv">
          <div className="flxpos-pv__title">{t('Payment')}</div>
          {preview.length === 0 && <p className="flxpos-muted">{t('No payment method is on. Customers cannot pay.')}</p>}
          {preview.map((m) => (
            <label key={m.id} className={'flxpos-pv__row' + (chosen?.id === m.id ? ' is-on' : '')}>
              <input type="radio" name="flxpos-pv" checked={chosen?.id === m.id} onChange={() => setPick(m.id)} />
              <span className="flxpos-pv__name">{m.title || m.methodTitle}</span>
              <span className="flxpos-pv__icons">
                {m.icons.slice(0, 4).map((src) => (
                  <img key={src} src={src} alt="" loading="lazy" />
                ))}
              </span>
              {chosen?.id === m.id && (m.description || m.hasFields) && (
                <span className="flxpos-pv__desc">
                  {m.description}
                  {m.hasFields && (
                    <span className="flxpos-pv__fields" aria-hidden="true">
                      <i />
                      <i />
                      <i />
                    </span>
                  )}
                </span>
              )}
            </label>
          ))}
          <div className="flxpos-pv__btn">{t('Place order')}</div>
        </div>
      </aside>
    </div>
  );
}

/* ------------------------------------------------------------------ */
/* POS accounts                                                        */
/* ------------------------------------------------------------------ */

interface Draft {
  id: string;
  provider: string;
  label: string;
  enabled: boolean;
  test: boolean;
  mode: string;
  creds: Record<string, string>;
  currencies: string[];
  installments: { count: number; rate: string }[];
  instMin: string;
  title: string;
  display: string;
}

const draftOf = (a: Account | null, p: Provider): Draft => ({
  id: a?.id ?? '',
  provider: p.id,
  label: a?.label ?? '',
  enabled: a ? a.enabled : true,
  test: a ? a.test : true,
  mode: a?.mode || p.modes[0]?.value || '',
  creds: Object.fromEntries([...p.fields.map((f) => [f.key, f.secret ? '' : a?.creds.find((c) => c.key === f.key)?.value ?? f.defaultValue] as const), ...(a?.creds.filter((c) => c.key.startsWith('url_')).map((c) => [c.key, c.value] as const) ?? [])]),
  currencies: a?.currencies ?? [],
  installments: (a?.installments ?? []).map((i) => ({ count: i.count, rate: String(i.rate) })),
  instMin: a && a.instMin ? String(a.instMin) : '',
  title: a?.title ?? '',
  display: a?.display ?? '',
});

function AccountsTab({ t, say }: { t: Translate; say: (n: Notice | null) => void }) {
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const q = useFlxQuery(FlxPosAccounts, {});
  const [picking, setPicking] = useState(false);
  const [edit, setEdit] = useState<{ account: Account | null; provider: Provider } | null>(null);
  const [del, setDel] = useState<Account | null>(null);
  const [tests, setTests] = useState<Record<string, { ok: boolean; text: string } | 'busy'>>({});
  const put = (accounts: Account[]) => {
    const old = qc.getQueryData<AccountsData>(flxQueryKey(scope, FlxPosAccounts, {}));
    if (old) qc.setQueryData<AccountsData>(flxQueryKey(scope, FlxPosAccounts, {}), { ...old, flxPosAccounts: accounts });
    void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxPosMethods, {}) });
  };
  const remove = useFlxMutation(FlxPosAccountDelete, {
    onSuccess: (r) => {
      const p = r.flxPosAccountDelete;
      if (!p || p.errors.length) return say({ kind: 'bad', text: p?.errors.map((e) => e.message).join(' ') || t('The action could not be completed.') });
      put(p.accounts);
      setDel(null);
      say({ kind: 'ok', text: t('The POS account was deleted.') });
    },
    onError: (e) => say({ kind: 'bad', text: msg(e, t) }),
  });
  const test = useFlxMutation(FlxPosAccountTest);
  const runTest = (a: Account) => {
    setTests((o) => ({ ...o, [a.id]: 'busy' }));
    test.mutate(
      { input: { id: a.id } },
      {
        onSuccess: (r) => {
          const p = r.flxPosAccountTest;
          const res = p?.result;
          setTests((o) => ({ ...o, [a.id]: res ? { ok: res.ok, text: res.message || (res.ok ? t('Connection works.') : t('Connection failed.')) } : { ok: false, text: p?.errors[0]?.message || t('The action could not be completed.') } }));
        },
        onError: (e) => setTests((o) => ({ ...o, [a.id]: { ok: false, text: msg(e, t) } })),
      },
    );
  };

  if (q.isLoading) return <Loading />;
  if (q.isError || !q.data) return <Failed t={t} error={q.error} />;
  const d = q.data;
  const prov = new Map(d.flxPosProviders.map((p) => [p.id, p]));

  return (
    <>
      <section className="flxpos-card">
        <div className="flxpos-card__head">
          <h2>
            <Icon name="bank" size={16} />
            {t('POS accounts')}
          </h2>
          <button type="button" className="flxpos-btn is-primary" onClick={() => setPicking(true)}>
            <Icon name="plus-alt" size={14} />
            {t('Add POS')}
          </button>
        </div>
        {d.flxPosAccounts.length === 0 ? (
          <div className="flxpos-empty">
            <p>{t('No POS account yet. Pick your bank or payment institution and enter the keys it gave you.')}</p>
            <button type="button" className="flxpos-btn is-primary is-large" onClick={() => setPicking(true)}>
              {t('Add POS')}
            </button>
          </div>
        ) : (
          <ul className="flxpos-accounts">
            {d.flxPosAccounts.map((a) => {
              const p = prov.get(a.provider);
              const r = tests[a.id];
              const missing = a.creds.filter((c) => p?.fields.some((f) => f.key === c.key && f.secret) && !c.isSet).length;
              return (
                <li key={a.id} className={'flxpos-account' + (a.enabled ? '' : ' is-off')}>
                  <div className="flxpos-account__main">
                    <strong>{a.name}</strong>
                    <span className="flxpos-muted">{a.providerName}</span>
                    <div className="flxpos-badges">
                      <span className={'flxpos-badge ' + (a.enabled ? 'is-ok' : '')}>{a.enabled ? t('on') : t('off')}</span>
                      <span className={'flxpos-badge ' + (a.test ? 'is-warn' : 'is-ok')}>{a.test ? t('test mode') : t('live')}</span>
                      {a.takesCard && <span className="flxpos-badge">{t('card form')}</span>}
                      {missing > 0 && <span className="flxpos-badge is-bad">{t('%d keys missing', missing)}</span>}
                    </div>
                    {r && r !== 'busy' && <p className={'flxpos-test ' + (r.ok ? 'is-ok' : 'is-bad')}>{r.text}</p>}
                  </div>
                  <div className="flxpos-account__tools">
                    <button type="button" className="flxpos-btn" disabled={r === 'busy'} onClick={() => runTest(a)}>
                      {r === 'busy' ? t('Testing…') : t('Test connection')}
                    </button>
                    <button type="button" className="flxpos-btn" disabled={!p} onClick={() => p && setEdit({ account: a, provider: p })}>
                      {t('Edit')}
                    </button>
                    <button type="button" className="flxpos-btn is-danger" onClick={() => setDel(a)}>
                      {t('Delete')}
                    </button>
                  </div>
                </li>
              );
            })}
          </ul>
        )}
        <p className="flxpos-muted flxpos-note">
          <Icon name="info" size={14} />
          {t('Keys are stored encrypted and never shown again; leave a key empty to keep the saved one. Try every account in test mode before going live.')}
        </p>
      </section>
      {picking && (
        <ProviderPicker
          t={t}
          providers={d.flxPosProviders}
          onClose={() => setPicking(false)}
          onPick={(p) => {
            setPicking(false);
            setEdit({ account: null, provider: p });
          }}
        />
      )}
      {edit && (
        <AccountEditor
          t={t}
          account={edit.account}
          provider={edit.provider}
          settings={d.flxPosSettings}
          onClose={() => setEdit(null)}
          onSaved={(accounts, isNew) => {
            put(accounts);
            setEdit(null);
            say({ kind: 'ok', text: isNew ? t('The POS account was added. Test the connection before going live.') : t('Saved.') });
          }}
        />
      )}
      {del && (
        <Modal title={t('Delete the POS account?')} onClose={() => setDel(null)}>
          <p>{t('%s will be deleted with its keys. Its transactions stay in the list.', del.name)}</p>
          <div className="flxpos-actions">
            <button type="button" className="flxpos-btn is-danger is-large" disabled={remove.isPending} onClick={() => remove.mutate({ input: { id: del.id } })}>
              {t('Delete')}
            </button>
            <button type="button" className="flxpos-btn is-large" onClick={() => setDel(null)}>
              {t('Cancel')}
            </button>
          </div>
        </Modal>
      )}
    </>
  );
}

function ProviderPicker({ t, providers, onPick, onClose }: { t: Translate; providers: Provider[]; onPick: (p: Provider) => void; onClose: () => void }) {
  const [s, setS] = useState('');
  const box = useRef<HTMLInputElement | null>(null);
  useEffect(() => box.current?.focus(), []);
  const groups: Record<FlxPosProviderGroup, string> = { BANK: t('Banks'), PSP: t('Payment institutions'), WALLET: t('Wallets and other methods'), MEAL: t('Meal and gift cards') };
  const norm = (x: string) => x.toLocaleLowerCase('tr').normalize('NFD').replace(/[̀-ͯ]/g, '');
  const hit = providers.filter((p) => norm(p.name + ' ' + p.id).includes(norm(s.trim())));
  return (
    <Modal title={t('Pick a provider')} onClose={onClose} wide>
      <input ref={box} type="search" className="flxpos-search" placeholder={t('Search: bank or company name')} value={s} onChange={(e) => setS(e.currentTarget.value)} />
      {GROUPS.map((g) => {
        const rows = hit.filter((p) => p.group === g);
        if (!rows.length) return null;
        return (
          <div key={g}>
            <h3 className="flxpos-sub">{groups[g]}</h3>
            <div className="flxpos-providers">
              {rows.map((p) => (
                <button key={p.id} type="button" className="flxpos-provider" onClick={() => onPick(p)}>
                  <strong>{p.name}</strong>
                  <small>{p.currencies.slice(0, 4).join(' · ')}</small>
                  {!p.ready && <span className="flxpos-badge is-warn">{t('needs a live test')}</span>}
                </button>
              ))}
            </div>
          </div>
        );
      })}
      {hit.length === 0 && <p className="flxpos-muted">{t('Nothing found.')}</p>}
    </Modal>
  );
}

function AccountEditor({
  t,
  account,
  provider: p,
  settings,
  onClose,
  onSaved,
}: {
  t: Translate;
  account: Account | null;
  provider: Provider;
  settings: Settings;
  onClose: () => void;
  onSaved: (accounts: Account[], isNew: boolean) => void;
}) {
  const [d, setD] = useState<Draft>(() => draftOf(account, p));
  const [err, setErr] = useState('');
  const set = (patch: Partial<Draft>) => setD((o) => ({ ...o, ...patch }));
  const save = useFlxMutation(FlxPosAccountSave, {
    onSuccess: (r) => {
      const x = r.flxPosAccountSave;
      if (!x) return setErr(t('The action could not be completed.'));
      if (x.errors.length) return setErr(x.errors.map((e) => e.message).join(' '));
      onSaved(x.accounts, !account);
    },
    onError: (e) => setErr(msg(e, t)),
  });
  const submit = () => {
    setErr('');
    save.mutate({
      input: {
        ...(d.id ? { id: d.id } : {}),
        provider: d.provider,
        label: d.label.trim(),
        enabled: d.enabled,
        test: d.test,
        mode: d.mode,
        creds: Object.entries(d.creds).map(([key, value]) => ({ key, value: value.trim() })),
        currencies: d.currencies,
        installments: d.installments.filter((i) => i.count >= 2 && i.rate.trim() !== '').map((i) => ({ count: i.count, rate: Number(i.rate.replace(',', '.')) || 0 })),
        instMin: Number(d.instMin.replace(',', '.')) || 0,
        title: d.title.trim(),
        display: d.display,
      },
    });
  };
  const saved = (key: string) => account?.creds.find((c) => c.key === key);
  const cardCaps = p.caps.includes('installment');
  return (
    <Modal title={(account ? t('Edit POS account') : t('New POS account')) + ' · ' + p.name} onClose={onClose} wide>
      <div className="flxpos-form">
        {p.notes && <p className="flxpos-callout">{p.notes}</p>}
        <div className="flxpos-fields">
          <label className="flxpos-field">
            <span>{t('Name in the panel')}</span>
            <input type="text" value={d.label} placeholder={p.name} onChange={(e) => set({ label: e.currentTarget.value })} />
          </label>
          {p.modes.length > 1 && (
            <label className="flxpos-field">
              <span>{t('Payment model')}</span>
              <select value={d.mode} onChange={(e) => set({ mode: e.currentTarget.value })}>
                {p.modes.map((m) => (
                  <option key={m.value} value={m.value}>
                    {m.label}
                  </option>
                ))}
              </select>
            </label>
          )}
          {p.fields.map((f) => {
            const sv = saved(f.key);
            return (
              <label key={f.key} className="flxpos-field">
                <span>{f.label}</span>
                <input
                  type={f.secret ? 'password' : 'text'}
                  className="code"
                  autoComplete={f.secret ? 'new-password' : 'off'}
                  value={d.creds[f.key] ?? ''}
                  placeholder={f.secret && sv?.isSet ? t('Saved (%s). Leave empty to keep it.', sv.mask) : ''}
                  onChange={(e) => set({ creds: { ...d.creds, [f.key]: e.currentTarget.value } })}
                />
                {f.help && <small>{f.help}</small>}
              </label>
            );
          })}
        </div>
        <div className="flxpos-checks">
          <Check checked={d.enabled} onChange={(v) => set({ enabled: v })} note={t('Customers can pay with this account.')}>
            {t('On')}
          </Check>
          <Check checked={d.test} onChange={(v) => set({ test: v })} note={t('Uses the provider test server; no real money moves.')}>
            {t('Test mode')}
          </Check>
        </div>
        {p.currencies.length > 1 && (
          <fieldset className="flxpos-currencies">
            <legend>{t('Currencies (none chosen = all)')}</legend>
            {p.currencies.map((c) => (
              <Check key={c} checked={d.currencies.includes(c)} onChange={(v) => set({ currencies: v ? [...d.currencies, c] : d.currencies.filter((x) => x !== c) })}>
                {c}
              </Check>
            ))}
          </fieldset>
        )}
        {cardCaps && (
          <div className="flxpos-inst">
            <h3 className="flxpos-sub">{t('Installments')}</h3>
            <p className="flxpos-muted">{t('Extra charge in percent for each installment count. Empty table = single payment only.')}</p>
            {d.installments.map((row, i) => (
              <div key={i} className="flxpos-inst__row">
                <input type="number" min={2} max={36} value={row.count} aria-label={t('Installment count')} onChange={(e) => set({ installments: d.installments.map((x, j) => (j === i ? { ...x, count: Number(e.currentTarget.value) } : x)) })} />
                <span>{t('installments')}</span>
                <input type="text" inputMode="decimal" value={row.rate} aria-label={t('Extra charge (%)')} onChange={(e) => set({ installments: d.installments.map((x, j) => (j === i ? { ...x, rate: e.currentTarget.value } : x)) })} />
                <span>%</span>
                <button type="button" className="flxpos-icon-btn" aria-label={t('Remove')} onClick={() => set({ installments: d.installments.filter((_x, j) => j !== i) })}>
                  ×
                </button>
              </div>
            ))}
            <button type="button" className="flxpos-btn" onClick={() => set({ installments: [...d.installments, { count: (d.installments.at(-1)?.count ?? 1) + 1, rate: '0' }] })}>
              {t('Add installment')}
            </button>
            <label className="flxpos-field flxpos-field--inline">
              <span>{t('Smallest amount with installments')}</span>
              <input type="text" inputMode="decimal" value={d.instMin} onChange={(e) => set({ instMin: e.currentTarget.value })} />
            </label>
          </div>
        )}
        <div className="flxpos-fields">
          <label className="flxpos-field">
            <span>{t('Name at checkout (optional)')}</span>
            <input type="text" value={d.title} placeholder={settings.cardTitle} onChange={(e) => set({ title: e.currentTarget.value })} />
          </label>
          <label className="flxpos-field">
            <span>{t('Payment page')}</span>
            <select value={d.display} onChange={(e) => set({ display: e.currentTarget.value })}>
              <option value="">{t('General setting')}</option>
              {settings.displays.map((o) => (
                <option key={o.value} value={o.value}>
                  {o.label}
                </option>
              ))}
            </select>
          </label>
        </div>
        {account?.notifyUrl && (
          <label className="flxpos-field">
            <span>{t('Notification address (give it to the provider if it asks)')}</span>
            <input type="text" className="code" readOnly value={account.notifyUrl} onFocus={(e) => e.currentTarget.select()} />
          </label>
        )}
        {err && <div className="flxpos-notice is-bad">{err}</div>}
        <div className="flxpos-actions">
          <button type="button" className="flxpos-btn is-primary is-large" disabled={save.isPending} onClick={submit}>
            {t('Save')}
          </button>
          <button type="button" className="flxpos-btn is-large" onClick={onClose}>
            {t('Cancel')}
          </button>
        </div>
      </div>
    </Modal>
  );
}

/* ------------------------------------------------------------------ */
/* Transactions                                                        */
/* ------------------------------------------------------------------ */

function TxnsTab({ t, say }: { t: Translate; say: (n: Notice | null) => void }) {
  const qc = useQueryClient();
  const { scope } = useGraphQL();
  const initial = typeof window !== 'undefined' ? new URLSearchParams(window.location.search).get('s') ?? '' : '';
  const [status, setStatus] = useState<FlxPosTxnStatus | ''>('');
  const [account, setAccount] = useState('');
  const [search, setSearch] = useState(initial);
  const [typed, setTyped] = useState(initial);
  const [cursors, setCursors] = useState<(string | null)[]>([null]);
  const [refund, setRefund] = useState<Txn | null>(null);
  useEffect(() => {
    const id = setTimeout(() => setSearch(typed.trim()), 350);
    return () => clearTimeout(id);
  }, [typed]);
  useEffect(() => setCursors([null]), [status, account, search]);
  const vars = { first: 25, after: cursors[cursors.length - 1] ?? null, status: status || null, account: account || null, search: search || null };
  const q = useFlxQuery(FlxPosTxns, vars);
  const accounts = useFlxQuery(FlxPosAccounts, {});
  const labels: Record<FlxPosTxnStatus, string> = {
    PAID: t('Paid'),
    PENDING: t('Pending'),
    FAILED: t('Failed'),
    REFUNDED: t('Refunded'),
    PARTIAL: t('Partly refunded'),
    VOIDED: t('Voided'),
    REVIEW: t('Check needed'),
  };
  const tone: Record<FlxPosTxnStatus, string> = { PAID: 'is-ok', PENDING: '', FAILED: 'is-bad', REFUNDED: 'is-warn', PARTIAL: 'is-warn', VOIDED: '', REVIEW: 'is-bad' };
  const rows = q.data?.flxPosTxns.nodes ?? [];
  const info = q.data?.flxPosTxns.pageInfo;
  return (
    <section className="flxpos-card">
      <div className="flxpos-filters">
        <input type="search" placeholder={t('Reference, order number, card or message')} value={typed} onChange={(e) => setTyped(e.currentTarget.value)} />
        <select value={status} onChange={(e) => setStatus(e.currentTarget.value as FlxPosTxnStatus | '')} aria-label={t('Status')}>
          <option value="">{t('All results')}</option>
          {STATUSES.map((s) => (
            <option key={s} value={s}>
              {labels[s]}
            </option>
          ))}
        </select>
        <select value={account} onChange={(e) => setAccount(e.currentTarget.value)} aria-label={t('POS account')}>
          <option value="">{t('All accounts')}</option>
          {(accounts.data?.flxPosAccounts ?? []).map((a) => (
            <option key={a.id} value={a.id}>
              {a.name}
            </option>
          ))}
        </select>
      </div>
      {q.isLoading ? (
        <Loading />
      ) : q.isError ? (
        <Failed t={t} error={q.error} />
      ) : rows.length === 0 ? (
        <p className="flxpos-muted flxpos-empty">{t('No transactions.')}</p>
      ) : (
        <div className="flxpos-tablewrap">
          <table className="flxpos-table">
            <thead>
              <tr>
                <th scope="col">{t('Date')}</th>
                <th scope="col">{t('Order')}</th>
                <th scope="col">{t('POS')}</th>
                <th scope="col">{t('Card')}</th>
                <th scope="col">{t('Amount')}</th>
                <th scope="col">{t('Result')}</th>
                <th scope="col">
                  <span className="flxpos-sr">{t('Actions')}</span>
                </th>
              </tr>
            </thead>
            <tbody>
              {rows.map((x) => (
                <tr key={x.databaseId}>
                  <td title={x.created.label}>{x.created.relative || x.created.label}</td>
                  <td>{x.orderId ? <a href={x.orderUrl}>#{x.orderNumber}</a> : '—'}</td>
                  <td>
                    {x.accountName}
                    {x.test && <span className="flxpos-badge is-warn">{t('test')}</span>}
                  </td>
                  <td>
                    <code>{x.card || '—'}</code>
                    <small className="flxpos-cell-sub">{x.installment > 1 ? t('%d installments', x.installment) : t('single payment')}</small>
                  </td>
                  <td>
                    {x.amount.formatted}
                    {x.refunded.amount > 0 && <small className="flxpos-cell-sub">{t('refunded %s', x.refunded.formatted)}</small>}
                  </td>
                  <td>
                    <span className={'flxpos-badge ' + tone[x.status]}>{labels[x.status]}</span>
                    <small className="flxpos-cell-sub">
                      <code>{x.bankRef || x.ref}</code>
                    </small>
                    {x.message && <small className="flxpos-cell-sub">{x.message}</small>}
                  </td>
                  <td>
                    {x.refundable && (
                      <button type="button" className="flxpos-btn" onClick={() => setRefund(x)}>
                        {t('Refund')}
                      </button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      {(cursors.length > 1 || info?.hasNextPage) && (
        <div className="flxpos-pager">
          <button type="button" className="flxpos-btn" disabled={cursors.length < 2} onClick={() => setCursors(cursors.slice(0, -1))}>
            {t('Newer')}
          </button>
          <button type="button" className="flxpos-btn" disabled={!info?.hasNextPage} onClick={() => setCursors([...cursors, info?.endCursor ?? null])}>
            {t('Older')}
          </button>
        </div>
      )}
      {refund && (
        <RefundModal
          t={t}
          txn={refund}
          onClose={() => setRefund(null)}
          onDone={() => {
            setRefund(null);
            void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxPosTxns, vars) });
            say({ kind: 'ok', text: t('The refund was sent to the provider.') });
          }}
        />
      )}
    </section>
  );
}

function RefundModal({ t, txn, onClose, onDone }: { t: Translate; txn: Txn; onClose: () => void; onDone: () => void }) {
  const left = Math.round((txn.amount.amount - txn.refunded.amount) * 100) / 100;
  const [amount, setAmount] = useState(String(left));
  const [err, setErr] = useState('');
  const m = useFlxMutation(FlxPosTxnRefund, {
    onSuccess: (r) => {
      const p = r.flxPosTxnRefund;
      if (!p || p.errors.length) return setErr(p?.errors.map((e) => e.message).join(' ') || t('The action could not be completed.'));
      onDone();
    },
    onError: (e) => setErr(msg(e, t)),
  });
  const n = Number(amount.replace(',', '.'));
  return (
    <Modal title={t('Refund')} onClose={onClose}>
      <p>{t('Order #%1$s · paid %2$s. The money goes back to the card; it cannot be undone.', txn.orderNumber, txn.amount.formatted)}</p>
      <label className="flxpos-field">
        <span>{t('Amount to refund (%s)', txn.amount.currency)}</span>
        <input type="text" inputMode="decimal" value={amount} onChange={(e) => setAmount(e.currentTarget.value)} />
      </label>
      {err && <div className="flxpos-notice is-bad">{err}</div>}
      <div className="flxpos-actions">
        <button type="button" className="flxpos-btn is-danger is-large" disabled={m.isPending || !(n > 0 && n <= left)} onClick={() => m.mutate({ input: { id: txn.databaseId, amount: n } })}>
          {t('Refund')}
        </button>
        <button type="button" className="flxpos-btn is-large" onClick={onClose}>
          {t('Cancel')}
        </button>
      </div>
    </Modal>
  );
}

/* ------------------------------------------------------------------ */
/* Settings                                                            */
/* ------------------------------------------------------------------ */

function SettingsTab({ t, say }: { t: Translate; say: (n: Notice | null) => void }) {
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const q = useFlxQuery(FlxPosAccounts, {});
  const [d, setD] = useState<Settings | null>(null);
  const s = d ?? q.data?.flxPosSettings ?? null;
  const set = (patch: Partial<Settings>) => s && setD({ ...s, ...patch });
  const save = useFlxMutation(FlxPosSettingsUpdate, {
    onSuccess: (r) => {
      const p = r.flxPosSettingsUpdate;
      if (!p || p.errors.length) return say({ kind: 'bad', text: p?.errors.map((e) => e.message).join(' ') || t('The action could not be completed.') });
      const old = qc.getQueryData<AccountsData>(flxQueryKey(scope, FlxPosAccounts, {}));
      if (old) qc.setQueryData<AccountsData>(flxQueryKey(scope, FlxPosAccounts, {}), { ...old, flxPosSettings: p.settings });
      setD(null);
      say({ kind: 'ok', text: t('Saved.') });
    },
    onError: (e) => say({ kind: 'bad', text: msg(e, t) }),
  });
  if (q.isLoading) return <Loading />;
  if (q.isError || !q.data || !s) return <Failed t={t} error={q.error} />;
  const accounts = q.data.flxPosAccounts;
  const currencies = Array.from(new Set(accounts.flatMap((a) => (a.currencies.length ? a.currencies : q.data.flxPosProviders.find((p) => p.id === a.provider)?.currencies ?? []))));
  const route = (c: string) => s.routes.find((r) => r.value === c)?.label ?? '';
  const submit = () =>
    save.mutate({
      input: {
        cardTitle: s.cardTitle,
        cardDesc: s.cardDesc,
        cardAccount: s.cardAccount,
        routes: s.routes.filter((r) => r.label).map((r) => ({ currency: r.value, account: r.label })),
        display: s.display,
        showBrands: s.showBrands,
        blockFallback: s.blockFallback,
        saveLog: s.saveLog,
      },
    });
  return (
    <section className="flxpos-card">
      <div className="flxpos-card__head">
        <h2>
          <Icon name="settings" size={16} />
          {t('Card payment at checkout')}
        </h2>
      </div>
      <div className="flxpos-fields">
        <label className="flxpos-field">
          <span>{t('Title')}</span>
          <input type="text" value={s.cardTitle} onChange={(e) => set({ cardTitle: e.currentTarget.value })} />
        </label>
        <label className="flxpos-field">
          <span>{t('Description')}</span>
          <input type="text" value={s.cardDesc} onChange={(e) => set({ cardDesc: e.currentTarget.value })} />
        </label>
        <label className="flxpos-field">
          <span>{t('Default POS account')}</span>
          <select value={s.cardAccount} onChange={(e) => set({ cardAccount: e.currentTarget.value })}>
            <option value="">{t('First active account')}</option>
            {accounts.map((a) => (
              <option key={a.id} value={a.id}>
                {a.name}
              </option>
            ))}
          </select>
        </label>
        <label className="flxpos-field">
          <span>{t('Payment page')}</span>
          <select value={s.display} onChange={(e) => set({ display: e.currentTarget.value })}>
            {s.displays.map((o) => (
              <option key={o.value} value={o.value}>
                {o.label}
              </option>
            ))}
          </select>
        </label>
      </div>
      {currencies.length > 1 && (
        <div className="flxpos-routes">
          <h3 className="flxpos-sub">{t('Account per currency')}</h3>
          {currencies.map((c) => (
            <label key={c} className="flxpos-field flxpos-field--inline">
              <span>{c}</span>
              <select value={route(c)} onChange={(e) => set({ routes: [...s.routes.filter((r) => r.value !== c), { value: c, label: e.currentTarget.value }] })}>
                <option value="">{t('Default account')}</option>
                {accounts.map((a) => (
                  <option key={a.id} value={a.id}>
                    {a.name}
                  </option>
                ))}
              </select>
            </label>
          ))}
        </div>
      )}
      <div className="flxpos-checks">
        <Check checked={s.showBrands} onChange={(v) => set({ showBrands: v })} note={t('Visa, Mastercard and Troy logos next to the card number.')}>
          {t('Show card logos')}
        </Check>
        <Check checked={s.blockFallback} onChange={(v) => set({ blockFallback: v })} note={t('If the checkout block cannot show the card form, the customer goes to the payment page.')}>
          {t('Payment page fallback for the checkout block')}
        </Check>
        <Check checked={s.saveLog} onChange={(v) => set({ saveLog: v })} note={t('Provider requests and answers are kept for 7 days with the keys and card masked.')}>
          {t('Keep a technical log')}
        </Check>
      </div>
      <div className="flxpos-actions">
        <button type="button" className="flxpos-btn is-primary is-large" disabled={!d || save.isPending} onClick={submit}>
          {t('Save settings')}
        </button>
        {d && <span className="flxpos-dirty">{t('Unsaved changes')}</span>}
      </div>
    </section>
  );
}

