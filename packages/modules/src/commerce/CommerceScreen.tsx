// Flux Commerce store screens. Same component in wp-admin and the FluxWP app.
// Reads and writes go through plugins/flx-commerce/graphql/operations.
import { useEffect, useMemo, useState, type FormEvent, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { useQueryClient } from '@tanstack/react-query';
import {
  AlertTriangle,
  ArrowLeft,
  ArrowRight,
  Calendar,
  Check,
  Clock,
  Coins,
  Copy,
  Download,
  Landmark,
  Mail,
  MapPin,
  MessageCircle,
  Package,
  Phone,
  Receipt,
  RefreshCw,
  Rss,
  Search,
  ShoppingBag,
  StickyNote,
  TrendingUp,
  Truck,
  User,
  Users,
} from 'lucide-react';
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
import type {
  FlxCommerceCustomersQuery,
  FlxCommerceReportDaysQuery,
  FlxCommerceReportPreset,
  FlxCommerceReportProductsQuery,
} from '@fluxwp/graphql/types';
import { Loading, Modal } from '@fluxwp/ui';
import { useHost } from '../host';
import { csvDownload } from './csv';
import { translator, type Translate } from './i18n';
import './commerce.css';

type Page = 'overview' | 'customers' | 'subscriptions' | 'reports' | 'settings' | `customer:${string}`;
type Notice = { kind: 'ok' | 'bad'; text: string } | null;
type MoneyVal = { amount?: number | null; currency?: string | null; formatted?: string | null } | null | undefined;
type WhenVal = { iso?: string | null; timestamp?: number | null; label?: string | null; relative?: string | null } | null | undefined;
type PayloadErr = { message?: string | null } | null | undefined;

function startOf(raw?: string): { page: Page; email: string; q: URLSearchParams } {
  const text = raw ?? 'overview';
  const [head, query] = text.split('?');
  const q = new URLSearchParams(query ?? '');
  if (head?.startsWith('customer:')) return { page: head as Page, email: '', q };
  if (head?.startsWith('email:')) return { page: 'customers', email: head.slice('email:'.length), q };
  if (head === 'customers' || head === 'subscriptions' || head === 'reports' || head === 'settings' || head === 'overview') {
    return { page: head, email: '', q };
  }
  return { page: 'overview', email: '', q };
}

function errText(e: unknown, t: Translate): string {
  const msg = e && typeof e === 'object' && 'message' in e ? String((e as { message: string }).message) : '';
  return msg || t('Something went wrong.');
}

function payloadErr(errors: ReadonlyArray<PayloadErr> | null | undefined): string {
  return (errors ?? [])
    .map((e) => e?.message ?? '')
    .filter(Boolean)
    .join(' ');
}

function money(v?: MoneyVal): string {
  return v?.formatted ?? '';
}

function moneyCalc(amount: number, currency?: string | null): string {
  try {
    return new Intl.NumberFormat(undefined, {
      style: 'currency',
      currency: currency || 'TRY',
      currencyDisplay: 'narrowSymbol',
      maximumFractionDigits: 2,
    }).format(amount);
  } catch {
    return `${amount.toFixed(2)} ${currency || ''}`.trim();
  }
}

function when(v?: WhenVal): string {
  return v?.label ?? '';
}

function digitsOnly(phone: string): string {
  return phone.replace(/\D+/g, '');
}

function initialOf(name: string): string {
  const s = name.trim();
  return s ? [...s][0]!.toUpperCase() : '?';
}

function adminLink(adminUrl: string | undefined, path: string): string {
  if (!adminUrl) return '';
  return adminUrl.replace(/\/?$/, '/') + path.replace(/^\/+/, '');
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
  const num = useMemo(() => new Intl.NumberFormat(i18n.language || undefined), [i18n.language]);
  const fmtNum = (n: number) => num.format(n);
  const initial = useMemo(() => startOf(host.startPage), [host.startPage]);
  const [page, setPage] = useState<Page>(initial.page);
  const [reportRange, setReportRange] = useState<{ from: string; to: string } | null>(null);
  const [notice, setNotice] = useState<Notice>(null);
  const [emailTarget, setEmailTarget] = useState(initial.email);
  const [fallbackSearch, setFallbackSearch] = useState(initial.email);

  const manage = useFlxCan('manage_woocommerce') === true;
  const approve = useFlxCan('edit_shop_orders') === true || manage;

  const emailQuery = useFlxQuery(
    FlxCommerceCustomers,
    { first: 10, search: emailTarget || null },
    { enabled: emailTarget !== '' },
  );

  useEffect(() => {
    if (!emailTarget || emailQuery.isLoading || !emailQuery.data) return;
    const nodes = emailQuery.data.flxCommerceCustomers?.nodes ?? [];
    const exact = nodes.find((n) => n.email.toLowerCase() === emailTarget.toLowerCase()) ?? nodes[0];
    if (exact) {
      setPage(`customer:${exact.id}`);
    } else {
      setFallbackSearch(emailTarget);
      setPage('customers');
    }
    setEmailTarget('');
  }, [emailQuery.data, emailQuery.isLoading, emailTarget]);

  const tell = (kind: 'ok' | 'bad', text: string) => setNotice({ kind, text });
  const openByEmail = (email: string) => {
    setFallbackSearch(email);
    setEmailTarget(email);
  };

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
        <div className="flxcm-head__actions">
          {host.classicUrl ? (
            <a className="flxcm-btn" href={host.classicUrl}>
              {t('Classic view')}
            </a>
          ) : null}
        </div>
      </header>
      {notice ? (
        <p className={'flxcm-notice' + (notice.kind === 'bad' ? ' is-bad' : '')} role="status">
          <span>{notice.text}</span>
          <button type="button" onClick={() => setNotice(null)} aria-label="Close">
            ×
          </button>
        </p>
      ) : null}
      <nav className="flxcm-tabs" aria-label={t('Store')}>
        {tabs.map((tab) => (
          <button
            key={tab.id}
            type="button"
            aria-current={current === tab.id ? 'page' : undefined}
            onClick={() => {
              setNotice(null);
              if (tab.id === 'reports') setReportRange(null);
              setPage(tab.id);
            }}
          >
            {tab.label}
          </button>
        ))}
      </nav>
      {page === 'overview' ? (
        <Overview
          t={t}
          num={fmtNum}
          approve={approve}
          adminUrl={host.adminUrl}
          tell={tell}
          openReports={(from, to) => {
            setReportRange({ from, to });
            setPage('reports');
          }}
        />
      ) : null}
      {page === 'customers' ? (
        <Customers
          t={t}
          num={fmtNum}
          manage={manage}
          adminUrl={host.adminUrl}
          q={initial.q}
          initialSearch={fallbackSearch}
          tell={tell}
          open={(id) => setPage(`customer:${id}`)}
        />
      ) : null}
      {page.startsWith('customer:') ? (
        <Customer
          t={t}
          num={fmtNum}
          manage={manage}
          adminUrl={host.adminUrl}
          id={page.slice('customer:'.length)}
          tell={tell}
          back={() => setPage('customers')}
        />
      ) : null}
      {page === 'subscriptions' ? (
        <Subscriptions
          t={t}
          num={fmtNum}
          manage={manage}
          q={initial.q}
          tell={tell}
          openCustomerByEmail={openByEmail}
        />
      ) : null}
      {page === 'reports' ? (
        <Reports
          t={t}
          num={fmtNum}
          q={initial.q}
          forcedRange={reportRange}
        />
      ) : null}
      {page === 'settings' && manage ? <Settings t={t} adminUrl={host.adminUrl} tell={tell} /> : null}
    </div>
  );
}

function Kpi({
  icon,
  label,
  value,
  delta,
  hint,
}: {
  icon: ReactNode;
  label: string;
  value: string;
  delta?: number | null;
  hint?: string;
}) {
  let deltaEl: ReactNode = null;
  if (delta !== null && delta !== undefined) {
    const cls = delta > 0.05 ? 'is-up' : delta < -0.05 ? 'is-down' : 'is-flat';
    const text = (delta > 0 ? '+' : '') + delta.toFixed(Math.abs(delta) < 10 ? 1 : 0) + '%';
    deltaEl = <span className={`flxcm-delta ${cls}`}>{text}</span>;
  }
  return (
    <div className="flxcm-kpi">
      <span className="flxcm-kpi__icon" aria-hidden="true">
        {icon}
      </span>
      <div className="flxcm-kpi__body">
        <span className="flxcm-kpi__label">{label}</span>
        <strong className="flxcm-kpi__value">{value}</strong>
        {deltaEl}
        {hint ? <span className="flxcm-kpi__hint">{hint}</span> : null}
      </div>
    </div>
  );
}

function niceMax(v: number): number {
  if (v <= 0) return 1;
  const e = Math.pow(10, Math.floor(Math.log10(v)));
  for (const m of [1, 2, 2.5, 5, 10]) {
    if (v <= m * e) return m * e;
  }
  return 10 * e;
}

function shortDate(ymd: string): string {
  const parts = ymd.split('-');
  if (parts.length !== 3) return ymd;
  return `${parts[2]}.${parts[1]}`;
}

function RevenueChart({
  series,
  t,
  num,
}: {
  series: ReadonlyArray<{ date: string; orders: number; revenue: { amount: number; currency: string; formatted: string } }>;
  t: Translate;
  num: (n: number) => string;
}) {
  const n = series.length;
  if (!n) return null;
  const maxVal = Math.max(1, ...series.map((d) => d.revenue.amount));
  const nice = niceMax(maxVal);
  const currency = series.find((d) => d.revenue.currency)?.revenue.currency ?? 'TRY';
  const w = 720;
  const h = 200;
  const gap = n > 60 ? 1 : n > 20 ? 2 : 6;
  const bw = Math.max(1, (w - gap * (n - 1)) / n);
  const step = Math.max(1, Math.ceil(n / 7));

  return (
    <figure className="flxcm-chart">
      <svg viewBox={`0 0 ${w} ${h}`} preserveAspectRatio="none" role="img" aria-label={t('Daily revenue')}>
        {[0.25, 0.5, 0.75, 1].map((g) => {
          const y = h - h * g;
          return <line key={g} className="flxcm-chart__grid" x1="0" x2={w} y1={y} y2={y} />;
        })}
        {series.map((d, i) => {
          const bh = d.revenue.amount > 0 ? Math.max(2, (h * d.revenue.amount) / nice) : 0;
          const x = i * (bw + gap);
          const tip = t('%1$s · %2$s · %3$s orders', shortDate(d.date), d.revenue.formatted, num(d.orders));
          return (
            <rect
              key={d.date}
              className="flxcm-chart__bar"
              x={Number(x.toFixed(2))}
              y={Number((h - bh).toFixed(2))}
              width={Number(bw.toFixed(2))}
              height={Number(bh.toFixed(2))}
              rx={bw > 8 ? 3 : 1}
            >
              <title>{tip}</title>
            </rect>
          );
        })}
      </svg>
      <div className="flxcm-chart__axis" aria-hidden="true">
        {series.map((d, j) => {
          if (j % step !== 0) return null;
          const xPct = ((j * (bw + gap) + bw / 2) / w) * 100;
          return (
            <span key={d.date} style={{ left: `${xPct.toFixed(2)}%` }}>
              {shortDate(d.date)}
            </span>
          );
        })}
      </div>
      <figcaption className="flxcm-chart__max">{moneyCalc(nice, currency)}</figcaption>
    </figure>
  );
}

function BarsList({
  items,
  emptyText,
}: {
  items: ReadonlyArray<{ key: string; label: string; amount: number; formatted: string; sub: string }>;
  emptyText: string;
}) {
  if (!items.length) return <p className="flxcm-empty">{emptyText}</p>;
  const max = Math.max(1, ...items.map((r) => r.amount));
  return (
    <ul className="flxcm-bars">
      {items.map((r) => {
        const pct = Math.min(100, (r.amount / max) * 100);
        return (
          <li key={r.key}>
            <div className="flxcm-bars__top">
              <span className="flxcm-bars__name">{r.label}</span>
              <span className="flxcm-bars__val">{r.formatted}</span>
            </div>
            <div className="flxcm-bars__track">
              <span style={{ width: `${pct.toFixed(1)}%` }} />
            </div>
            <span className="flxcm-bars__sub">{r.sub}</span>
          </li>
        );
      })}
    </ul>
  );
}

function Overview({
  t,
  num,
  approve,
  adminUrl,
  tell,
  openReports,
}: {
  t: Translate;
  num: (n: number) => string;
  approve: boolean;
  adminUrl?: string;
  tell: (kind: 'ok' | 'bad', text: string) => void;
  openReports: (from: string, to: string) => void;
}) {
  const [days, setDays] = useState(30);
  const q = useFlxQuery(FlxCommerceSummary, { days }, { refetchInterval: () => (hidden() ? false : 60_000) });
  const s = q.data?.flxCommerceSummary;
  const preset: FlxCommerceReportPreset | null = days === 7 ? 'D7' : days === 30 ? 'D30' : days === 90 ? 'D90' : null;
  const prodQ = useFlxQuery(
    FlxCommerceReportProducts,
    { first: 6, preset, from: days === 1 ? (s?.from ?? null) : null, to: days === 1 ? (s?.to ?? null) : null },
    { enabled: Boolean(preset || s?.from) },
  );
  const methQ = useFlxQuery(
    FlxCommerceReportMethods,
    { first: 6, preset, from: days === 1 ? (s?.from ?? null) : null, to: days === 1 ? (s?.to ?? null) : null },
    { enabled: Boolean(preset || s?.from) },
  );
  const approveM = useFlxMutation(FlxCommerceReceiptApprove);
  const receipts = q.data?.flxCommerceReceipts?.nodes ?? [];

  if (q.isLoading) return <Loading />;
  if (q.error) return <p className="flxcm-empty">{errText(q.error, t)}</p>;

  const topProducts = (prodQ.data?.flxCommerceReportProducts?.nodes ?? []).map((p) => ({
    key: String(p.productId || p.name),
    label: p.name,
    amount: p.total.amount,
    formatted: p.total.formatted,
    sub: `${num(p.quantity)} ${t('pcs')}`,
  }));
  const methods = (methQ.data?.flxCommerceReportMethods?.nodes ?? []).map((m) => ({
    key: m.title,
    label: m.title,
    amount: m.total.amount,
    formatted: m.total.formatted,
    sub: `${num(m.orders)} ${t('orders')}`,
  }));

  const quickLinks = adminUrl
    ? [
        { icon: <Receipt size={16} className="flxcm-i" />, label: t('Orders'), href: adminLink(adminUrl, 'edit.php?post_type=shop_order') },
        { icon: <Package size={16} className="flxcm-i" />, label: t('Products'), href: adminLink(adminUrl, 'edit.php?post_type=product') },
        { icon: <ShoppingBag size={16} className="flxcm-i" />, label: t('New product'), href: adminLink(adminUrl, 'post-new.php?post_type=product') },
        { icon: <Coins size={16} className="flxcm-i" />, label: t('Coupons'), href: adminLink(adminUrl, 'edit.php?post_type=shop_coupon') },
        { icon: <Truck size={16} className="flxcm-i" />, label: t('Shipping'), href: adminLink(adminUrl, 'admin.php?page=wc-settings&tab=shipping') },
      ]
    : [];

  return (
    <div>
      <div className="flxcm-seg" role="group" aria-label={t('Overview')}>
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
        <Kpi icon={<Coins size={18} />} label={t('Net revenue')} value={money(s?.revenue)} delta={s?.revenueDelta} />
        <Kpi icon={<Receipt size={18} />} label={t('Orders')} value={num(s?.orders ?? 0)} delta={s?.ordersDelta} />
        <Kpi icon={<ShoppingBag size={18} />} label={t('Average order')} value={money(s?.aov)} delta={s?.aovDelta} />
        <Kpi
          icon={<Users size={18} />}
          label={t('Customers')}
          value={num(s?.customers ?? 0)}
          hint={s?.newCustomers ? t('%s new', num(s.newCustomers)) : undefined}
        />
      </div>
      {s?.from && s?.to ? (
        <p className="flxcm-period">
          {s.from} – {s.to}
        </p>
      ) : null}
      <div className="flxcm-grid">
        <section className="flxcm-card flxcm-span-2">
          <div className="flxcm-card__head">
            <h2>{t('Daily revenue')}</h2>
            {s?.from && s?.to ? (
              <button type="button" className="flxcm-link" onClick={() => openReports(s.from, s.to)}>
                {t('Full report')} <ArrowRight size={14} className="flxcm-i" />
              </button>
            ) : null}
          </div>
          {days === 1 ? (
            <p className="flxcm-empty">{t('Pick 7 days or longer for the daily chart.')}</p>
          ) : !s?.orders ? (
            <p className="flxcm-empty">{t('No paid orders in this period.')}</p>
          ) : (
            <RevenueChart series={s.series} t={t} num={num} />
          )}
        </section>

        <section className="flxcm-card">
          <div className="flxcm-card__head">
            <h2>{t('Awaiting payment')}</h2>
            {(s?.awaiting ?? 0) > 0 ? <span className="flxcm-badge is-wait">{num(s?.awaiting ?? 0)}</span> : null}
          </div>
          {receipts.length === 0 ? (
            <p className="flxcm-empty">
              <Check size={16} className="flxcm-i" /> {t('No bank transfers are waiting.')}
            </p>
          ) : (
            receipts.map((r) => (
              <article key={r.id} className="flxcm-row">
                <div>
                  <strong>#{r.number}</strong> {r.name} · {money(r.total)} {r.reference ? `· ${r.reference}` : ''}
                  <div className="flxcm-muted">{when(r.createdAt)}</div>
                  {(r.files ?? []).map((f) =>
                    f.url ? (
                      <div key={f.name}>
                        <a href={f.url} target="_blank" rel="noopener noreferrer">
                          {t('Open file')} ({f.name})
                        </a>
                      </div>
                    ) : (
                      <div key={f.name} className="flxcm-muted">
                        {f.name} — {t('The file link is only shown to someone who can open it.')}
                      </div>
                    ),
                  )}
                </div>
                {approve ? (
                  <button
                    type="button"
                    className="flxcm-btn is-primary is-small"
                    disabled={approveM.isPending}
                    onClick={() =>
                      approveM.mutate(
                        { input: { id: r.id } },
                        {
                          onSuccess: (res) => {
                            const msg = payloadErr(res.flxCommerceReceiptApprove?.errors);
                            if (msg) {
                              tell('bad', msg);
                              return;
                            }
                            tell('ok', t('Payment approved'));
                            void q.refetch();
                          },
                          onError: (e) => tell('bad', errText(e, t)),
                        },
                      )
                    }
                  >
                    {t('Approve')}
                  </button>
                ) : null}
              </article>
            ))
          )}
        </section>

        <section className="flxcm-card">
          <div className="flxcm-card__head">
            <h2>{t('Best sellers')}</h2>
          </div>
          <BarsList items={topProducts} emptyText={t('No sales in this period.')} />
        </section>

        <section className="flxcm-card">
          <div className="flxcm-card__head">
            <h2>{t('Low stock')}</h2>
          </div>
          {(s?.lowStock ?? []).length === 0 ? (
            <p className="flxcm-empty">
              <Check size={16} className="flxcm-i" /> {t('No products running low.')}
            </p>
          ) : (
            <ul className="flxcm-list">
              {(s?.lowStock ?? []).map((p) => {
                const badge =
                  p.stock <= 0 ? (
                    <span className="flxcm-badge is-bad">{t('Out of stock')}</span>
                  ) : (
                    <span className="flxcm-badge is-wait">{t('%s left', num(p.stock))}</span>
                  );
                return (
                  <li key={p.id}>
                    {p.url ? (
                      <a href={p.url}>
                        <span className="flxcm-list__main">
                          <strong>{p.name}</strong>
                        </span>
                        <span className="flxcm-list__side">{badge}</span>
                      </a>
                    ) : (
                      <span className="flxcm-li">
                        <span className="flxcm-list__main">
                          <strong>{p.name}</strong>
                        </span>
                        <span className="flxcm-list__side">{badge}</span>
                      </span>
                    )}
                  </li>
                );
              })}
            </ul>
          )}
        </section>

        <section className="flxcm-card">
          <div className="flxcm-card__head">
            <h2>{t('Payment methods')}</h2>
          </div>
          <BarsList items={methods} emptyText={t('No sales in this period.')} />
        </section>
      </div>

      {quickLinks.length > 0 ? (
        <nav className="flxcm-quick" aria-label={t('Store')}>
          {quickLinks.map((l) => (
            <a key={l.href} className="flxcm-quick__a" href={l.href}>
              {l.icon}
              <span>{l.label}</span>
            </a>
          ))}
        </nav>
      ) : null}
    </div>
  );
}

function Customers({
  t,
  num,
  manage,
  adminUrl,
  q,
  initialSearch,
  tell,
  open,
}: {
  t: Translate;
  num: (n: number) => string;
  manage: boolean;
  adminUrl?: string;
  q: URLSearchParams;
  initialSearch: string;
  tell: (kind: 'ok' | 'bad', text: string) => void;
  open: (id: string) => void;
}) {
  const { client, scope } = useGraphQL();
  const qc = useQueryClient();
  const [segment, setSegment] = useState<string>(q.get('segment')?.toUpperCase() || 'ALL');
  const [search, setSearch] = useState(initialSearch || q.get('s') || '');
  const [orderby, setOrderby] = useState<'LAST_AT' | 'TOTAL' | 'ORDERS' | 'NAME'>(
    (['LAST_AT', 'TOTAL', 'ORDERS', 'NAME'].includes(q.get('orderby')?.toUpperCase() || '')
      ? q.get('orderby')?.toUpperCase()
      : 'LAST_AT') as 'LAST_AT' | 'TOTAL' | 'ORDERS' | 'NAME',
  );
  const [order, setOrder] = useState<'ASC' | 'DESC'>(q.get('order')?.toUpperCase() === 'ASC' ? 'ASC' : 'DESC');
  const [cursors, setCursors] = useState<Array<string | null>>([null]);
  const [progress, setProgress] = useState('');
  const [exporting, setExporting] = useState(false);
  const [savedNoteId, setSavedNoteId] = useState<string | null>(null);

  const after = cursors[cursors.length - 1] ?? null;
  const list = useFlxQuery(
    FlxCommerceCustomers,
    {
      first: 25,
      after,
      search: search || null,
      segment: segment as 'ALL' | 'REPEAT' | 'VIP' | 'LAPSED' | 'NEW',
      orderby,
      order,
    },
    { placeholderData: (old) => old },
  );
  const rebuild = useFlxMutation(FlxCommerceCustomersRebuild);
  const saveNote = useFlxMutation(FlxCommerceCustomerNote);

  const resetPaging = () => setCursors([null]);
  const sortBy = (col: 'LAST_AT' | 'TOTAL' | 'ORDERS' | 'NAME') => {
    if (orderby === col) {
      setOrder(order === 'DESC' ? 'ASC' : 'DESC');
    } else {
      setOrderby(col);
      setOrder(col === 'NAME' ? 'ASC' : 'DESC');
    }
    resetPaging();
  };
  const sortLabel = (col: 'LAST_AT' | 'TOTAL' | 'ORDERS' | 'NAME', label: string) => (
    <button type="button" onClick={() => sortBy(col)}>
      {label + (orderby === col ? (order === 'DESC' ? ' ↓' : ' ↑') : '')}
    </button>
  );

  const runRebuild = async () => {
    try {
      let pageNo = 1;
      let done = false;
      while (!done && pageNo <= 500) {
        const res = (await rebuild.mutateAsync({ input: { page: pageNo } })).flxCommerceCustomersRebuild;
        const msg = payloadErr(res?.errors);
        if (msg) throw new Error(msg);
        setProgress(t('Scanning %1$s / %2$s', num(res?.scanned ?? 0), num(res?.total ?? 0)));
        done = Boolean(res?.done ?? true);
        pageNo = (res?.page ?? pageNo) + 1;
      }
      tell('ok', t('Done'));
      void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxCommerceCustomers) });
    } catch (e) {
      tell('bad', errText(e, t));
    } finally {
      setProgress('');
    }
  };

  const exportCsv = async () => {
    setExporting(true);
    try {
      type CustNode = NonNullable<FlxCommerceCustomersQuery['flxCommerceCustomers']>['nodes'][number];
      const all: CustNode[] = [];
      let cur: string | null = null;
      for (let i = 0; i < 200; i++) {
        const res: FlxCommerceCustomersQuery = await client.request(FlxCommerceCustomers, {
          first: 50,
          after: cur,
          search: search || null,
          segment: segment as 'ALL' | 'REPEAT' | 'VIP' | 'LAPSED' | 'NEW',
          orderby,
          order,
        });
        const conn = res.flxCommerceCustomers;
        if (!conn) break;
        all.push(...conn.nodes);
        if (!conn.pageInfo.hasNextPage) break;
        cur = conn.pageInfo.endCursor ?? null;
      }
      csvDownload(
        `customers-${new Date().toISOString().slice(0, 10)}.csv`,
        [t('Name'), t('E-mail'), t('Phone'), t('City'), t('Orders'), t('Total spent'), t('First order'), t('Last order'), t('Note')],
        all.map((c) => [
          c.name,
          c.email,
          c.phone,
          c.city,
          String(c.orders),
          String(c.total.amount),
          c.firstAt?.iso?.slice(0, 10) ?? '',
          c.lastAt?.iso?.slice(0, 10) ?? '',
          c.note,
        ]),
      );
    } catch (e) {
      tell('bad', errText(e, t));
    } finally {
      setExporting(false);
    }
  };

  const counts = list.data?.flxCommerceCustomerCounts;
  const conn = list.data?.flxCommerceCustomers;
  const rows = conn?.nodes ?? [];
  const hasNext = conn?.pageInfo?.hasNextPage ?? false;
  const endCursor = conn?.pageInfo?.endCursor ?? null;
  const repeatPct = counts?.all ? Math.round((counts.repeat / counts.all) * 100) : 0;

  const chips: [string, string, number | undefined][] = [
    ['ALL', t('All'), counts?.all],
    ['NEW', t('New (30 days)'), counts?.new],
    ['REPEAT', t('Repeat buyers'), counts?.repeat],
    ['VIP', t('Top 10% spenders'), counts?.vip],
    ['LAPSED', t('Lapsed (90 days)'), counts?.lapsed],
  ];

  const ordersBaseUrl = adminUrl ? adminLink(adminUrl, 'edit.php?post_type=shop_order') : '';

  return (
    <div>
      {progress ? (
        <p className="flxcm-progress" role="status">
          {progress}
        </p>
      ) : null}
      {manage && counts && !counts.built ? (
        <div className="flxcm-callout">
          <AlertTriangle size={18} className="flxcm-i" />
          <div>
            <strong>{t('Older orders are not scanned yet.')}</strong>{' '}
            {t('The list fills itself with new orders. To add older orders, press "Rebuild"; the scan runs in batches of 100.')}
          </div>
        </div>
      ) : null}

      <div className="flxcm-kpis">
        <Kpi icon={<Users size={18} />} label={t('Customers')} value={num(counts?.all ?? 0)} />
        <Kpi
          icon={<RefreshCw size={18} />}
          label={t('Repeat buyers')}
          value={num(counts?.repeat ?? 0)}
          hint={counts?.all ? `${repeatPct}%` : undefined}
        />
        <Kpi icon={<Coins size={18} />} label={t('Spend per customer')} value={money(counts?.ltv)} />
        <Kpi icon={<Clock size={18} />} label={t('No order in 90 days')} value={num(counts?.lapsed ?? 0)} />
      </div>

      <div className="flxcm-toolbar">
        <div className="flxcm-chips">
          {chips.map(([id, label, n]) => (
            <button
              key={id}
              type="button"
              className={segment === id ? 'is-on' : ''}
              onClick={() => {
                setSegment(id);
                resetPaging();
              }}
            >
              {label} {n !== undefined ? <span>{num(n)}</span> : null}
            </button>
          ))}
        </div>
        <div className="flxcm-actions">
          {manage ? (
            <>
              <button className="flxcm-btn" type="button" disabled={exporting} onClick={() => void exportCsv()}>
                <Download size={15} className="flxcm-i" /> {t('Download CSV')}
              </button>
              <button className="flxcm-btn" type="button" disabled={rebuild.isPending || progress !== ''} onClick={() => void runRebuild()}>
                <RefreshCw size={15} className="flxcm-i" /> {t('Rebuild')}
              </button>
            </>
          ) : null}
        </div>
      </div>

      <form
        className="flxcm-filters"
        onSubmit={(e: FormEvent<HTMLFormElement>) => {
          e.preventDefault();
          const data = new FormData(e.currentTarget);
          setSearch(String(data.get('s') ?? ''));
          resetPaging();
        }}
      >
        <span className="flxcm-search__box">
          <Search size={16} className="flxcm-i" />
          <input name="s" defaultValue={search} placeholder={t('Name, e-mail, phone, city')} aria-label={t('Search')} />
        </span>
        <button className="flxcm-btn" type="submit">
          {t('Apply')}
        </button>
      </form>

      {list.isLoading ? <Loading /> : null}
      {list.error ? <p className="flxcm-notice is-bad">{errText(list.error, t)}</p> : null}

      {!list.isLoading && rows.length === 0 ? (
        <div className="flxcm-card">
          <p className="flxcm-empty">{search ? t('No customer matches the search.') : t('No customers.')}</p>
        </div>
      ) : null}

      {rows.length > 0 ? (
        <div className="flxcm-card flxcm-card--flush">
          <table className="flxcm-table flxcm-table--customers">
            <thead>
              <tr>
                <th>{sortLabel('NAME', t('Customers'))}</th>
                <th className="num">{sortLabel('ORDERS', t('Orders'))}</th>
                <th className="num">{sortLabel('TOTAL', t('Total'))}</th>
                <th>{sortLabel('LAST_AT', t('Last order'))}</th>
                <th>{t('Note')}</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((r) => {
                const displayName = r.name.trim() || r.email;
                return (
                  <tr key={r.id}>
                    <td data-label={t('Customers')}>
                      <div className="flxcm-who">
                        <span className="flxcm-avatar" aria-hidden="true">
                          {initialOf(displayName)}
                        </span>
                        <span>
                          <span>
                            <button type="button" className="flxcm-link" onClick={() => open(r.id)}>
                              <strong>{displayName}</strong>
                            </button>{' '}
                            {!r.guest ? <span className="flxcm-badge">{t('Member')}</span> : null}{' '}
                            {r.orders >= 2 ? <span className="flxcm-badge is-ok">{t('Repeat')}</span> : null}
                          </span>
                          <small>
                            {r.email ? <a href={`mailto:${r.email}`}>{r.email}</a> : null}
                            {r.phone ? (
                              <>
                                {' · '}
                                <a href={`https://wa.me/${digitsOnly(r.phone)}`} target="_blank" rel="noopener noreferrer">
                                  {r.phone}
                                </a>
                              </>
                            ) : null}
                            {r.city ? ` · ${r.city}` : null}
                          </small>
                        </span>
                      </div>
                    </td>
                    <td className="num" data-label={t('Orders')}>
                      {ordersBaseUrl && r.email ? (
                        <a href={`${ordersBaseUrl}&s=${encodeURIComponent(r.email)}`}>{num(r.orders)}</a>
                      ) : (
                        num(r.orders)
                      )}
                    </td>
                    <td className="num" data-label={t('Total')}>
                      <strong>{money(r.total)}</strong>
                    </td>
                    <td data-label={t('Last order')}>{when(r.lastAt) || '—'}</td>
                    <td data-label={t('Note')}>
                      {manage ? (
                        <input
                          type="text"
                          className={'flxcm-note-input' + (savedNoteId === r.id ? ' is-saved' : '')}
                          defaultValue={r.note}
                          maxLength={2000}
                          placeholder={t('Add a note…')}
                          aria-label={`${t('Note')} — ${displayName}`}
                          onBlur={(e) => {
                            const val = e.currentTarget.value;
                            if (val === r.note) return;
                            saveNote.mutate(
                              { input: { id: r.id, note: val } },
                              {
                                onSuccess: (res) => {
                                  const msg = payloadErr(res.flxCommerceCustomerNote?.errors);
                                  if (msg) {
                                    tell('bad', msg);
                                    return;
                                  }
                                  setSavedNoteId(r.id);
                                  setTimeout(() => setSavedNoteId(null), 1500);
                                  void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxCommerceCustomers) });
                                },
                                onError: (err) => tell('bad', errText(err, t)),
                              },
                            );
                          }}
                        />
                      ) : (
                        r.note || '—'
                      )}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      ) : null}

      {cursors.length > 1 || hasNext ? (
        <div className="flxcm-pager">
          <span className="flxcm-muted">
            {t('Page %s', num(cursors.length))}
            {conn?.totalCount != null ? ` · ${num(conn.totalCount)}` : ''}
          </span>
          <div className="flxcm-pager__btns">
            <button
              type="button"
              className="flxcm-btn"
              disabled={cursors.length <= 1}
              onClick={() => setCursors((prev) => prev.slice(0, -1))}
            >
              {t('Previous')}
            </button>
            <button
              type="button"
              className="flxcm-btn"
              disabled={!hasNext || !endCursor}
              onClick={() => endCursor && setCursors((prev) => [...prev, endCursor])}
            >
              {t('Next')}
            </button>
          </div>
        </div>
      ) : null}
    </div>
  );
}

function Customer({
  t,
  num,
  manage,
  adminUrl,
  id,
  tell,
  back,
}: {
  t: Translate;
  num: (n: number) => string;
  manage: boolean;
  adminUrl?: string;
  id: string;
  tell: (kind: 'ok' | 'bad', text: string) => void;
  back: () => void;
}) {
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const q = useFlxQuery(FlxCommerceCustomer, { id });
  const noteMut = useFlxMutation(FlxCommerceCustomerNote);
  const [tl, setTl] = useState<'all' | 'orders' | 'paid' | 'notes'>('all');
  const row = q.data?.flxCommerceCustomer;

  if (q.isLoading) return <Loading />;
  if (q.error) return <p className="flxcm-notice is-bad">{errText(q.error, t)}</p>;
  if (!row) {
    return (
      <div>
        <button type="button" className="flxcm-btn flxcm-back" onClick={back}>
          <ArrowLeft size={15} className="flxcm-i" /> {t('Back')}
        </button>
        <p className="flxcm-empty">{t('No customers.')}</p>
      </div>
    );
  }

  const displayName = row.name.trim() || row.email;
  const firstTs = row.firstAt?.timestamp ?? 0;
  const lastTs = row.lastAt?.timestamp ?? 0;
  const gapDays = row.orders >= 2 && firstTs && lastTs && lastTs > firstTs ? Math.max(1, Math.round((lastTs - firstTs) / 86400 / (row.orders - 1))) : 0;
  const aov = row.orders > 0 ? moneyCalc(row.total.amount / row.orders, row.total.currency) : '—';
  const lapsed = lastTs > 0 && lastTs < Math.floor(Date.now() / 1000) - 90 * 86400;
  const ordersUrl = adminUrl && row.email ? `${adminLink(adminUrl, 'edit.php?post_type=shop_order')}&s=${encodeURIComponent(row.email)}` : '';

  const events = (row.timeline ?? []).filter((e) => {
    if (tl === 'orders') return e.type === 'order';
    if (tl === 'paid') return e.type === 'paid' || e.type === 'refund' || e.type === 'order_paid' || (e.type === 'order' && e.paid);
    if (tl === 'notes') return e.type === 'note' || e.type === 'customer_note';
    return true;
  });

  return (
    <div>
      <button type="button" className="flxcm-btn flxcm-back" onClick={back}>
        <ArrowLeft size={15} className="flxcm-i" /> {t('Customers')}
      </button>

      <header className="flxcm-card flxcm-hero">
        <span className="flxcm-avatar is-lg" aria-hidden="true">
          {initialOf(displayName)}
        </span>
        <div className="flxcm-hero__main">
          <h2 className="flxcm-hero__name">{displayName}</h2>
          <div className="flxcm-pills">
            <span className="flxcm-badge">{row.guest ? t('Guest') : t('Member')}</span>
            {row.orders >= 2 ? <span className="flxcm-badge is-ok">{t('Repeat buyers')}</span> : null}
            {lapsed ? <span className="flxcm-badge is-wait">{t('Lapsed')}</span> : null}
          </div>
          <p className="flxcm-hero__meta">
            {row.email ? (
              <a href={`mailto:${row.email}`}>
                <Mail size={14} className="flxcm-i" /> {row.email}
              </a>
            ) : null}
            {row.phone ? (
              <a href={`tel:+${digitsOnly(row.phone)}`}>
                <Phone size={14} className="flxcm-i" /> {row.phone}
              </a>
            ) : null}
            {row.city ? (
              <span>
                <MapPin size={14} className="flxcm-i" /> {row.city}
              </span>
            ) : null}
            {row.firstAt ? <span className="flxcm-muted">{t('Customer since %s', when(row.firstAt))}</span> : null}
          </p>
        </div>
        <div className="flxcm-hero__actions">
          {row.phone ? (
            <a className="flxcm-btn" href={`https://wa.me/${digitsOnly(row.phone)}`} target="_blank" rel="noopener noreferrer">
              <MessageCircle size={15} className="flxcm-i" /> {t('WhatsApp')}
            </a>
          ) : null}
          {row.email ? (
            <a className="flxcm-btn" href={`mailto:${row.email}`}>
              <Mail size={15} className="flxcm-i" /> {t('E-mail')}
            </a>
          ) : null}
          {ordersUrl ? (
            <a className="flxcm-btn" href={ordersUrl}>
              <ShoppingBag size={15} className="flxcm-i" /> {t('Orders')}
            </a>
          ) : null}
        </div>
      </header>

      <div className="flxcm-kpis flxcm-kpis--6">
        <Kpi icon={<Coins size={18} />} label={t('Total spent')} value={row.orders ? money(row.total) : '—'} />
        <Kpi
          icon={<ShoppingBag size={18} />}
          label={t('Orders')}
          value={num(row.orders)}
          hint={row.openOrders ? t('%s open orders', num(row.openOrders)) : undefined}
        />
        <Kpi icon={<Receipt size={18} />} label={t('Average order')} value={aov} />
        <Kpi icon={<Calendar size={18} />} label={t('First order')} value={row.firstAt ? when(row.firstAt) : '—'} />
        <Kpi
          icon={<Clock size={18} />}
          label={t('Last order')}
          value={row.lastAt ? row.lastAt.relative || when(row.lastAt) : '—'}
        />
        <Kpi
          icon={<RefreshCw size={18} />}
          label={t('Order frequency')}
          value={gapDays ? t('Every %s days', num(gapDays)) : '—'}
        />
      </div>

      <div className="flxcm-profile">
        <div className="flxcm-profile__main">
          <section className="flxcm-card">
            <div className="flxcm-card__head">
              <h2>
                <Clock size={16} className="flxcm-i" /> {t('Timeline')}
              </h2>
              <div className="flxcm-seg">
                {(
                  [
                    ['all', t('All')],
                    ['orders', t('Orders')],
                    ['paid', t('Payments')],
                    ['notes', t('Notes')],
                  ] as const
                ).map(([k, label]) => (
                  <button key={k} type="button" className={tl === k ? 'is-on' : ''} onClick={() => setTl(k)}>
                    {label}
                  </button>
                ))}
              </div>
            </div>
            {events.length === 0 ? (
              <p className="flxcm-empty">{t('Nothing in this filter.')}</p>
            ) : (
              <ol className="flxcm-timeline">
                {events.map((e, i) => (
                  <li key={`${e.type}-${i}`} className={`flxcm-ev is-${e.type}`}>
                    <span className="flxcm-ev__icon" aria-hidden="true">
                      {e.type === 'paid' ? <Check size={14} /> : e.type === 'order' ? <ShoppingBag size={14} /> : <StickyNote size={14} />}
                    </span>
                    <div className="flxcm-ev__body">
                      <div className="flxcm-ev__top">
                        <span className="flxcm-ev__type">{e.label}</span>
                        {e.paid ? <span className="flxcm-badge is-ok">{t('Payments')}</span> : null}
                        {e.at ? <time dateTime={e.at.iso}>{e.at.label}</time> : null}
                      </div>
                      {e.title ? (
                        <p className="flxcm-ev__title">
                          {e.url ? <a href={e.url}>{e.title}</a> : e.title}
                        </p>
                      ) : null}
                      {e.text ? <p className="flxcm-ev__text">{e.text}</p> : null}
                    </div>
                  </li>
                ))}
              </ol>
            )}
          </section>

          <section className="flxcm-card">
            <div className="flxcm-card__head">
              <h2>
                <ShoppingBag size={16} className="flxcm-i" /> {t('Recent orders')}
              </h2>
            </div>
            {(row.recentOrders ?? []).length === 0 ? (
              <p className="flxcm-empty">{t('No orders.')}</p>
            ) : (
              <ul className="flxcm-list">
                {row.recentOrders.map((o) => {
                  const badgeCls = o.paid ? 'is-ok' : o.status === 'cancelled' || o.status === 'failed' ? 'is-bad' : 'is-wait';
                  const inner = (
                    <>
                      <span className="flxcm-list__main">
                        <strong>{t('Order #%s', o.number)}</strong>
                        <small>{when(o.createdAt)}</small>
                      </span>
                      <span className="flxcm-list__side">
                        <strong>{money(o.total)}</strong>
                        <span className={`flxcm-badge ${badgeCls}`}>{o.statusLabel}</span>
                      </span>
                    </>
                  );
                  return (
                    <li key={o.id}>
                      {o.url ? <a href={o.url}>{inner}</a> : <span className="flxcm-li">{inner}</span>}
                    </li>
                  );
                })}
              </ul>
            )}
          </section>
        </div>

        <aside className="flxcm-profile__side">
          <section className="flxcm-card">
            <div className="flxcm-card__head">
              <h2>
                <User size={16} className="flxcm-i" /> {t('Details')}
              </h2>
            </div>
            <dl className="flxcm-dl">
              <dt>{t('E-mail')}</dt>
              <dd>{row.email || '—'}</dd>
              <dt>{t('Phone')}</dt>
              <dd>{row.phone || '—'}</dd>
              <dt>{t('City')}</dt>
              <dd>{row.city || '—'}</dd>
              <dt>{t('Account')}</dt>
              <dd>{row.userId > 0 ? t('Registered member') : t('Guest')}</dd>
            </dl>
            {manage ? (
              <form
                key={row.note}
                className="flxcm-note-form"
                onSubmit={(e: FormEvent<HTMLFormElement>) => {
                  e.preventDefault();
                  const val = String(new FormData(e.currentTarget).get('note') ?? '');
                  noteMut.mutate(
                    { input: { id, note: val } },
                    {
                      onSuccess: (res) => {
                        const msg = payloadErr(res.flxCommerceCustomerNote?.errors);
                        if (msg) {
                          tell('bad', msg);
                          return;
                        }
                        tell('ok', t('Saved'));
                        void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxCommerceCustomer, { id }) });
                        void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxCommerceCustomers) });
                      },
                      onError: (err) => tell('bad', errText(err, t)),
                    },
                  );
                }}
              >
                <label className="flxcm-field__label" htmlFor="flxcm-cust-note">
                  {t('Store note')}
                </label>
                <textarea id="flxcm-cust-note" name="note" defaultValue={row.note} maxLength={2000} />
                <button className="flxcm-btn is-primary" type="submit" disabled={noteMut.isPending}>
                  {t('Save note')}
                </button>
              </form>
            ) : row.note ? (
              <div>
                <div className="flxcm-field__label">{t('Store note')}</div>
                <p>{row.note}</p>
              </div>
            ) : null}
          </section>

          {(row.addresses ?? []).length > 0 ? (
            <section className="flxcm-card">
              <div className="flxcm-card__head">
                <h2>
                  <MapPin size={16} className="flxcm-i" /> {t('Addresses')}
                </h2>
              </div>
              <div className="flxcm-addr">
                {row.addresses.map((a) => {
                  const lines = a.text.split(/<br\s*\/?>|\n/i).filter(Boolean);
                  return (
                    <div key={a.kind}>
                      <h3>{a.kind === 'shipping' ? t('Shipping') : t('Billing')}</h3>
                      <address>
                        {lines.map((line, idx) => (
                          <span key={idx}>
                            {line}
                            {idx < lines.length - 1 ? <br /> : null}
                          </span>
                        ))}
                      </address>
                    </div>
                  );
                })}
              </div>
              <p className="flxcm-muted">{t('From the latest order')}</p>
            </section>
          ) : null}

          {(row.products ?? []).length > 0 ? (
            <section className="flxcm-card">
              <div className="flxcm-card__head">
                <h2>
                  <Package size={16} className="flxcm-i" /> {t('Frequently bought')}
                </h2>
              </div>
              <ul className="flxcm-list">
                {row.products.map((p) => (
                  <li key={p.productId || p.name}>
                    <span className="flxcm-li">
                      <span className="flxcm-list__main">
                        <strong>{p.name}</strong>
                        <small>
                          {num(p.quantity)} {t('pcs')}
                        </small>
                      </span>
                      <span className="flxcm-list__side">{money(p.total)}</span>
                    </span>
                  </li>
                ))}
              </ul>
            </section>
          ) : null}
        </aside>
      </div>
    </div>
  );
}

function Subscriptions({
  t,
  num,
  manage,
  q,
  tell,
  openCustomerByEmail,
}: {
  t: Translate;
  num: (n: number) => string;
  manage: boolean;
  q: URLSearchParams;
  tell: (kind: 'ok' | 'bad', text: string) => void;
  openCustomerByEmail: (email: string) => void;
}) {
  const [status, setStatus] = useState(q.get('status')?.toUpperCase() || '');
  const [search, setSearch] = useState(q.get('s') || '');
  const [cursors, setCursors] = useState<Array<string | null>>([null]);
  const [endId, setEndId] = useState<string | null>(null);

  const after = cursors[cursors.length - 1] ?? null;
  const list = useFlxQuery(
    FlxCommerceSubscriptions,
    {
      first: 25,
      after,
      search: search || null,
      status: (status || null) as 'PENDING' | 'ACTIVE' | 'PAST_DUE' | 'CANCELLED' | 'EXPIRED' | null,
    },
    { placeholderData: (old) => old },
  );
  const renew = useFlxMutation(FlxCommerceSubscriptionRenew);
  const cancel = useFlxMutation(FlxCommerceSubscriptionCancel);
  const end = useFlxMutation(FlxCommerceSubscriptionEnd);
  const resume = useFlxMutation(FlxCommerceSubscriptionResume);
  const { scope } = useGraphQL();
  const qc = useQueryClient();

  const refresh = () => void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxCommerceSubscriptions) });

  const counts = list.data?.flxCommerceSubscriptionCounts;
  const conn = list.data?.flxCommerceSubscriptions;
  const rows = conn?.nodes ?? [];
  const hasNext = conn?.pageInfo?.hasNextPage ?? false;
  const endCursor = conn?.pageInfo?.endCursor ?? null;

  const chips: [string, string, number | undefined][] = [
    ['', t('All'), counts?.all],
    ['ACTIVE', t('Active'), counts?.active],
    ['PAST_DUE', t('Payment overdue'), counts?.pastDue],
    ['CANCELLED', t('Cancelled'), counts?.cancelled],
    ['PENDING', t('Awaiting first payment'), counts?.pending],
    ['EXPIRED', t('Ended'), counts?.expired],
  ];

  const statusMeta = (st: string): { label: string; cls: string } => {
    switch (st) {
      case 'ACTIVE':
        return { label: t('Active'), cls: 'is-ok' };
      case 'PAST_DUE':
        return { label: t('Payment overdue'), cls: 'is-bad' };
      case 'CANCELLED':
        return { label: t('Cancelled'), cls: 'is-wait' };
      case 'PENDING':
        return { label: t('Awaiting first payment'), cls: 'is-wait' };
      default:
        return { label: t('Ended'), cls: '' };
    }
  };

  return (
    <div>
      <div className="flxcm-kpis">
        <Kpi icon={<RefreshCw size={18} />} label={t('Active')} value={num(counts?.active ?? 0)} />
        <Kpi
          icon={<Coins size={18} />}
          label={t('Monthly recurring revenue')}
          value={money(counts?.mrr)}
          hint={t('Yearly plans count as 1/12')}
        />
        <Kpi icon={<Calendar size={18} />} label={t('Renewing in 30 days')} value={num(counts?.due ?? 0)} />
        <Kpi icon={<AlertTriangle size={18} />} label={t('Payment overdue')} value={num(counts?.pastDue ?? 0)} />
      </div>

      <div className="flxcm-toolbar">
        <div className="flxcm-chips">
          {chips.map(([id, label, n]) => (
            <button
              key={id || 'all'}
              type="button"
              className={status === id ? 'is-on' : ''}
              onClick={() => {
                setStatus(id);
                setCursors([null]);
              }}
            >
              {label} {n !== undefined ? <span>{num(n)}</span> : null}
            </button>
          ))}
        </div>
        <form
          className="flxcm-filters"
          style={{ margin: 0 }}
          onSubmit={(e: FormEvent<HTMLFormElement>) => {
            e.preventDefault();
            setSearch(String(new FormData(e.currentTarget).get('s') ?? ''));
            setCursors([null]);
          }}
        >
          <span className="flxcm-search__box">
            <Search size={16} className="flxcm-i" />
            <input name="s" defaultValue={search} placeholder={t('Name, e-mail, product, no.')} aria-label={t('Search')} />
          </span>
          <button className="flxcm-btn" type="submit">
            {t('Apply')}
          </button>
        </form>
      </div>

      {list.isLoading ? <Loading /> : null}
      {list.error ? <p className="flxcm-notice is-bad">{errText(list.error, t)}</p> : null}

      {!list.isLoading && rows.length === 0 ? (
        <div className="flxcm-card">
          <p className="flxcm-empty">{t('No subscriptions.')}</p>
        </div>
      ) : null}

      {rows.length > 0 ? (
        <div className="flxcm-card flxcm-card--flush">
          <table className="flxcm-table flxcm-table--subs">
            <thead>
              <tr>
                <th>{t('Customer')}</th>
                <th>{t('Product')}</th>
                <th className="num">{t('Amount')}</th>
                <th>{t('Status')}</th>
                <th>{t('Renewal / end')}</th>
                <th>{t('Orders')}</th>
                {manage ? (
                  <th>
                    <span className="screen-reader-text">{t('Actions')}</span>
                  </th>
                ) : null}
              </tr>
            </thead>
            <tbody>
              {rows.map((r) => {
                const displayName = r.name.trim() || r.email;
                const st = r.status.toUpperCase();
                const sm = statusMeta(st);
                const whenText = st === 'EXPIRED' ? when(r.endedAt) : when(r.periodEnd);
                const busy = renew.isPending || cancel.isPending || end.isPending || resume.isPending;
                return (
                  <tr key={r.id}>
                    <td data-label={t('Customer')}>
                      <div className="flxcm-who">
                        <span className="flxcm-avatar" aria-hidden="true">
                          {initialOf(displayName)}
                        </span>
                        <span>
                          <button type="button" className="flxcm-link" onClick={() => openCustomerByEmail(r.email)}>
                            <strong>{displayName}</strong>
                          </button>
                          <small>
                            #{r.id} · <a href={`mailto:${r.email}`}>{r.email}</a>
                          </small>
                        </span>
                      </div>
                    </td>
                    <td data-label={t('Product')}>
                      {r.productUrl ? (
                        <a href={r.productUrl} target="_blank" rel="noopener noreferrer">
                          {r.productName}
                        </a>
                      ) : (
                        r.productName
                      )}{' '}
                      <small className="flxcm-muted">· {r.period === 'year' ? t('Yearly') : t('Monthly')}</small>
                    </td>
                    <td className="num" data-label={t('Amount')}>
                      <strong>{money(r.amount)}</strong>
                    </td>
                    <td data-label={t('Status')}>
                      <span className={`flxcm-badge ${sm.cls}`}>{sm.label}</span>
                    </td>
                    <td data-label={t('Renewal / end')}>
                      {whenText || '—'}
                      {r.cardLabel && (st === 'ACTIVE' || st === 'PAST_DUE') ? (
                        <div>
                          <small className="flxcm-muted">{t('Auto: %s', r.cardLabel)}</small>
                        </div>
                      ) : null}
                    </td>
                    <td data-label={t('Orders')}>
                      <div>
                        {r.parentOrderId ? (
                          <span>
                            #{r.parentOrderId} <small className="flxcm-muted">{t('first')}</small>
                          </span>
                        ) : null}
                        {r.renewalOrderId ? (
                          <div>
                            #{r.renewalOrderId} <small className="flxcm-muted">{t('awaiting')}</small>
                          </div>
                        ) : null}
                        {r.renewals > 0 ? (
                          <div>
                            <small className="flxcm-muted">{t(r.renewals === 1 ? '%d renewal' : '%d renewals', r.renewals)}</small>
                          </div>
                        ) : null}
                        {!r.parentOrderId && !r.renewalOrderId && !r.renewals ? '—' : null}
                      </div>
                    </td>
                    {manage ? (
                      <td data-label={t('Actions')}>
                        <div className="flxcm-actions">
                          {(st === 'ACTIVE' || st === 'PAST_DUE') && !r.renewalOrderId ? (
                            <button
                              type="button"
                              className="flxcm-btn is-small"
                              disabled={busy}
                              onClick={() =>
                                renew.mutate(
                                  { input: { id: r.id } },
                                  {
                                    onSuccess: (res) => {
                                      const msg = payloadErr(res.flxCommerceSubscriptionRenew?.errors);
                                      if (msg) return tell('bad', msg);
                                      tell('ok', t('Renewal order created and the customer was notified.'));
                                      refresh();
                                    },
                                    onError: (e) => tell('bad', errText(e, t)),
                                  },
                                )
                              }
                            >
                              {t('Create renewal order')}
                            </button>
                          ) : null}
                          {st === 'ACTIVE' || st === 'PAST_DUE' ? (
                            <button
                              type="button"
                              className="flxcm-btn is-small"
                              disabled={busy}
                              onClick={() =>
                                cancel.mutate(
                                  { input: { id: r.id } },
                                  {
                                    onSuccess: (res) => {
                                      const msg = payloadErr(res.flxCommerceSubscriptionCancel?.errors);
                                      if (msg) return tell('bad', msg);
                                      tell('ok', t('Renewal turned off; the subscription stays valid until the end of the period.'));
                                      refresh();
                                    },
                                    onError: (e) => tell('bad', errText(e, t)),
                                  },
                                )
                              }
                            >
                              {t('Cancel at period end')}
                            </button>
                          ) : null}
                          {st === 'CANCELLED' ? (
                            <button
                              type="button"
                              className="flxcm-btn is-small"
                              disabled={busy}
                              onClick={() =>
                                resume.mutate(
                                  { input: { id: r.id } },
                                  {
                                    onSuccess: (res) => {
                                      const msg = payloadErr(res.flxCommerceSubscriptionResume?.errors);
                                      if (msg) return tell('bad', msg);
                                      tell('ok', t('Renewal is back on.'));
                                      refresh();
                                    },
                                    onError: (e) => tell('bad', errText(e, t)),
                                  },
                                )
                              }
                            >
                              {t('Turn renewal back on')}
                            </button>
                          ) : null}
                          {st !== 'EXPIRED' ? (
                            <button type="button" className="flxcm-btn is-small" disabled={busy} onClick={() => setEndId(r.id)}>
                              {t('End now')}
                            </button>
                          ) : null}
                        </div>
                      </td>
                    ) : null}
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      ) : null}

      {cursors.length > 1 || hasNext ? (
        <div className="flxcm-pager">
          <span className="flxcm-muted">
            {t('Page %s', num(cursors.length))}
            {conn?.totalCount != null ? ` · ${num(conn.totalCount)}` : ''}
          </span>
          <div className="flxcm-pager__btns">
            <button
              type="button"
              className="flxcm-btn"
              disabled={cursors.length <= 1}
              onClick={() => setCursors((prev) => prev.slice(0, -1))}
            >
              {t('Previous')}
            </button>
            <button
              type="button"
              className="flxcm-btn"
              disabled={!hasNext || !endCursor}
              onClick={() => endCursor && setCursors((prev) => [...prev, endCursor])}
            >
              {t('Next')}
            </button>
          </div>
        </div>
      ) : null}

      {endId ? (
        <Modal title={t('End now')} onClose={() => setEndId(null)}>
          <p>{t('End this subscription now? Access closes immediately.')}</p>
          <div className="flxcm-modal-actions">
            <button type="button" className="flxcm-btn" onClick={() => setEndId(null)}>
              {t('Cancel')}
            </button>
            <button
              type="button"
              className="flxcm-btn is-danger"
              disabled={end.isPending}
              onClick={() => {
                const target = endId;
                setEndId(null);
                end.mutate(
                  { input: { id: target } },
                  {
                    onSuccess: (res) => {
                      const msg = payloadErr(res.flxCommerceSubscriptionEnd?.errors);
                      if (msg) return tell('bad', msg);
                      tell('ok', t('Subscription ended.'));
                      refresh();
                    },
                    onError: (e) => tell('bad', errText(e, t)),
                  },
                );
              }}
            >
              {t('End now')}
            </button>
          </div>
        </Modal>
      ) : null}
    </div>
  );
}

function Reports({
  t,
  num,
  q,
  forcedRange,
}: {
  t: Translate;
  num: (n: number) => string;
  q: URLSearchParams;
  forcedRange: { from: string; to: string } | null;
}) {
  const { client } = useGraphQL();
  const [preset, setPreset] = useState(q.get('preset')?.toUpperCase() || 'D30');
  const [from, setFrom] = useState(forcedRange?.from ?? q.get('from') ?? '');
  const [to, setTo] = useState(forcedRange?.to ?? q.get('to') ?? '');
  const [exporting, setExporting] = useState(false);

  useEffect(() => {
    if (forcedRange) {
      setFrom(forcedRange.from);
      setTo(forcedRange.to);
    }
  }, [forcedRange]);

  const vars = {
    preset: (from && to ? null : preset) as FlxCommerceReportPreset | null,
    from: from || null,
    to: to || null,
  };
  const report = useFlxQuery(FlxCommerceReport, vars);
  const days = useFlxQuery(FlxCommerceReportDays, { ...vars, first: 50 });
  const products = useFlxQuery(FlxCommerceReportProducts, { ...vars, first: 50 });
  const categories = useFlxQuery(FlxCommerceReportCategories, vars);
  const methods = useFlxQuery(FlxCommerceReportMethods, vars);

  const [allDays, setAllDays] = useState<NonNullable<FlxCommerceReportDaysQuery['flxCommerceReportDays']>['nodes']>([]);
  useEffect(() => {
    let active = true;
    const initialNodes = days.data?.flxCommerceReportDays?.nodes ?? [];
    setAllDays(initialNodes);
    const hasMore = days.data?.flxCommerceReportDays?.pageInfo?.hasNextPage ?? false;
    const curStart = days.data?.flxCommerceReportDays?.pageInfo?.endCursor ?? null;
    if (!hasMore || !curStart) return;
    void (async () => {
      const acc = [...initialNodes];
      let cur: string | null = curStart;
      for (let i = 0; i < 8; i++) {
        const res: FlxCommerceReportDaysQuery = await client.request(FlxCommerceReportDays, { ...vars, first: 50, after: cur });
        const conn = res.flxCommerceReportDays;
        if (!conn || !active) break;
        acc.push(...conn.nodes);
        if (!conn.pageInfo.hasNextPage || !conn.pageInfo.endCursor) break;
        cur = conn.pageInfo.endCursor;
      }
      if (active) setAllDays(acc);
    })();
    return () => {
      active = false;
    };
  }, [days.data, client, vars.preset, vars.from, vars.to]);

  const s = report.data?.flxCommerceReport;
  const prodNodes = products.data?.flxCommerceReportProducts?.nodes ?? [];
  const catNodes = categories.data?.flxCommerceReportCategories?.nodes ?? [];
  const methNodes = methods.data?.flxCommerceReportMethods?.nodes ?? [];

  const presets: [string, string][] = [
    ['D7', t('7 days')],
    ['D30', t('30 days')],
    ['D90', t('90 days')],
    ['MONTH', t('This month')],
    ['LAST_MONTH', t('Last month')],
    ['YEAR', t('This year')],
  ];

  const exportCsv = async () => {
    if (!s) return;
    setExporting(true);
    try {
      const allProds = [...prodNodes];
      let cur = products.data?.flxCommerceReportProducts?.pageInfo?.hasNextPage
        ? products.data.flxCommerceReportProducts.pageInfo.endCursor ?? null
        : null;
      let i = 0;
      while (cur && i++ < 20) {
        const res: FlxCommerceReportProductsQuery = await client.request(FlxCommerceReportProducts, { ...vars, first: 50, after: cur });
        const conn = res.flxCommerceReportProducts;
        if (!conn) break;
        allProds.push(...conn.nodes);
        cur = conn.pageInfo.hasNextPage ? conn.pageInfo.endCursor ?? null : null;
      }

      const rows: string[][] = [
        [t('Summary'), `${s.from} – ${s.to}`],
        [t('Gross revenue'), s.gross.amount.toFixed(2)],
        [t('Refunds'), s.refunds.amount.toFixed(2)],
        [t('Net revenue'), s.revenue.amount.toFixed(2)],
        [t('Shipping income'), s.shipping.amount.toFixed(2)],
        [t('Tax'), s.tax.amount.toFixed(2)],
        [t('Discount'), s.discount.amount.toFixed(2)],
        [t('Orders'), String(s.orders)],
        [t('Items sold'), String(s.items)],
        [t('Average order'), s.aov.amount.toFixed(2)],
        [],
        [t('Day'), t('Net revenue'), t('Orders')],
        ...allDays.map((d) => [d.date, d.revenue.amount.toFixed(2), String(d.orders)]),
        [],
        [t('Product'), t('Qty'), t('Net sales')],
        ...allProds.map((p) => [p.name, String(p.quantity), p.total.amount.toFixed(2)]),
        [],
        [t('Category'), t('Qty'), t('Net sales')],
        ...catNodes.map((c) => [c.name, String(c.quantity), c.total.amount.toFixed(2)]),
        [],
        [t('Payment method'), t('Orders'), t('Net sales')],
        ...methNodes.map((m) => [m.title, String(m.orders), m.total.amount.toFixed(2)]),
      ];
      csvDownload(`sales-${s.from}_${s.to}.csv`, rows[0]!, rows.slice(1));
    } finally {
      setExporting(false);
    }
  };

  const prodSum = prodNodes.reduce((acc, p) => acc + p.total.amount, 0);

  return (
    <div>
      <div className="flxcm-toolbar">
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
        <button className="flxcm-btn" type="button" disabled={!s || exporting} onClick={() => void exportCsv()}>
          <Download size={15} className="flxcm-i" /> {t('Download CSV')}
        </button>
      </div>

      <form
        className="flxcm-filters"
        onSubmit={(e: FormEvent<HTMLFormElement>) => {
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
        <button className="flxcm-btn is-primary" type="submit">
          {t('Show')}
        </button>
      </form>

      {report.isLoading ? <Loading /> : null}
      {report.error ? <p className="flxcm-notice is-bad">{errText(report.error, t)}</p> : null}

      <div className="flxcm-kpis flxcm-kpis--6">
        <Kpi icon={<Coins size={18} />} label={t('Net revenue')} value={money(s?.revenue)} delta={s?.revenueDelta} />
        <Kpi icon={<Receipt size={18} />} label={t('Orders')} value={num(s?.orders ?? 0)} delta={s?.ordersDelta} />
        <Kpi icon={<Package size={18} />} label={t('Items sold')} value={num(s?.items ?? 0)} delta={s?.itemsDelta} />
        <Kpi icon={<ShoppingBag size={18} />} label={t('Average order')} value={money(s?.aov)} delta={s?.aovDelta} />
        <Kpi icon={<RefreshCw size={18} />} label={t('Refunds')} value={money(s?.refunds)} />
        <Kpi icon={<Truck size={18} />} label={t('Shipping income')} value={money(s?.shipping)} />
      </div>

      <div className="flxcm-grid">
        <section className="flxcm-card flxcm-span-3">
          <div className="flxcm-card__head">
            <h2>{t('Daily revenue')}</h2>
            {s?.from && s?.to ? (
              <span className="flxcm-muted">
                {s.from} – {s.to}
              </span>
            ) : null}
          </div>
          {allDays.length === 0 || !s?.orders ? (
            <p className="flxcm-empty">{t('No paid orders in this period.')}</p>
          ) : (
            <RevenueChart series={allDays} t={t} num={num} />
          )}
        </section>

        <section className="flxcm-card flxcm-span-2 flxcm-card--flush">
          <div className="flxcm-card__head">
            <h2>{t('Products')}</h2>
          </div>
          {prodNodes.length === 0 ? (
            <p className="flxcm-empty">{t('No sales in this period.')}</p>
          ) : (
            <table className="flxcm-table">
              <thead>
                <tr>
                  <th>{t('Product')}</th>
                  <th className="num">{t('Qty')}</th>
                  <th className="num">{t('Net sales')}</th>
                  <th className="num">{t('Share')}</th>
                </tr>
              </thead>
              <tbody>
                {prodNodes.map((p) => (
                  <tr key={p.productId || p.name}>
                    <td data-label={t('Product')}>{p.name}</td>
                    <td className="num" data-label={t('Qty')}>
                      {num(p.quantity)}
                    </td>
                    <td className="num" data-label={t('Net sales')}>
                      {money(p.total)}
                    </td>
                    <td className="num" data-label={t('Share')}>
                      {prodSum > 0 ? `${((p.total.amount / prodSum) * 100).toFixed(1)}%` : '—'}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </section>

        <div className="flxcm-stack">
          <section className="flxcm-card">
            <div className="flxcm-card__head">
              <h2>{t('Categories')}</h2>
            </div>
            <BarsList
              items={catNodes.slice(0, 8).map((c) => ({
                key: c.name,
                label: c.name,
                amount: c.total.amount,
                formatted: c.total.formatted,
                sub: `${num(c.quantity)} ${t('pcs')}`,
              }))}
              emptyText={t('No sales in this period.')}
            />
          </section>

          <section className="flxcm-card">
            <div className="flxcm-card__head">
              <h2>{t('Payment methods')}</h2>
            </div>
            <BarsList
              items={methNodes.map((m) => ({
                key: m.title,
                label: m.title,
                amount: m.total.amount,
                formatted: m.total.formatted,
                sub: `${num(m.orders)} ${t('orders')}`,
              }))}
              emptyText={t('No sales in this period.')}
            />
          </section>
        </div>
      </div>
    </div>
  );
}

function Settings({
  t,
  adminUrl,
  tell,
}: {
  t: Translate;
  adminUrl?: string;
  tell: (kind: 'ok' | 'bad', text: string) => void;
}) {
  const q = useFlxQuery(FlxCommerceSettings, {});
  const save = useFlxMutation(FlxCommerceSettingsSave);
  const bank = useFlxMutation(FlxCommerceBankSettingsSave);
  const run = useFlxMutation(FlxCommerceRenewalsRun);
  const [copied, setCopied] = useState(false);

  const s = q.data?.flxCommerceSettings;
  const b = q.data?.flxCommerceBankSettings;
  const status = q.data?.flxCommerceRenewalStatus;
  if (q.isLoading) return <Loading />;
  if (q.error || !s || !b) return <p className="flxcm-notice is-bad">{errText(q.error, t)}</p>;

  const copyFeedUrl = () => {
    if (!s.feedUrl || !navigator.clipboard) return;
    void navigator.clipboard.writeText(s.feedUrl).then(() => {
      setCopied(true);
      tell('ok', t('Copied'));
      setTimeout(() => setCopied(false), 1500);
    });
  };

  return (
    <div className="flxcm-settings">
      <form
        key={JSON.stringify(s)}
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
            {
              onSuccess: (res) => {
                const msg = payloadErr(res.flxCommerceSettingsSave?.errors);
                if (msg) return tell('bad', msg);
                tell('ok', t('Saved'));
                void q.refetch();
              },
              onError: (err) => tell('bad', errText(err, t)),
            },
          );
        }}
      >
        <section className="flxcm-card">
          <h2>
            <TrendingUp size={18} className="flxcm-i" /> {t('Dashboard and reports')}
          </h2>
          <fieldset className="flxcm-field">
            <legend className="flxcm-field__label">{t('Order statuses counted as revenue')}</legend>
            <div className="flxcm-checks">
              {(s.orderStatuses ?? []).map((st) => (
                <label key={st.slug}>
                  <input type="checkbox" name="paid" value={st.slug} defaultChecked={s.paidStatuses.includes(st.slug)} /> {st.label}
                </label>
              ))}
            </div>
            <span className="flxcm-field__hint">{t('On-hold (bank transfer) orders count once the payment is confirmed.')}</span>
          </fieldset>
          {s.donateCart ? (
            <Toggle
              name="excludeDonations"
              label={t('Leave donation orders out of the store statistics')}
              hint={t('Flux Donate is installed: orders with only donations stay in the Donations dashboard.')}
              on={s.excludeDonations}
            />
          ) : null}
          <Num
            name="lowStock"
            label={t('Low stock threshold')}
            hint={t('Products whose stock falls to this number or below are listed on the Overview.')}
            value={s.lowStock}
          />
        </section>

        <section className="flxcm-card">
          <h2>
            <ShoppingBag size={18} className="flxcm-i" /> {t('Cart and checkout')}
          </h2>
          <Toggle name="hideCompany" label={t('Hide the company name field')} on={s.hideCompany} />
          <Toggle name="hideAddress2" label={t('Hide the second address line')} on={s.hideAddress2} />
          <Toggle
            name="virtualNoAddress"
            label={t('Do not ask for an address when the cart only has virtual/downloadable products')}
            hint={t('On the classic checkout. Country, name, e-mail and phone stay.')}
            on={s.virtualNoAddress}
          />
          <Toggle name="orderNotes" label={t('Order notes field')} on={s.orderNotes} />
          <Toggle name="stickyPay" label={t('On mobile, keep the Place order button fixed at the bottom')} on={s.stickyPay} />
          <Toggle
            name="freeShipBar"
            label={t('Free shipping bar')}
            hint={t('"Add X more for free shipping" in the cart and at checkout.')}
            on={s.freeShipBar}
          />
          <Num
            name="freeShipAmount"
            label={t('Free shipping amount')}
            hint={`${t('Empty or 0 = the minimum amount of WooCommerce\'s "Free shipping" method.')} (${t('WooCommerce free shipping minimum')}: ${money(s.freeShipThreshold)})`}
            value={s.freeShipAmount}
            step="0.01"
          />
        </section>

        <section className="flxcm-card">
          <h2>
            <Landmark size={18} className="flxcm-i" /> {t('Bank transfer')}
          </h2>
          {s.donateHavale ? (
            <Toggle
              name="deferToDonate"
              label={t("Hide this method when Flux Donate's bank transfer is on")}
              hint={t('So the checkout does not show two separate "Bank transfer" options.')}
              on={s.deferToDonate}
            />
          ) : null}
          <Num name="receiptMaxMb" label={t('Largest receipt (MB)')} value={s.receiptMaxMb} />
          <TextInput
            name="receiptNotify"
            type="email"
            label={t('Receipt notification e-mail')}
            hint={t('Empty = the site admin e-mail.')}
            value={s.receiptNotify}
          />
          <Num
            name="cancelAfterDays"
            label={t('Cancel unpaid bank transfers after (days)')}
            hint={t('0 = never cancel. Orders with a receipt are not cancelled; stock is restored.')}
            value={s.cancelAfterDays}
          />
        </section>

        <section className="flxcm-card">
          <h2>
            <RefreshCw size={18} className="flxcm-i" /> {t('Subscriptions')}
          </h2>
          <p className="flxcm-muted">
            {t('Tick "Subscription" on a simple product and enter a monthly and/or yearly price. If the first payment is made with a card provider that can store cards, renewals are charged to that card automatically; otherwise they are paid by bank transfer or through the payment link.')}
          </p>
          <p className="flxcm-muted">
            <strong>{t('Automatic card renewal:')}</strong> {(s.cardProviders ?? []).join(', ') || t('No card provider yet.')}
          </p>
          <Num
            name="subRenewDays"
            label={t('Create the renewal order (days before the period ends)')}
            hint={t('The customer gets the reminder e-mail with the bank details or payment link at the same time, and once more on the last day if unpaid.')}
            value={s.subRenewDays}
          />
          <Num
            name="subGraceDays"
            label={t('Grace period after the period ends (days)')}
            hint={t('Access stays on while the renewal is unpaid; after this the subscription ends. 0 = ends on the last day.')}
            value={s.subGraceDays}
          />
          <TextInput
            name="hiddenRedirect"
            type="url"
            label={t('Hidden products: send visitors to')}
            hint={t('Where a customer goes when opening a hidden product page (e.g. your pricing page). Empty = home page.')}
            value={s.hiddenRedirect}
          />
        </section>

        <section className="flxcm-card">
          <h2>
            <Rss size={18} className="flxcm-i" /> {t('Product feed (Google Merchant)')}
          </h2>
          <Toggle
            name="feed"
            label={t('Feed on')}
            hint={t('Google Merchant Center, Meta catalog and similar services read this address.')}
            on={s.feed}
          />
          <div className="flxcm-field">
            <span className="flxcm-field__label">{t('Feed address')}</span>
            {s.feedUrl ? (
              <span className="flxcm-copyline">
                <input type="text" readOnly value={s.feedUrl} />
                <button type="button" className="flxcm-btn" onClick={copyFeedUrl}>
                  {copied ? <Check size={15} className="flxcm-i" /> : <Copy size={15} className="flxcm-i" />}{' '}
                  {copied ? t('Copied') : t('Copy')}
                </button>
              </span>
            ) : (
              <span className="flxcm-muted">{t('The feed is off, so there is no address.')}</span>
            )}
          </div>
          <TextInput name="feedBrand" label={t('Brand')} hint={t('Empty = site name.')} value={s.feedBrand} />
        </section>

        <section className="flxcm-card">
          <h2>
            <AlertTriangle size={18} className="flxcm-i" /> {t('Notifications')}
          </h2>
          <p className="flxcm-muted">
            {s.whatsappReady
              ? t("WhatsApp messages go through Flux Mail's WhatsApp connection.")
              : t('WhatsApp needs Flux Mail and a WhatsApp connection; only e-mail is sent for now.')}
          </p>
          <Toggle
            name="notifyCustomerWa"
            label={t('Send the bank details and the payment confirmation to the customer by WhatsApp')}
            on={s.notifyCustomerWa}
          />
          <TextInput
            name="notifyAdminPhone"
            type="tel"
            label={t('Admin WhatsApp number')}
            hint={t('Gets a message when a receipt arrives (and, if on, for every new order).')}
            value={s.notifyAdminPhone}
          />
          <Toggle name="notifyAdminOrder" label={t('WhatsApp the admin for every new paid order')} on={s.notifyAdminOrder} />
        </section>

        <p style={{ margin: '0 0 18px' }}>
          <button className="flxcm-btn is-primary" type="submit" disabled={save.isPending}>
            {t('Save')}
          </button>
        </p>
      </form>

      <form
        key={JSON.stringify(b)}
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
            {
              onSuccess: (res) => {
                const msg = payloadErr(res.flxCommerceBankSettingsSave?.errors);
                if (msg) return tell('bad', msg);
                tell('ok', t('Saved'));
                void q.refetch();
              },
              onError: (err) => tell('bad', errText(err, t)),
            },
          );
        }}
      >
        <div className="flxcm-card__head">
          <h2>
            <Landmark size={18} className="flxcm-i" /> {t('Bank transfer')}
          </h2>
          {adminUrl ? (
            <a href={adminLink(adminUrl, 'admin.php?page=wc-settings&tab=checkout&section=flxcm_bank')}>WooCommerce →</a>
          ) : null}
        </div>
        <p className="flxcm-muted">{t('The IBAN and the method switch are the same values WooCommerce Payments stores.')}</p>
        <Toggle name="enabled" label={t('Enabled')} on={b.enabled} />
        <TextInput name="title" label={t('Title')} value={b.title} />
        <TextInput name="description" label={t('Description')} value={b.description} rows={4} />
        <TextInput name="holder" label={t('Account holder')} value={b.holder} />
        <TextInput name="iban" label={t('IBAN')} value={b.iban} />
        <TextInput name="bank" label={t('Bank')} value={b.bank} />
        <TextInput name="refFormat" label={t('Reference format')} value={b.refFormat} />
        <TextInput name="refPrefix" label={t('Reference prefix')} value={b.refPrefix} />
        <div style={{ marginTop: 12 }}>
          <button className="flxcm-btn is-primary" type="submit" disabled={bank.isPending}>
            {t('Save')}
          </button>
        </div>
      </form>

      <section className="flxcm-card">
        <div className="flxcm-card__head">
          <h2>
            <RefreshCw size={18} className="flxcm-i" /> {t('Last renewal pass')}
          </h2>
        </div>
        <p>{status?.ran ? when(status.at) : t('Not run yet.')}</p>
        <button
          className="flxcm-btn"
          type="button"
          disabled={run.isPending}
          onClick={() =>
            run.mutate(
              { input: {} },
              {
                onSuccess: (res) => {
                  const msg = payloadErr(res.flxCommerceRenewalsRun?.errors);
                  if (msg) return tell('bad', msg);
                  tell('ok', t('Done'));
                  void q.refetch();
                },
                onError: (err) => tell('bad', errText(err, t)),
              },
            )
          }
        >
          {t('Run renewals now')}
        </button>
      </section>
    </div>
  );
}

function Toggle({ name, label, hint, on }: { name: string; label: string; hint?: string; on: boolean }) {
  return (
    <label className="flxcm-toggle">
      <input type="checkbox" name={name} value="1" defaultChecked={on} />
      <span className="flxcm-toggle__text">
        <span className="flxcm-field__label">{label}</span>
        {hint ? <span className="flxcm-field__hint">{hint}</span> : null}
      </span>
    </label>
  );
}

function Num({
  name,
  label,
  hint,
  value,
  step,
}: {
  name: string;
  label: string;
  hint?: string;
  value: number;
  step?: string;
}) {
  return (
    <div className="flxcm-field">
      <label className="flxcm-field__label" htmlFor={`flxcm-${name}`}>
        {label}
      </label>
      <input id={`flxcm-${name}`} name={name} type="number" step={step} defaultValue={value} />
      {hint ? <span className="flxcm-field__hint">{hint}</span> : null}
    </div>
  );
}

function TextInput({
  name,
  label,
  hint,
  value,
  type = 'text',
  rows,
}: {
  name: string;
  label: string;
  hint?: string;
  value: string;
  type?: string;
  /** Renders a multi-line textarea instead of a single-line input. */
  rows?: number;
}) {
  return (
    <div className="flxcm-field">
      <label className="flxcm-field__label" htmlFor={`flxcm-${name}`}>
        {label}
      </label>
      {rows ? (
        <textarea id={`flxcm-${name}`} name={name} rows={rows} defaultValue={value} />
      ) : (
        <input id={`flxcm-${name}`} name={name} type={type} defaultValue={value} />
      )}
      {hint ? <span className="flxcm-field__hint">{hint}</span> : null}
    </div>
  );
}
