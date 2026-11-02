// Flux Donate › Onay bekleyenler: dekontu gelen Havale/EFT bağışları.
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useMutation, useQuery } from '@tanstack/react-query';
import { AlertTriangle, Check, FileText, Mail, Phone, RefreshCw, StickyNote, Users, X, Inbox } from 'lucide-react';
import { bagisReview, type PendingDonation } from '@fluxwp/core';
import { useModuleApi } from '../host';
import { Modal } from '@fluxwp/ui';
import { Empty, Loading } from '@fluxwp/ui';
import { Avatar } from '@fluxwp/ui';

export function ReviewScreen() {
  const { t } = useTranslation();
  const { api, key, qc, refreshCounts } = useModuleApi(bagisReview);
  const q = useQuery({ queryKey: key('flxb', 'pending'), queryFn: () => api.pending(), refetchInterval: 15_000 });
  const items = q.data?.items ?? [];
  const [selId, setSelId] = useState<number | null>(null);
  const sel = items.find((i) => i.id === selId) ?? items[0] ?? null;
  const [rejecting, setRejecting] = useState(false);
  const [zoom, setZoom] = useState(false);
  const [toast, setToast] = useState<string | null>(null);

  useEffect(() => {
    if (!toast) return;
    const h = setTimeout(() => setToast(null), 3500);
    return () => clearTimeout(h);
  }, [toast]);

  const done = (msg: string, id: number) => {
    setToast(msg);
    qc.setQueryData(key('flxb', 'pending'), (old: typeof q.data) => old && { ...old, items: old.items.filter((i) => i.id !== id), total: old.total - 1 });
    setSelId(null);
    void refreshCounts();
    void qc.invalidateQueries({ queryKey: key('dashboard') });
  };

  const approve = useMutation({
    mutationFn: (d: PendingDonation) => api.approve(d.id),
    onSuccess: (r, d) => done(r.certificates ? t('review.approvedCert', { count: r.certificates }) : t('review.approved'), d.id),
  });
  const reject = useMutation({
    mutationFn: (v: { d: PendingDonation; reason: string; notify: boolean }) => api.reject(v.d.id, v.reason, v.notify),
    onSuccess: (_r, v) => (setRejecting(false), done(t('review.rejected'), v.d.id)),
  });

  return (
    <div className="split">
      <section className="split-list">
        <header className="split-head">
          <div>
            <h1>{t('review.title')}</h1>
            <p className="muted small">{t('review.count', { count: q.data?.total ?? 0 })}</p>
          </div>
          <button className="icon-btn" onClick={() => q.refetch()} aria-label={t('common.refresh')}>
            <RefreshCw size={16} className={q.isFetching ? 'spin' : ''} />
          </button>
        </header>
        {q.isLoading && <Loading />}
        {q.isError && <p className="error pad">{t('errors.server')}</p>}
        {q.isSuccess && items.length === 0 && <Empty icon={<Inbox size={28} />} title={t('review.emptyTitle')} body={t('review.emptyBody')} />}
        <div className="rows">
          {items.map((d) => (
            <button key={d.id} className={'row-item' + (sel?.id === d.id ? ' sel' : '')} onClick={() => setSelId(d.id)}>
              <Avatar name={d.donor.name || '?'} size={34} />
              <span className="grow">
                <span className="row-line"><strong>{d.donor.name}</strong><span className="amount">{d.amount_label}</span></span>
                <span className="row-line muted small">
                  <span>{d.title}</span>
                  <span className={d.receipt ? '' : 'text-warn'}>{d.receipt ? d.waiting_label : t('review.noReceipt')}</span>
                </span>
              </span>
            </button>
          ))}
        </div>
      </section>

      <section className="split-detail">
        {!sel ? (
          q.isSuccess && <Empty icon={<Check size={28} />} title={t('review.allDone')} />
        ) : (
          <div className="detail">
            <header className="detail-head">
              <div>
                <h2 className="big">{sel.amount_label}</h2>
                <p className="muted">{sel.title} · #{sel.order_id} · {sel.date_label}</p>
              </div>
              <span className="pill warn">{sel.waiting_label}</span>
            </header>

            <div className="facts">
              <div><Users size={15} /> {sel.donor.name}</div>
              {sel.donor.phone && <div><Phone size={15} /> {sel.donor.phone}</div>}
              {sel.donor.email && <div><Mail size={15} /> {sel.donor.email}</div>}
              {sel.ref && <div><StickyNote size={15} /> {t('review.ref')}: {sel.ref.name}</div>}
              {sel.note && <div><StickyNote size={15} /> {sel.note}</div>}
            </div>

            <table className="lines">
              <tbody>
                {sel.lines.map((l, i) => (
                  <tr key={i}><td>{l.title}</td><td className="muted">× {l.quantity}</td><td className="num">{l.amount_label}</td></tr>
                ))}
              </tbody>
            </table>

            <h3 className="sub-title">{t('review.receipt')}</h3>
            {sel.receipt ? (
              <div className="receipt">
                {sel.receipt.warn && <p className="notice warn"><AlertTriangle size={14} /> {sel.receipt.warn}</p>}
                {sel.receipt.type === 'image' ? (
                  <button className="receipt-img" onClick={() => setZoom(true)}><img src={sel.receipt.url} alt={t('review.receipt')} /></button>
                ) : (
                  <a className="btn-ghost" href={sel.receipt.url} target="_blank" rel="noreferrer"><FileText size={16} /> {sel.receipt.name || 'PDF'}</a>
                )}
                <small className="muted">{sel.receipt.at_label}</small>
              </div>
            ) : (
              <p className="notice">{t('review.noReceiptBody')}</p>
            )}

            <footer className="detail-actions">
              <button className="btn-ghost danger" onClick={() => setRejecting(true)} disabled={approve.isPending}><X size={16} /> {t('review.reject')}</button>
              <button className="btn-primary" disabled={approve.isPending} onClick={() => confirm(t('review.approveConfirm', { name: sel.donor.name, amount: sel.amount_label })) && approve.mutate(sel)}>
                <Check size={16} /> {t('review.approve')}
              </button>
            </footer>
            {(approve.isError || reject.isError) && <p className="error">{(approve.error ?? reject.error)?.message || t('errors.server')}</p>}
          </div>
        )}
      </section>

      {rejecting && sel && <RejectModal d={sel} busy={reject.isPending} onClose={() => setRejecting(false)} onSubmit={(reason, notify) => reject.mutate({ d: sel, reason, notify })} />}
      {zoom && sel?.receipt && <Modal wide onClose={() => setZoom(false)}><img className="zoom-img" src={sel.receipt.url} alt="" /></Modal>}
      {toast && <div className="toast" role="status">{toast}</div>}
    </div>
  );
}

function RejectModal({ d, busy, onClose, onSubmit }: { d: PendingDonation; busy: boolean; onClose: () => void; onSubmit: (reason: string, notify: boolean) => void }) {
  const { t } = useTranslation();
  const [reason, setReason] = useState('');
  const [notify, setNotify] = useState(true);
  return (
    <Modal title={t('review.rejectTitle', { name: d.donor.name })} onClose={onClose}>
      <form className="stack" onSubmit={(e) => (e.preventDefault(), onSubmit(reason.trim(), notify))}>
        <label className="field">
          <span>{t('review.reason')}</span>
          <textarea autoFocus rows={3} value={reason} onChange={(e) => setReason(e.target.value)} placeholder={t('review.reasonPlaceholder')} />
        </label>
        <label className="check"><input type="checkbox" checked={notify} onChange={(e) => setNotify(e.target.checked)} /> {t('review.notify')}</label>
        <div className="row end">
          <button type="button" className="btn-ghost" onClick={onClose}>{t('common.cancel')}</button>
          <button className="btn-primary danger-bg" disabled={!reason.trim() || busy}>{t('review.reject')}</button>
        </div>
      </form>
    </Modal>
  );
}
