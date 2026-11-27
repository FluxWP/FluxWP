import { useEffect, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { FlxPosAccounts, FlxPosTxnRefund, FlxPosTxns } from '@fluxwp/graphql/operations';
import type { FlxPosTxnStatus } from '@fluxwp/graphql/types';
import { Loading } from '@fluxwp/ui';
import { ChevronLeft, ChevronRight, Receipt, RotateCcw } from 'lucide-react';
import { Badge, Dialog, EmptyState, Failed, InlineNotice, Panel, SearchField, msg } from './components';
import type { Translate } from './i18n';
import type { TabProps, Txn } from './types';

const STATUSES: FlxPosTxnStatus[] = ['PAID', 'PENDING', 'FAILED', 'REFUNDED', 'PARTIAL', 'VOIDED', 'REVIEW'];

export function TxnsTab({ t, say }: TabProps) {
  const qc = useQueryClient();
  const { scope } = useGraphQL();
  const initial = typeof window !== 'undefined' ? (new URLSearchParams(window.location.search).get('s') ?? '') : '';
  const [status, setStatus] = useState<FlxPosTxnStatus | ''>('');
  const [account, setAccount] = useState('');
  const [search, setSearch] = useState(initial);
  const [typed, setTyped] = useState(initial);
  const [cursors, setCursors] = useState<(string | null)[]>([null]);
  const [refund, setRefund] = useState<Txn | null>(null);
  useEffect(() => {
    const next = typed.trim();
    if (next === search) return;
    const id = setTimeout(() => {
      setSearch(next);
      setCursors([null]);
    }, 350);
    return () => clearTimeout(id);
  }, [typed, search]);

  const vars = {
    first: 25,
    after: cursors[cursors.length - 1] ?? null,
    status: status || null,
    account: account || null,
    search: search || null,
  };
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
  const tone: Record<FlxPosTxnStatus, string> = {
    PAID: 'is-ok',
    PENDING: '',
    FAILED: 'is-bad',
    REFUNDED: 'is-warn',
    PARTIAL: 'is-warn',
    VOIDED: '',
    REVIEW: 'is-bad',
  };
  const rows = q.data?.flxPosTxns.nodes ?? [];
  const info = q.data?.flxPosTxns.pageInfo;
  return (
    <Panel
      icon={Receipt}
      title={t('Transactions')}
      description={t('Search card payments and manage refunds.')}
      className="flxpos-transactions"
    >
      <div className="flxpos-filters">
        <SearchField
          label={t('Reference, order number, card or message')}
          value={typed}
          onChange={(e) => setTyped(e.currentTarget.value)}
        />
        <select
          value={status}
          onChange={(e) => {
            setStatus(e.currentTarget.value as FlxPosTxnStatus | '');
            setCursors([null]);
          }}
          aria-label={t('Status')}
        >
          <option value="">{t('All results')}</option>
          {STATUSES.map((s) => (
            <option key={s} value={s}>
              {labels[s]}
            </option>
          ))}
        </select>
        <select
          value={account}
          onChange={(e) => {
            setAccount(e.currentTarget.value);
            setCursors([null]);
          }}
          aria-label={t('POS account')}
        >
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
        <EmptyState icon={Receipt} title={t('No transactions.')} />
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
                  <td data-label={t('Date')} title={x.created.label}>
                    {x.created.relative || x.created.label}
                  </td>
                  <td data-label={t('Order')}>{x.orderId ? <a href={x.orderUrl}>#{x.orderNumber}</a> : '—'}</td>
                  <td data-label={t('POS')}>
                    {x.accountName}
                    {x.test && <Badge tone="is-warn">{t('test')}</Badge>}
                  </td>
                  <td data-label={t('Card')}>
                    <code>{x.card || '—'}</code>
                    <small className="flxpos-cell-sub">
                      {x.installment > 1 ? t('%d installments', x.installment) : t('single payment')}
                    </small>
                  </td>
                  <td data-label={t('Amount')}>
                    <strong className="flxpos-amount">{x.amount.formatted}</strong>
                    {x.refunded.amount > 0 && <small className="flxpos-cell-sub">{t('refunded %s', x.refunded.formatted)}</small>}
                  </td>
                  <td data-label={t('Result')}>
                    <Badge tone={tone[x.status]}>{labels[x.status]}</Badge>
                    <small className="flxpos-cell-sub">
                      <code>{x.bankRef || x.ref}</code>
                    </small>
                    {x.message && <small className="flxpos-cell-sub">{x.message}</small>}
                  </td>
                  <td data-label={t('Actions')}>
                    {x.refundable && (
                      <button type="button" className="btn-ghost small" onClick={() => setRefund(x)}>
                        <RotateCcw size={14} aria-hidden="true" />
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
          <button type="button" className="btn-ghost small" disabled={cursors.length < 2} onClick={() => setCursors(cursors.slice(0, -1))}>
            <ChevronLeft size={16} aria-hidden="true" />
            {t('Newer')}
          </button>
          <button
            type="button"
            className="btn-ghost small"
            disabled={!info?.hasNextPage}
            onClick={() => setCursors([...cursors, info?.endCursor ?? null])}
          >
            {t('Older')}
            <ChevronRight size={16} aria-hidden="true" />
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
    </Panel>
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
    <Dialog
      t={t}
      title={t('Refund')}
      onClose={() => !m.isPending && onClose()}
      actions={
        <>
          <button type="button" className="btn-ghost" disabled={m.isPending} onClick={onClose}>
            {t('Cancel')}
          </button>
          <button
            type="button"
            className="btn-primary danger-bg"
            disabled={m.isPending || !(n > 0 && n <= left)}
            onClick={() => {
              setErr('');
              m.mutate({ input: { id: txn.databaseId, amount: n } });
            }}
          >
            <RotateCcw size={16} aria-hidden="true" />
            {m.isPending ? t('Refunding…') : t('Refund')}
          </button>
        </>
      }
    >
      <InlineNotice kind="warn">
        {t('Order #%1$s · paid %2$s. The money goes back to the card; it cannot be undone.', txn.orderNumber, txn.amount.formatted)}
      </InlineNotice>
      <label className="field flxpos-field">
        <span>{t('Amount to refund (%s)', txn.amount.currency)}</span>
        <input type="text" inputMode="decimal" value={amount} onChange={(e) => setAmount(e.currentTarget.value)} />
      </label>
      {err && <InlineNotice kind="bad">{err}</InlineNotice>}
    </Dialog>
  );
}
