// Flux Commerce store screens. Same component in wp-admin and the FluxWP app.
// Reads and writes go through plugins/flx-commerce/graphql/operations.
import { useMemo, useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { useQueryClient } from '@tanstack/react-query';
import { createGraphQLClient } from '@fluxwp/graphql';
import { FlxGraphQLProvider, flxQueryKey, useFlxCan, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import {
  FlxCommerceBankSettingsSave,
  FlxCommerceCustomer,
  FlxCommerceCustomerNote,
  FlxCommerceCustomers,
  FlxCommerceCustomersRebuild,
  FlxCommerceReceiptApprove,
  FlxCommerceRenewalsRun,
  FlxCommerceReport,
  FlxCommerceReportCategories,
  FlxCommerceReportDays,
  FlxCommerceReportMethods,
  FlxCommerceReportProducts,
  FlxCommerceSettings,
  FlxCommerceSettingsSave,
  FlxCommerceSubscriptions,
  FlxCommerceSubscriptionCancel,
  FlxCommerceSubscriptionEnd,
  FlxCommerceSubscriptionRenew,
  FlxCommerceSubscriptionResume,
  FlxCommerceSummary,
} from '@fluxwp/graphql/operations';
import { Loading } from '@fluxwp/ui';
import { useHost } from '../host';
import { translator, type Translate } from './i18n';
import './commerce.css';

type Page = 'overview' | 'customers' | 'subscriptions' | 'reports' | 'settings' | `customer:${string}`;

function startOf(raw?: string): { page: Page; q: URLSearchParams } {
  const text = raw ?? 'overview';
  const [head, query] = text.split('?');
  const q = new URLSearchParams(query ?? '');
  if (head?.startsWith('customer:') || head?.startsWith('email:')) return { page: head.startsWith('customer:') ? (head as Page) : 'customers', q };
  if (head === 'customers' || head === 'subscriptions' || head === 'reports' || head === 'settings' || head === 'overview') return { page: head, q };
  return { page: 'overview', q };
}

function errText(e: unknown, t: Translate): string {
  const msg = e && typeof e === 'object' && 'message' in e ? String((e as { message: string }).message) : '';
  return msg || t('Something went wrong.');
}

const hidden = () => typeof document !== 'undefined' && document.hidden;

export function CommerceScreen() {
  const host = useHost();
  const client = useMemo(() => createGraphQLClient({ api: host.client }), [host.client]);
  return (
    <FlxGraphQLProvider client={client} scope={host.scope}>
      <Store />
    </FlxGraphQLProvider>
  );
}

function Store() {
  const host = useHost();
  const { i18n } = useTranslation();
  const { t } = useMemo(() => translator(i18n.language), [i18n.language]);
  const initial = useMemo(() => startOf(host.startPage), [host.startPage]);
  const [page, setPage] = useState<Page>(initial.page);
  const [notice, setNotice] = useState<string | null>(null);
  const manage = useFlxCan('manage_woocommerce') === true;
  const approve = useFlxCan('edit_shop_orders') === true || manage;
  const tabs: { id: Page; label: string }[] = [
    { id: 'overview', label: t('Overview') },
    { id: 'customers', label: t('Customers') },
    { id: 'subscriptions', label: t('Subscriptions') },
    { id: 'reports', label: t('Sales report') },
  ];
  if (manage) tabs.push({ id: 'settings', label: t('Settings') });
  const current = page.startsWith('customer:') ? 'customers' : page;
  return (
    <div className="flxcm-app">
      <header className="flxcm-head">
        <div>
          <h1>{t('Store')}</h1>
          <p>{t('Sales, customers, subscriptions and the bank-transfer queue.')}</p>
        </div>
        {host.classicUrl ? (
          <a className="flxcm-btn" href={host.classicUrl}>
            {t('Classic view')}
          </a>
        ) : null}
      </header>
      {notice ? (
        <p className="flxcm-notice" role="status">
          {notice}
          <button type="button" onClick={() => setNotice(null)} aria-label="x">
            ×
          </button>
        </p>
      ) : null}
      <nav className="flxcm-tabs" aria-label={t('Store')}>
        {tabs.map((tab) => (
          <button key={tab.id} type="button" aria-current={current === tab.id ? 'page' : undefined} onClick={() => setPage(tab.id)}>
            {tab.label}
          </button>
        ))}
      </nav>
      {page === 'overview' ? <Overview t={t} approve={approve} onDone={setNotice} /> : null}
      {page === 'customers' ? <Customers t={t} manage={manage} q={initial.q} open={(id) => setPage(`customer:${id}`)} /> : null}
      {page.startsWith('customer:') ? <Customer t={t} manage={manage} id={page.slice('customer:'.length)} back={() => setPage('customers')} /> : null}
      {page === 'subscriptions' ? <Subscriptions t={t} manage={manage} q={initial.q} /> : null}
      {page === 'reports' ? <Reports t={t} q={initial.q} /> : null}
      {page === 'settings' && manage ? <Settings t={t} onDone={setNotice} /> : null}
    </div>
  );
}

function money(v?: { formatted?: string | null } | null): string {
  return v?.formatted ?? '';
}
function when(v?: { label?: string | null } | null): string {
  return v?.label ?? '';
}

function Overview({ t, approve, onDone }: { t: Translate; approve: boolean; onDone: (s: string) => void }) {
  const [days, setDays] = useState(30);
  const q = useFlxQuery(FlxCommerceSummary, { days }, { refetchInterval: () => (hidden() ? false : 15_000) });
  const approveM = useFlxMutation(FlxCommerceReceiptApprove);
  const s = q.data?.flxCommerceSummary;
  const receipts = q.data?.flxCommerceReceipts?.nodes ?? [];
  if (q.isLoading) return <Loading />;
  if (q.error) return <p className="flxcm-empty">{errText(q.error, t)}</p>;
  return (
    <div>
      <div className="flxcm-chips">
        {[
          [1, t('Today')],
          [7, t('7 days')],
          [30, t('30 days')],
          [90, t('90 days')],
        ].map(([n, label]) => (
          <button key={String(n)} type="button" className={days === n ? 'is-on' : ''} onClick={() => setDays(Number(n))}>
            {label}
          </button>
        ))}
      </div>
      <div className="flxcm-kpis">
        <Kpi label={t('Revenue')} value={money(s?.revenue)} />
        <Kpi label={t('Orders')} value={String(s?.orders ?? 0)} />
        <Kpi label={t('Average order')} value={money(s?.aov)} />
        <Kpi label={t('New customers')} value={String(s?.newCustomers ?? 0)} />
        <Kpi label={t('Awaiting payment')} value={String(s?.awaiting ?? 0)} />
      </div>
      <section className="flxcm-card">
        <h2>{t('Low stock')}</h2>
        {(s?.lowStock ?? []).length === 0 ? <p className="flxcm-empty">{t('No products under the threshold.')}</p> : null}
        <ul>
          {(s?.lowStock ?? []).map((p) => (
            <li key={p.id}>
              {p.name} · {p.stock}
            </li>
          ))}
        </ul>
      </section>
      <section className="flxcm-card">
        <h2>{t('Receipts')}</h2>
        {receipts.length === 0 ? <p className="flxcm-empty">{t('No bank transfers are waiting.')}</p> : null}
        {receipts.map((r) => (
          <article key={r.id} className="flxcm-row">
            <div>
              <strong>#{r.number}</strong> {r.name} · {money(r.total)} · {r.reference}
              <div className="flxcm-muted">{when(r.createdAt)}</div>
              {(r.files ?? []).map((f) =>
                f.url ? (
                  <a key={f.name} href={f.url}>
                    {t('Open file')}
                  </a>
                ) : (
                  <span key={f.name} className="flxcm-muted">
                    {f.name} — {t('The file link is only shown to someone who can open it.')}
                  </span>
                ),
              )}
            </div>
            {approve ? (
              <button
                type="button"
                className="flxcm-btn is-primary"
                disabled={approveM.isPending}
                onClick={() =>
                  approveM.mutate(
                    { input: { id: r.id } },
                    { onSuccess: () => void q.refetch(), onError: (e) => onDone(errText(e, t)) },
                  )
                }
              >
                {t('Approve')}
              </button>
            ) : null}
          </article>
        ))}
      </section>
    </div>
  );
}

function Kpi({ label, value }: { label: string; value: string }) {
  return (
    <div className="flxcm-kpi">
      <span>{label}</span>
      <strong>{value}</strong>
    </div>
  );
}

function Customers({ t, manage, q, open }: { t: Translate; manage: boolean; q: URLSearchParams; open: (id: string) => void }) {
  const [segment, setSegment] = useState<string>(q.get('segment') || 'ALL');
  const [search, setSearch] = useState(q.get('s') || '');
  const [orderby, setOrderby] = useState(q.get('orderby') || 'LAST_AT');
  const [order, setOrder] = useState<'ASC' | 'DESC'>(q.get('order') === 'ASC' ? 'ASC' : 'DESC');
  const list = useFlxQuery(FlxCommerceCustomers, {
    first: 25,
    search: search || null,
    segment: segment as 'ALL' | 'REPEAT' | 'VIP' | 'LAPSED' | 'NEW',
    orderby: orderby as 'LAST_AT' | 'TOTAL' | 'ORDERS' | 'NAME',
    order,
  });
  const rebuild = useFlxMutation(FlxCommerceCustomersRebuild);
  const counts = list.data?.flxCommerceCustomerCounts;
  const rows = list.data?.flxCommerceCustomers?.nodes ?? [];
  const chips: [string, string, number | undefined][] = [
    ['ALL', t('All'), counts?.all],
    ['NEW', t('New (30 days)'), counts?.new],
    ['REPEAT', t('Repeat buyers'), counts?.repeat],
    ['VIP', t('Top 10% spenders'), counts?.vip],
    ['LAPSED', t('No order in 90 days'), counts?.lapsed],
  ];
  return (
    <div>
      {manage && counts && !counts.built ? <p className="flxcm-callout">{t('Older orders are not scanned yet.')}</p> : null}
      <div className="flxcm-kpis">
        <Kpi label={t('Customers')} value={String(counts?.all ?? 0)} />
        <Kpi label={t('Spent')} value={money(counts?.spent)} />
        <Kpi label={t('Average order')} value={money(counts?.ltv)} />
      </div>
      <div className="flxcm-chips">
        {chips.map(([id, label, n]) => (
          <button key={id} type="button" className={segment === id ? 'is-on' : ''} onClick={() => setSegment(id)}>
            {label} {n ?? ''}
          </button>
        ))}
      </div>
      <form
        className="flxcm-filters"
        onSubmit={(e: FormEvent) => {
          e.preventDefault();
          const data = new FormData(e.currentTarget);
          setSearch(String(data.get('s') ?? ''));
          setOrderby(String(data.get('orderby') ?? 'LAST_AT'));
        }}
      >
        <input name="s" defaultValue={search} placeholder={t('Search')} aria-label={t('Search')} />
        <select name="orderby" defaultValue={orderby} aria-label={t('Name')}>
          <option value="LAST_AT">{t('Last order')}</option>
          <option value="NAME">{t('Name')}</option>
          <option value="ORDERS">{t('Orders')}</option>
          <option value="TOTAL">{t('Spent')}</option>
        </select>
        <button className="flxcm-btn" type="submit">
          {t('Apply')}
        </button>
        <button className="flxcm-btn" type="button" onClick={() => setOrder(order === 'DESC' ? 'ASC' : 'DESC')}>
          {order === 'DESC' ? t('Newest first') : t('Oldest first')}
        </button>
        {manage ? (
          <button
            className="flxcm-btn"
            type="button"
            disabled={rebuild.isPending}
            onClick={() => rebuild.mutate({ input: { page: 1 } })}
          >
            {t('Rebuild')}
          </button>
        ) : null}
      </form>
      {rebuild.data?.flxCommerceCustomersRebuild ? (
        <p className="flxcm-muted">{t('Scanning %1$s / %2$s', rebuild.data.flxCommerceCustomersRebuild.scanned ?? 0, rebuild.data.flxCommerceCustomersRebuild.total ?? 0)}</p>
      ) : null}
      {list.isLoading ? <Loading /> : null}
      {rows.length === 0 && !list.isLoading ? <p className="flxcm-empty">{t('No customers.')}</p> : null}
      <table className="flxcm-table">
        <tbody>
          {rows.map((r) => (
            <tr key={r.id}>
              <td>
                <button type="button" className="flxcm-link" onClick={() => open(r.id)}>
                  {r.name || r.email}
                </button>
                <div className="flxcm-muted">
                  {r.email} · {r.guest ? t('Guest') : t('Member')}
                </div>
              </td>
              <td>{r.orders}</td>
              <td>{money(r.total)}</td>
              <td>{when(r.lastAt)}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function Customer({ t, manage, id, back }: { t: Translate; manage: boolean; id: string; back: () => void }) {
  const q = useFlxQuery(FlxCommerceCustomer, { id });
  const note = useFlxMutation(FlxCommerceCustomerNote);
  const [tl, setTl] = useState('all');
  const row = q.data?.flxCommerceCustomer;
  if (q.isLoading) return <Loading />;
  if (!row) return <p className="flxcm-empty">{t('No customers.')}</p>;
  return (
    <div>
      <button type="button" className="flxcm-btn" onClick={back}>
        {t('Back')}
      </button>
      <h2>
        {row.name} <span className="flxcm-muted">{row.guest ? t('Guest') : t('Member')}</span>
      </h2>
      <p>
        {row.email} · {row.phone} · {row.city}
      </p>
      <p>
        {t('Open orders')}: {row.openOrders}
      </p>
      <section className="flxcm-card">
        <h2>{t('Addresses')}</h2>
        {(row.addresses ?? []).map((a) => (
          <p key={a.kind}>
            <strong>{a.kind}</strong> {a.text}
          </p>
        ))}
      </section>
      <section className="flxcm-card">
        <h2>{t('Top products')}</h2>
        <ul>
          {(row.products ?? []).map((p) => (
            <li key={p.productId}>
              {p.name} · {p.quantity} · {money(p.total)}
            </li>
          ))}
        </ul>
      </section>
      <section className="flxcm-card">
        <h2>{t('Timeline')}</h2>
        <div className="flxcm-chips">
          {(
            [
              ['all', t('All')],
              ['orders', t('Orders')],
              ['paid', t('Payments')],
              ['notes', t('Notes')],
            ] as const
          ).map(([id, label]) => (
            <button key={id} type="button" className={tl === id ? 'is-on' : ''} onClick={() => setTl(id)}>
              {label}
            </button>
          ))}
        </div>
        <ul>
          {(row.timeline ?? [])
            .filter((e) => {
              if (tl === 'orders') return e.type === 'order';
              if (tl === 'paid') return e.type === 'paid' || e.type === 'refund' || e.type === 'order_paid' || (e.type === 'order' && e.paid);
              if (tl === 'notes') return e.type === 'note' || e.type === 'customer_note';
              return true;
            })
            .map((e, i) => (
              <li key={i}>
                {when(e.at)} · {e.title || e.label} {e.text}
              </li>
            ))}
        </ul>
      </section>
      {manage ? (
        <form
          onSubmit={(e: FormEvent<HTMLFormElement>) => {
            e.preventDefault();
            const data = new FormData(e.currentTarget);
            note.mutate({ input: { id, note: String(data.get('note') ?? '') } });
          }}
        >
          <label>
            {t('Note')}
            <textarea name="note" defaultValue={row.note} maxLength={2000} />
          </label>
          <button className="flxcm-btn is-primary" type="submit">
            {t('Save note')}
          </button>
        </form>
      ) : (
        <p>{row.note}</p>
      )}
    </div>
  );
}

function Subscriptions({ t, manage, q }: { t: Translate; manage: boolean; q: URLSearchParams }) {
  const [status, setStatus] = useState(q.get('status') || '');
  const [search, setSearch] = useState(q.get('s') || '');
  const list = useFlxQuery(FlxCommerceSubscriptions, {
    first: 25,
    search: search || null,
    status: (status || null) as 'PENDING' | 'ACTIVE' | 'PAST_DUE' | 'CANCELLED' | 'EXPIRED' | null,
  });
  const renew = useFlxMutation(FlxCommerceSubscriptionRenew);
  const cancel = useFlxMutation(FlxCommerceSubscriptionCancel);
  const end = useFlxMutation(FlxCommerceSubscriptionEnd);
  const resume = useFlxMutation(FlxCommerceSubscriptionResume);
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const refresh = () => void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxCommerceSubscriptions) });
  const counts = list.data?.flxCommerceSubscriptionCounts;
  const rows = list.data?.flxCommerceSubscriptions?.nodes ?? [];
  const act = (run: typeof renew, id: string) => run.mutate({ input: { id } }, { onSuccess: refresh });
  const chips: [string, string][] = [
    ['', t('All')],
    ['ACTIVE', t('Active')],
    ['PAST_DUE', t('Past due')],
    ['CANCELLED', t('Cancelled')],
    ['PENDING', t('Pending')],
    ['EXPIRED', t('Expired')],
  ];
  return (
    <div>
      <div className="flxcm-kpis">
        <Kpi label={t('Active')} value={String(counts?.active ?? 0)} />
        <Kpi label={t('Monthly recurring')} value={money(counts?.mrr)} />
        <Kpi label={t('Due in 30 days')} value={String(counts?.due ?? 0)} />
      </div>
      <div className="flxcm-chips">
        {chips.map(([id, label]) => (
          <button key={id || 'all'} type="button" className={status === id ? 'is-on' : ''} onClick={() => setStatus(id)}>
            {label}
          </button>
        ))}
      </div>
      <form
        className="flxcm-filters"
        onSubmit={(e: FormEvent) => {
          e.preventDefault();
          setSearch(String(new FormData(e.currentTarget).get('s') ?? ''));
        }}
      >
        <input name="s" defaultValue={search} placeholder={t('Search')} aria-label={t('Search')} />
        <button className="flxcm-btn" type="submit">
          {t('Apply')}
        </button>
      </form>
      {list.isLoading ? <Loading /> : null}
      {rows.length === 0 && !list.isLoading ? <p className="flxcm-empty">{t('No subscriptions.')}</p> : null}
      {rows.map((r) => (
        <article key={r.id} className="flxcm-row">
          <div>
            <strong>{r.productName}</strong> · {r.name} · {r.email}
            <div className="flxcm-muted">
              {money(r.amount)} / {r.period} · {r.status} · {when(r.periodEnd)}
              {r.cardLabel ? ` · ${r.cardLabel}` : ''} · {t('%d renewals', r.renewals)}
              {r.parentOrderId ? ` · #${r.parentOrderId}` : ''}
              {r.endedAt ? ` · ${when(r.endedAt)}` : ''}
            </div>
          </div>
          {manage ? (
            <div className="flxcm-actions">
              {(r.status === 'ACTIVE' || r.status === 'PAST_DUE') && !r.renewalOrderId ? (
                <button type="button" className="flxcm-btn" onClick={() => act(renew, r.id)}>
                  {t('Create renewal order')}
                </button>
              ) : null}
              {r.status === 'ACTIVE' || r.status === 'PAST_DUE' ? (
                <button type="button" className="flxcm-btn" onClick={() => act(cancel, r.id)}>
                  {t('Cancel at period end')}
                </button>
              ) : null}
              {r.status === 'CANCELLED' ? (
                <button type="button" className="flxcm-btn" onClick={() => act(resume, r.id)}>
                  {t('Turn renewal back on')}
                </button>
              ) : null}
              {r.status !== 'EXPIRED' ? (
                <button type="button" className="flxcm-btn" onClick={() => act(end, r.id)}>
                  {t('End now')}
                </button>
              ) : null}
            </div>
          ) : null}
        </article>
      ))}
    </div>
  );
}

function Reports({ t, q }: { t: Translate; q: URLSearchParams }) {
  const [preset, setPreset] = useState(q.get('preset') || 'D30');
  const [from, setFrom] = useState(q.get('from') || '');
  const [to, setTo] = useState(q.get('to') || '');
  const vars = {
    preset: (from && to ? null : preset) as 'D7' | 'D30' | 'D90' | 'MONTH' | 'LAST_MONTH' | 'YEAR' | null,
    from: from || null,
    to: to || null,
  };
  const report = useFlxQuery(FlxCommerceReport, vars);
  const days = useFlxQuery(FlxCommerceReportDays, { ...vars, first: 50 });
  const products = useFlxQuery(FlxCommerceReportProducts, { ...vars, first: 50 });
  const categories = useFlxQuery(FlxCommerceReportCategories, vars);
  const methods = useFlxQuery(FlxCommerceReportMethods, vars);
  const s = report.data?.flxCommerceReport;
  const presets: [string, string][] = [
    ['D7', t('7 days')],
    ['D30', t('30 days')],
    ['D90', t('90 days')],
    ['MONTH', t('This month')],
    ['LAST_MONTH', t('Last month')],
    ['YEAR', t('This year')],
  ];
  return (
    <div>
      <div className="flxcm-chips">
        {presets.map(([id, label]) => (
          <button
            key={id}
            type="button"
            className={preset === id && !from ? 'is-on' : ''}
            onClick={() => {
              setPreset(id);
              setFrom('');
              setTo('');
            }}
          >
            {label}
          </button>
        ))}
      </div>
      <form
        className="flxcm-filters"
        onSubmit={(e: FormEvent) => {
          e.preventDefault();
          const data = new FormData(e.currentTarget);
          setFrom(String(data.get('from') ?? ''));
          setTo(String(data.get('to') ?? ''));
        }}
      >
        <label>
          {t('From')} <input name="from" type="date" defaultValue={from} />
        </label>
        <label>
          {t('To')} <input name="to" type="date" defaultValue={to} />
        </label>
        <button className="flxcm-btn" type="submit">
          {t('Apply')}
        </button>
      </form>
      {report.isLoading ? <Loading /> : null}
      <div className="flxcm-kpis">
        <Kpi label={t('Revenue')} value={money(s?.revenue)} />
        <Kpi label={t('Orders')} value={String(s?.orders ?? 0)} />
        <Kpi label={t('Average order')} value={money(s?.aov)} />
      </div>
      <section className="flxcm-card">
        <h2>{t('Days')}</h2>
        <ul>
          {(days.data?.flxCommerceReportDays?.nodes ?? []).map((d) => (
            <li key={d.date}>
              {d.date} · {money(d.revenue)} · {d.orders}
            </li>
          ))}
        </ul>
      </section>
      <section className="flxcm-card">
        <h2>{t('Products')}</h2>
        <ul>
          {(products.data?.flxCommerceReportProducts?.nodes ?? []).map((p) => (
            <li key={p.productId}>
              {p.name} · {p.quantity} · {money(p.total)}
            </li>
          ))}
        </ul>
      </section>
      <section className="flxcm-card">
        <h2>{t('Categories')}</h2>
        <ul>
          {(categories.data?.flxCommerceReportCategories?.nodes ?? []).map((c) => (
            <li key={c.name}>
              {c.name} · {money(c.total)}
            </li>
          ))}
        </ul>
      </section>
      <section className="flxcm-card">
        <h2>{t('Payment methods')}</h2>
        <ul>
          {(methods.data?.flxCommerceReportMethods?.nodes ?? []).map((m) => (
            <li key={m.title}>
              {m.title} · {m.orders} · {money(m.total)}
            </li>
          ))}
        </ul>
      </section>
    </div>
  );
}

function Settings({ t, onDone }: { t: Translate; onDone: (s: string) => void }) {
  const q = useFlxQuery(FlxCommerceSettings, {});
  const save = useFlxMutation(FlxCommerceSettingsSave);
  const bank = useFlxMutation(FlxCommerceBankSettingsSave);
  const run = useFlxMutation(FlxCommerceRenewalsRun);
  const s = q.data?.flxCommerceSettings;
  const b = q.data?.flxCommerceBankSettings;
  const status = q.data?.flxCommerceRenewalStatus;
  if (q.isLoading || !s || !b) return <Loading />;
  return (
    <div>
      <form
        className="flxcm-card"
        onSubmit={(e: FormEvent<HTMLFormElement>) => {
          e.preventDefault();
          const data = new FormData(e.currentTarget);
          const paid = data.getAll('paid').map(String);
          save.mutate(
            {
              input: {
                paidStatuses: paid,
                excludeDonations: data.get('excludeDonations') === '1',
                lowStock: Number(data.get('lowStock') ?? 0),
                hideCompany: data.get('hideCompany') === '1',
                hideAddress2: data.get('hideAddress2') === '1',
                virtualNoAddress: data.get('virtualNoAddress') === '1',
                orderNotes: data.get('orderNotes') === '1',
                stickyPay: data.get('stickyPay') === '1',
                freeShipBar: data.get('freeShipBar') === '1',
                freeShipAmount: Number(data.get('freeShipAmount') ?? 0),
                receiptMaxMb: Number(data.get('receiptMaxMb') ?? 8),
                receiptNotify: String(data.get('receiptNotify') ?? ''),
                deferToDonate: data.get('deferToDonate') === '1',
                cancelAfterDays: Number(data.get('cancelAfterDays') ?? 0),
                feed: data.get('feed') === '1',
                feedBrand: String(data.get('feedBrand') ?? ''),
                notifyCustomerWa: data.get('notifyCustomerWa') === '1',
                notifyAdminPhone: String(data.get('notifyAdminPhone') ?? ''),
                notifyAdminOrder: data.get('notifyAdminOrder') === '1',
                subRenewDays: Number(data.get('subRenewDays') ?? 0),
                subGraceDays: Number(data.get('subGraceDays') ?? 0),
                hiddenRedirect: String(data.get('hiddenRedirect') ?? ''),
              },
            },
            { onSuccess: () => onDone(t('Saved')), onError: (err) => onDone(errText(err, t)) },
          );
        }}
      >
        <fieldset>
          <legend>{t('Order statuses counted as revenue')}</legend>
          {(s.orderStatuses ?? []).map((st) => (
            <label key={st.slug}>
              <input type="checkbox" name="paid" value={st.slug} defaultChecked={s.paidStatuses.includes(st.slug)} /> {st.label}
            </label>
          ))}
        </fieldset>
        {s.donateCart ? <Toggle name="excludeDonations" label={t('Leave donation orders out of the store statistics')} on={s.excludeDonations} /> : null}
        <Num name="lowStock" label={t('Low stock threshold')} value={s.lowStock} />
        <Toggle name="hideCompany" label={t('Hide the company name field')} on={s.hideCompany} />
        <Toggle name="hideAddress2" label={t('Hide the second address line')} on={s.hideAddress2} />
        <Toggle name="virtualNoAddress" label={t('Do not ask for an address when the cart only has virtual/downloadable products')} on={s.virtualNoAddress} />
        <Toggle name="orderNotes" label={t('Order notes field')} on={s.orderNotes} />
        <Toggle name="stickyPay" label={t('On mobile, keep the Place order button fixed at the bottom')} on={s.stickyPay} />
        <Toggle name="freeShipBar" label={t('Free shipping bar')} on={s.freeShipBar} />
        <Num name="freeShipAmount" label={t('Free shipping amount')} value={s.freeShipAmount} />
        <p className="flxcm-muted">
          {t('WooCommerce free shipping minimum')}: {money(s.freeShipThreshold)}
        </p>
        <Num name="receiptMaxMb" label={t('Largest receipt (MB)')} value={s.receiptMaxMb} />
        <label>
          {t('Receipt notification e-mail')} <input name="receiptNotify" type="email" defaultValue={s.receiptNotify} />
        </label>
        {s.donateHavale ? <Toggle name="deferToDonate" label={t("Hide this method when Flux Donate's bank transfer is on")} on={s.deferToDonate} /> : null}
        <Num name="cancelAfterDays" label={t('Cancel unpaid bank transfers after (days)')} value={s.cancelAfterDays} />
        <Toggle name="feed" label={t('Feed on')} on={s.feed} />
        <p>
          {t('Feed address')}: {s.feedUrl || t('The feed is off, so there is no address.')}
        </p>
        <label>
          {t('Brand')} <input name="feedBrand" defaultValue={s.feedBrand} />
        </label>
        <p>{s.whatsappReady ? t('WhatsApp is ready.') : t('WhatsApp needs Flux Mail.')}</p>
        <Toggle name="notifyCustomerWa" label={t('Send the bank details and the payment confirmation to the customer by WhatsApp')} on={s.notifyCustomerWa} />
        <label>
          {t('Admin WhatsApp number')} <input name="notifyAdminPhone" defaultValue={s.notifyAdminPhone} />
        </label>
        <Toggle name="notifyAdminOrder" label={t('WhatsApp the admin for every new paid order')} on={s.notifyAdminOrder} />
        <p>
          {t('Card providers')}: {(s.cardProviders ?? []).join(', ') || t('No card provider yet.')}
        </p>
        <Num name="subRenewDays" label={t('Create the renewal order (days before the period ends)')} value={s.subRenewDays} />
        <Num name="subGraceDays" label={t('Grace period after the period ends (days)')} value={s.subGraceDays} />
        <label>
          {t('Hidden products: send visitors to')} <input name="hiddenRedirect" type="url" defaultValue={s.hiddenRedirect} />
        </label>
        <button className="flxcm-btn is-primary" type="submit" disabled={save.isPending}>
          {t('Save')}
        </button>
      </form>
      <form
        className="flxcm-card"
        onSubmit={(e: FormEvent<HTMLFormElement>) => {
          e.preventDefault();
          const data = new FormData(e.currentTarget);
          bank.mutate(
            {
              input: {
                enabled: data.get('enabled') === '1',
                title: String(data.get('title') ?? ''),
                description: String(data.get('description') ?? ''),
                holder: String(data.get('holder') ?? ''),
                iban: String(data.get('iban') ?? ''),
                bank: String(data.get('bank') ?? ''),
                refFormat: String(data.get('refFormat') ?? ''),
                refPrefix: String(data.get('refPrefix') ?? ''),
              },
            },
            { onSuccess: () => onDone(t('Saved')) },
          );
        }}
      >
        <h2>{t('Bank transfer')}</h2>
        <p className="flxcm-muted">{t('The IBAN and the method switch are the same values WooCommerce Payments stores.')}</p>
        <Toggle name="enabled" label={t('Enabled')} on={b.enabled} />
        <label>
          {t('Title')} <input name="title" defaultValue={b.title} />
        </label>
        <label>
          {t('Description')} <input name="description" defaultValue={b.description} />
        </label>
        <label>
          {t('Account holder')} <input name="holder" defaultValue={b.holder} />
        </label>
        <label>
          {t('IBAN')} <input name="iban" defaultValue={b.iban} />
        </label>
        <label>
          {t('Bank')} <input name="bank" defaultValue={b.bank} />
        </label>
        <label>
          {t('Reference format')} <input name="refFormat" defaultValue={b.refFormat} />
        </label>
        <label>
          {t('Reference prefix')} <input name="refPrefix" defaultValue={b.refPrefix} />
        </label>
        <button className="flxcm-btn is-primary" type="submit">
          {t('Save')}
        </button>
      </form>
      <section className="flxcm-card">
        <h2>{t('Last renewal pass')}</h2>
        <p>{status?.ran ? when(status.at) : t('Not run yet.')}</p>
        <button className="flxcm-btn" type="button" disabled={run.isPending} onClick={() => run.mutate({ input: {} }, { onSuccess: () => void q.refetch() })}>
          {t('Run renewals now')}
        </button>
      </section>
    </div>
  );
}

function Toggle({ name, label, on }: { name: string; label: string; on: boolean }) {
  return (
    <label className="flxcm-toggle">
      <input type="checkbox" name={name} value="1" defaultChecked={on} /> {label}
    </label>
  );
}
function Num({ name, label, value }: { name: string; label: string; value: number }) {
  return (
    <label>
      {label} <input name={name} type="number" defaultValue={value} />
    </label>
  );
}
