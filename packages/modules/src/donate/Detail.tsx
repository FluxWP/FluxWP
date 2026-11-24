// Donation page: summary strip, receipt awaiting approval, and the movable boxes (items, payment, contacts,
// history, donor, referral, manage, order notes) in the person's order. Boxes of other plugins (the
// WooCommerce order boxes, Flux POS…) are printed by PHP as hidden "islands" and moved into their place
// here, so their own forms and scripts keep working. Every write of this page is a GraphQL mutation.
import { useEffect, useLayoutEffect, useMemo, useRef, useState, type DragEvent, type FormEvent, type ReactNode } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import {
  FlxDonateDashboard,
  FlxDonateDetail,
  FlxDonateDonations,
  FlxDonationApprove,
  FlxDonationCertificates,
  FlxDonationLayoutSave,
  FlxDonationNoteAdd,
  FlxDonationNoteDelete,
  FlxDonationRefund,
  FlxDonationReject,
  FlxDonationSaveNames,
  FlxDonationSetReferral,
  FlxDonationSetStatus,
  FlxDonationsBulk,
} from '@fluxwp/graphql/operations';
import type { FlxDonateDetailQuery } from '@fluxwp/graphql/types';
import { Check, Loading, Modal, sanitizeHtml, Screen } from '@fluxwp/ui';
import { useHost } from '../host';
import { Go, Ico, useDonate } from './shared';

type D = NonNullable<FlxDonateDetailQuery['flxDonationDetail']>;
type Fact = D['hero'][number];
type Island = D['islands'][number];
type Col = 'main' | 'side';
type Layout = { main: string[]; side: string[]; closed: string[] };
type Res = { message?: string | null; ok?: boolean | null; errors?: { message: string }[] | null } | null | undefined;

/** Boxes of this page in their default column. */
const OWN: { id: string; col: Col }[] = [
  { id: 'lines', col: 'main' },
  { id: 'payment', col: 'main' },
  { id: 'contacts', col: 'main' },
  { id: 'history', col: 'main' },
  { id: 'donor', col: 'side' },
  { id: 'ref', col: 'side' },
  { id: 'manage', col: 'side' },
  { id: 'notes', col: 'side' },
];
/** Islands that are not boxes: printed after the boxes, or inside the payment card. */
const BARE = new Set(['wc-save', 'xtabs', 'money']);

export function Detail({ id, notice }: { id: number; notice: ReactNode }) {
  const host = useHost();
  const { t } = useDonate();
  const q = useFlxQuery(FlxDonateDetail, { id });
  const d = q.data?.flxDonationDetail;
  // The islands are printed with the page only: keep the first list (a refetch has none).
  const islands = useRef<Island[] | null>(null);
  if (d && islands.current === null) islands.current = d.islands;

  if (!q.data) {
    return (
      <Screen className="flxdn" width="wide" icon={host.appIcon} title={t('Donations')} notice={notice}>
        {q.isError ? (
          <div className="flxdn-empty">
            <p>{t('The data could not be loaded.')}</p>
            <button type="button" className="flxdn-btn" onClick={() => void q.refetch()}>
              {t('Try again')}
            </button>
          </div>
        ) : (
          <Loading />
        )}
      </Screen>
    );
  }
  if (!d) {
    return (
      <Screen className="flxdn" width="wide" icon={host.appIcon} title={t('Donation not found')} notice={notice} actions={<Back />}>
        <div className="flxdn-emptystate">
          <Ico name="heart" size={32} />
          <p>{t('Donation not found')}</p>
        </div>
      </Screen>
    );
  }
  return <Page d={d} islands={islands.current ?? []} notice={notice} busy={q.isFetching} />;
}

function Back({ url }: { url?: string }) {
  const { t } = useDonate();
  return (
    <Go className="flxdn-btn" href={url || 'admin.php?page=flxb-donations'}>
      {'‹ '}
      {t('Donations')}
    </Go>
  );
}

/** Mutations of this page: the notice, then the page (and the list / dashboard) read again. */
function useDone() {
  const { t, told } = useDonate();
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  return useMemo(
    () => ({
      ok: (r: Res, then?: () => void) => {
        const err = r?.errors?.[0]?.message;
        if (err) {
          told({ kind: 'error', text: err });
          return;
        }
        if (r?.message) told({ kind: 'ok', text: r.message });
        void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxDonateDetail) });
        void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxDonateDonations) });
        void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxDonateDashboard) });
        then?.();
      },
      fail: (e: Error) => told({ kind: 'error', text: e.message || t('The action could not be completed.') }),
    }),
    [qc, scope, t, told],
  );
}

type Ask = { title: string; text: string; label: string; danger?: boolean; run: () => void };

function Page({ d, islands, notice, busy }: { d: D; islands: Island[]; notice: ReactNode; busy: boolean }) {
  const host = useHost();
  const { t } = useDonate();
  const done = useDone();
  const [ask, setAsk] = useState<Ask | null>(null);
  const [preview, setPreview] = useState<D['approval'] extends infer A ? (A extends { receipts: (infer R)[] } ? R : never) | null : never>(null);
  const [rejecting, setRejecting] = useState(false);

  const approve = useFlxMutation(FlxDonationApprove, { onSuccess: (r) => done.ok(r.flxDonationApprove), onError: done.fail, onSettled: () => setAsk(null) });

  const actions = (
    <>
      <Back url={d.listUrl} />
      {d.receiptUrl ? (
        <a className="flxdn-btn" href={d.receiptUrl} target="_blank" rel="noopener noreferrer">
          <Ico name="file" />
          {t('Print receipt')}
        </a>
      ) : null}
    </>
  );
  const askApprove = () =>
    setAsk({
      title: t('Approve'),
      text: t('Has the payment reached the bank? When you confirm, the certificate is made and sent to the donor.'),
      label: t('Approve'),
      run: () => approve.mutate({ id: d.databaseId }),
    });

  return (
    <Screen className={'flxdn flxdn-detailscreen' + (busy ? ' is-busy' : '')} width="wide" icon={host.appIcon} title={d.title} subtitle={d.date} actions={actions} notice={notice}>
      <section className="flxdn-hero" aria-label={t('Summary')}>
        {d.hero.map((f) => (
          <div className="flxdn-hero__fact" key={f.label}>
            <span className="flxdn-hero__k">{f.label}</span>
            <span className="flxdn-hero__v">
              <FactView f={f} />
            </span>
          </div>
        ))}
      </section>

      {d.approval ? (
        <section className="flxdn-card flxdn-card--gap flxdn-approve" aria-labelledby="flxdn-approve-title">
          <div className="flxdn-card__head">
            <div>
              <h2 className="flxdn-card__title" id="flxdn-approve-title">
                {d.approval.title}
              </h2>
              <p className="flxdn-card__hint">{d.approval.hint}</p>
            </div>
            {d.approval.canAct ? (
              <div className="flxdn-approve__actions">
                <button type="button" className="flxdn-btn flxdn-btn--primary" onClick={askApprove}>
                  {t('Approve')}
                </button>
                <button type="button" className="flxdn-btn flxdn-btn--danger" onClick={() => setRejecting(true)}>
                  {t('Reject')}
                </button>
              </div>
            ) : null}
          </div>
          {d.approval.receipts.length ? (
            <ul className="flxdn-approve__files">
              {d.approval.receipts.map((r) => (
                <li key={r.url}>
                  <button type="button" className="flxdn-approve__file" onClick={() => setPreview(r)}>
                    <span className="flxdn-approve__icon" aria-hidden="true">
                      {r.kind === 'pdf' ? 'PDF' : r.kind === 'image' ? 'IMG' : 'DOC'}
                    </span>
                    <span className="flxdn-approve__name">
                      <strong>{r.name}</strong>
                      {r.meta ? <small>{r.meta}</small> : null}
                    </span>
                    <span className="flxdn-approve__go">{t('Preview')}</span>
                  </button>
                  {r.warn ? <p className="flxdn-notice flxdn-notice--warn">{r.warn}</p> : null}
                </li>
              ))}
            </ul>
          ) : (
            <p className="flxdn-muted">{t('No receipt uploaded yet. Check the bank account before approving.')}</p>
          )}
        </section>
      ) : null}

      <Boxes d={d} islands={islands} setAsk={setAsk} />

      {islands
        .filter((i) => i.id === 'wc-save' || i.id === 'xtabs')
        .map((i) => (
          <IslandNode key={i.id} id={i.id} />
        ))}

      {preview ? (
        <Modal title={preview.name} wide onClose={() => setPreview(null)}>
          {preview.kind === 'image' ? (
            <img className="flxdn-preview-img" src={preview.url} alt={preview.name} />
          ) : preview.kind === 'pdf' ? (
            <iframe className="flxdn-preview" src={preview.url} title={preview.name} />
          ) : (
            <p>{preview.name}</p>
          )}
          <div className="flxdn-actions">
            <a className="flxdn-btn flxdn-btn--ghost" href={preview.url} target="_blank" rel="noopener noreferrer">
              {t('Open in new tab')}
            </a>
            {d.approval?.canAct ? (
              <>
                <button
                  type="button"
                  className="flxdn-btn flxdn-btn--danger"
                  onClick={() => {
                    setPreview(null);
                    setRejecting(true);
                  }}
                >
                  {t('Reject')}
                </button>
                <button
                  type="button"
                  className="flxdn-btn flxdn-btn--primary"
                  onClick={() => {
                    setPreview(null);
                    askApprove();
                  }}
                >
                  {t('Approve')}
                </button>
              </>
            ) : null}
          </div>
        </Modal>
      ) : null}

      {rejecting && d.approval ? <Reject d={d} onClose={() => setRejecting(false)} /> : null}

      {ask ? <Confirm ask={ask} onClose={() => setAsk(null)} busy={approve.isPending} /> : null}
    </Screen>
  );
}

function Confirm({ ask, onClose, busy }: { ask: Ask; onClose: () => void; busy?: boolean }) {
  const { t } = useDonate();
  const [sent, setSent] = useState(false);
  const wait = busy || sent;
  return (
    <Modal title={ask.title} onClose={() => !wait && onClose()}>
      <p>{ask.text}</p>
      <div className="flxdn-actions">
        <button type="button" className="flxdn-btn" disabled={wait} onClick={onClose}>
          {t('Cancel')}
        </button>
        <button
          type="button"
          className={'flxdn-btn flxdn-btn--primary' + (ask.danger ? ' flxdn-btn--danger-solid' : '')}
          disabled={wait}
          onClick={() => {
            setSent(true);
            ask.run();
          }}
        >
          {ask.label}
        </button>
      </div>
    </Modal>
  );
}

function Reject({ d, onClose }: { d: D; onClose: () => void }) {
  const { t } = useDonate();
  const done = useDone();
  const [reason, setReason] = useState('');
  const [notify, setNotify] = useState(true);
  const m = useFlxMutation(FlxDonationReject, { onSuccess: (r) => done.ok(r.flxDonationReject, onClose), onError: done.fail });
  const to = d.approval?.notifyTo ?? '';
  const submit = (e: FormEvent) => {
    e.preventDefault();
    if (reason.trim()) m.mutate({ id: d.databaseId, reason: reason.trim(), notify: to ? notify : false });
  };
  return (
    <Modal title={t('Reject the payment')} onClose={() => !m.isPending && onClose()}>
      <form className="flxdn-modal__body--form" onSubmit={submit}>
        <label className="flxdn-field">
          <span>{t('Reason (sent to the donor)')}</span>
          <textarea rows={4} required maxLength={600} value={reason} onChange={(e) => setReason(e.target.value)} placeholder={t('e.g. The recipient name on the receipt does not match our account.')} />
        </label>
        {to ? (
          <Check checked={notify} onChange={setNotify}>
            {t('Tell the donor (%s)', to)}
          </Check>
        ) : (
          <p className="flxdn-muted">{t('The donor has no phone or e-mail; the reason is only kept on the order.')}</p>
        )}
        <p className="flxdn-muted">{t('The donation is canceled. The donor can make a new one.')}</p>
        <div className="flxdn-actions">
          <button type="button" className="flxdn-btn" disabled={m.isPending} onClick={onClose}>
            {t('Cancel')}
          </button>
          <button type="submit" className="flxdn-btn flxdn-btn--primary flxdn-btn--danger-solid" disabled={m.isPending || !reason.trim()}>
            {t('Reject')}
          </button>
        </div>
      </form>
    </Modal>
  );
}

/** One fact: plain text, code, a pill or a chip, with its link. */
function FactView({ f }: { f: Fact }) {
  let body: ReactNode = f.text;
  if (f.look === 'pill') body = <span className={'flxdn-pill flxdn-pill--' + (f.tone || 'muted')}>{f.text}</span>;
  else if (f.look === 'code') body = <code>{f.text}</code>;
  else if (f.look === 'strong') body = <strong>{f.text}</strong>;
  else if (f.look === 'muted') body = <span className="flxdn-muted">{f.text}</span>;
  else if (f.look === 'chip' || f.look === 'chip-donor')
    body = (
      <span className={'flxdn-ref-chip' + (f.look === 'chip-donor' ? ' flxdn-ref-chip--donor' : '')} title={f.hint || undefined}>
        {f.text}
      </span>
    );
  if (f.url) {
    return /^https?:/i.test(f.url) && !f.url.includes('/wp-admin/') ? (
      <a href={f.url} target="_blank" rel="noopener noreferrer" title={f.hint || undefined}>
        {body}
      </a>
    ) : (
      <Go href={f.url} title={f.hint || undefined}>
        {body}
      </Go>
    );
  }
  return <>{body}</>;
}

/* ---------- Boxes ---------- */

function arrange(ids: { id: string; col: Col }[], layout: Layout): Record<Col, string[]> {
  const known = new Map(ids.map((b) => [b.id, b.col]));
  const used = new Set<string>();
  const cols: Record<Col, string[]> = { main: [], side: [] };
  for (const col of ['main', 'side'] as Col[]) {
    for (const id of layout[col]) {
      if (known.has(id) && !used.has(id)) {
        cols[col].push(id);
        used.add(id);
      }
    }
  }
  for (const b of ids) if (!used.has(b.id)) cols[b.col].push(b.id);
  return cols;
}

function Boxes({ d, islands, setAsk }: { d: D; islands: Island[]; setAsk: (a: Ask | null) => void }) {
  const { t } = useDonate();
  const save = useFlxMutation(FlxDonationLayoutSave);
  const shown = useMemo(() => {
    const own = OWN.filter((b) => {
      if (b.id === 'contacts') return d.contacts.length > 0;
      if (b.id === 'history') return d.history.length > 0 || d.receipts.length > 0;
      if (b.id === 'ref') return !!d.referral;
      if (b.id === 'manage') return !!d.manage;
      if (b.id === 'notes') return !!d.notes;
      return true;
    });
    return [...own, ...islands.filter((i) => !BARE.has(i.id)).map((i) => ({ id: i.id, col: (i.col === 'side' ? 'side' : 'main') as Col }))];
  }, [d, islands]);
  const titles = useMemo(() => {
    const m: Record<string, string> = {
      lines: t('Items'),
      payment: d.payment.title,
      contacts: t('Contacts of the people on whose behalf'),
      history: t('History'),
      donor: t('Donor'),
      ref: t('Referral'),
      manage: t('Manage'),
      notes: t('Order notes'),
    };
    for (const i of islands) m[i.id] = i.title;
    return m;
  }, [d.payment.title, islands, t]);

  const [layout, setLayout] = useState<Layout>(() => ({ main: [...d.layout.main], side: [...d.layout.side], closed: [...d.layout.closed] }));
  const cols = useMemo(() => arrange(shown, layout), [shown, layout]);
  const [moved, setMoved] = useState<string | null>(null);
  const [drag, setDrag] = useState<string | null>(null);
  const closed = new Set(layout.closed);

  const commit = (next: Record<Col, string[]>, nextClosed: string[]) => {
    // Boxes not on this page now keep their place for gifts that have them.
    const keep = (col: Col) => [...next[col], ...layout[col].filter((x) => !shown.some((b) => b.id === x) && !next.main.includes(x) && !next.side.includes(x))];
    const l: Layout = { main: keep('main'), side: keep('side'), closed: nextClosed };
    setLayout(l);
    save.mutate(l);
  };
  const move = (id: string, dir: -1 | 1) => {
    const col: Col = cols.main.includes(id) ? 'main' : 'side';
    const list = [...cols[col]];
    const i = list.indexOf(id);
    const j = i + dir;
    if (j < 0 || j >= list.length) return;
    [list[i], list[j]] = [list[j] as string, list[i] as string];
    commit({ ...cols, [col]: list }, layout.closed);
    setMoved(id);
  };
  const fold = (id: string) => commit(cols, closed.has(id) ? layout.closed.filter((x) => x !== id) : [...layout.closed, id]);
  useEffect(() => {
    if (!moved) return;
    const tm = setTimeout(() => setMoved(null), 700);
    return () => clearTimeout(tm);
  }, [moved]);

  const dropOn = (col: Col) => (e: DragEvent<HTMLDivElement>) => {
    if (!drag) return;
    e.preventDefault();
    const target = e.currentTarget;
    const others = Array.from(target.querySelectorAll<HTMLElement>(':scope > .flxdn-box')).filter((b) => b.dataset.box !== drag);
    let at = others.length;
    others.some((b, i) => {
      const r = b.getBoundingClientRect();
      if (e.clientY < r.top + r.height / 2) {
        at = i;
        return true;
      }
      return false;
    });
    const next: Record<Col, string[]> = { main: cols.main.filter((x) => x !== drag), side: cols.side.filter((x) => x !== drag) };
    const ids = others.map((b) => b.dataset.box as string);
    next[col] = [...ids.slice(0, at), drag, ...ids.slice(at)];
    setDrag(null);
    commit(next, layout.closed);
    setMoved(drag);
  };

  const body = (id: string): ReactNode => {
    switch (id) {
      case 'lines':
        return <LinesCard d={d} />;
      case 'payment':
        return <PaymentCard d={d} islands={islands} setAsk={setAsk} />;
      case 'contacts':
        return <ContactsCard d={d} />;
      case 'history':
        return <HistoryCard d={d} />;
      case 'donor':
        return <DonorCard d={d} />;
      case 'ref':
        return <RefCard d={d} />;
      case 'manage':
        return <ManageCard d={d} setAsk={setAsk} />;
      case 'notes':
        return <NotesCard d={d} setAsk={setAsk} />;
      default:
        return <IslandNode id={id} />;
    }
  };

  return (
    <div className={'flxdn-detail flxdn-boxes' + (drag ? ' is-dragging' : '')}>
      {(['main', 'side'] as Col[]).map((col) => (
        <div key={col} className={`flxdn-detail__${col} flxdn-boxes__col`} data-col={col} onDragOver={(e) => drag && e.preventDefault()} onDrop={dropOn(col)}>
          {cols[col].map((id, i) => (
            <div
              key={id}
              data-box={id}
              className={'flxdn-box' + (closed.has(id) ? ' is-closed' : '') + (moved === id ? ' is-moved' : '') + (drag === id ? ' is-dragging' : '')}
              draggable={drag === id}
              onDragEnd={() => setDrag(null)}
            >
              <div className="flxdn-box__tools" role="group" aria-label={t('Box: %s', titles[id] ?? id)}>
                <button type="button" className="flxdn-box__btn flxdn-box__grip" aria-hidden="true" tabIndex={-1} title={t('Drag to move')} onMouseDown={() => setDrag(id)} onMouseUp={() => setDrag(null)}>
                  <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true">
                    {[6, 12, 18].map((y) => (
                      <g key={y}>
                        <circle cx="9" cy={y} r="1.3" />
                        <circle cx="15" cy={y} r="1.3" />
                      </g>
                    ))}
                  </svg>
                </button>
                <button type="button" className="flxdn-box__btn" disabled={i === 0} aria-label={t('Move up')} title={t('Move up')} onClick={() => move(id, -1)}>
                  <Svg d="m18 15-6-6-6 6" />
                </button>
                <button type="button" className="flxdn-box__btn" disabled={i === cols[col].length - 1} aria-label={t('Move down')} title={t('Move down')} onClick={() => move(id, 1)}>
                  <Svg d="m6 9 6 6 6-6" />
                </button>
                <button type="button" className="flxdn-box__btn flxdn-box__fold" aria-expanded={!closed.has(id)} aria-label={t('Fold or unfold')} title={t('Fold or unfold')} onClick={() => fold(id)}>
                  <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" strokeWidth={2} strokeLinecap="round" aria-hidden="true">
                    <path d="M5 12h14" />
                    <path className="flxdn-box__v" d="M12 5v14" />
                  </svg>
                </button>
              </div>
              {body(id)}
            </div>
          ))}
        </div>
      ))}
    </div>
  );
}

const Svg = ({ d }: { d: string }) => (
  <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" focusable="false">
    <path d={d} />
  </svg>
);

/** Moves the island printed by PHP into this box; it goes back to the hidden container when the box goes. */
function IslandNode({ id }: { id: string }) {
  const ref = useRef<HTMLDivElement>(null);
  useLayoutEffect(() => {
    const el = typeof document !== 'undefined' ? document.querySelector<HTMLElement>(`[data-flxdn-island="${CSS.escape(id)}"]`) : null;
    const home = el?.parentElement ?? null;
    const box = ref.current;
    if (!el || !box) return;
    box.appendChild(el);
    return () => {
      if (home && el.parentElement === box) home.appendChild(el);
    };
  }, [id]);
  return <div ref={ref} className="flxdn-islandslot" />;
}

function Card({ title, hint, chips, className, children }: { title: string; hint?: string; chips?: ReactNode; className?: string; children: ReactNode }) {
  return (
    <section className={'flxdn-card flxdn-card--gap ' + (className ?? '')}>
      <div className="flxdn-card__head">
        <div>
          <h2 className="flxdn-card__title">{title}</h2>
          {hint ? <p className="flxdn-card__hint">{hint}</p> : null}
        </div>
        {chips ? <div className="flxdn-chips">{chips}</div> : null}
      </div>
      {children}
    </section>
  );
}

/* ---------- Items ---------- */

type SortKey = 'name' | 'unit' | 'qty' | 'total';

function LinesCard({ d }: { d: D }) {
  const { t } = useDonate();
  const done = useDone();
  const [sort, setSort] = useState<{ k: SortKey; dir: 1 | -1 } | null>(null);
  const rows = useMemo(() => {
    const r = [...d.lines.rows];
    if (sort) r.sort((a, b) => (sort.k === 'name' ? a.name.localeCompare(b.name) : a[sort.k] - b[sort.k]) * sort.dir);
    return r;
  }, [d.lines.rows, sort]);
  const names = d.lines.names;
  const [draft, setDraft] = useState<Record<number, string>>(() => Object.fromEntries((names ?? []).map((n) => [n.itemId, n.value])));
  const saveNames = useFlxMutation(FlxDonationSaveNames, { onSuccess: (r) => done.ok(r.flxDonationSaveNames), onError: done.fail });

  const th = (k: SortKey, label: string, cls: string) => {
    const on = sort?.k === k;
    return (
      <th scope="col" className={'flxdn-sortable ' + cls} aria-sort={on ? (sort.dir === 1 ? 'ascending' : 'descending') : 'none'}>
        <button type="button" onClick={() => setSort(on && sort.dir === 1 ? { k, dir: -1 } : { k, dir: 1 })}>
          {label}
          <span className="flxdn-sort-ic" aria-hidden="true" />
        </button>
      </th>
    );
  };
  return (
    <Card title={t('Items')} hint={d.lines.hint} className="flxdn-lines">
      <div className="flxdn-tablewrap">
        <table className="flxdn-table flxdn-lines__table">
          <thead>
            <tr>
              {th('name', t('Item'), 'flxdn-lines__item')}
              {th('unit', t('Unit price'), 'num')}
              {th('qty', t('Quantity'), 'num')}
              {th('total', t('Total'), 'num')}
            </tr>
          </thead>
          <tbody>
            {rows.map((r, i) => (
              <tr key={i} className={r.current ? 'is-current' : undefined}>
                <td className="flxdn-lines__item">
                  <div className="flxdn-lines__cell">
                    {r.image ? (
                      <img className="flxdn-lines__img" src={r.image} alt="" width={52} height={52} loading="lazy" />
                    ) : (
                      <span className="flxdn-lines__img flxdn-lines__img--none" aria-hidden="true">
                        <Ico name="heart" size={20} />
                      </span>
                    )}
                    <div className="flxdn-lines__body">
                      <div className="flxdn-lines__name">
                        {r.current ? (
                          <>
                            <strong>{r.name}</strong> <span className="flxdn-lines__here">{t('this page')}</span>
                          </>
                        ) : r.url ? (
                          <Go href={r.url}>{r.name}</Go>
                        ) : (
                          r.name
                        )}
                        {r.statusLabel ? (
                          <>
                            {' '}
                            <span className={'flxdn-pill flxdn-pill--' + (r.tone || 'muted')}>{r.statusLabel}</span>
                          </>
                        ) : null}
                      </div>
                      {r.bits.length ? (
                        <dl className="flxdn-lines__meta">
                          {r.bits.map((b, j) => (
                            <span key={j} style={{ display: 'contents' }}>
                              <dt>{b.label.replace(/:$/, '')}</dt>
                              <dd>{b.text}</dd>
                            </span>
                          ))}
                        </dl>
                      ) : null}
                      {r.share ? (
                        <div className="flxdn-lines__extra">
                          <span className="flxdn-lines__code">
                            {t('Kurban share')}:{' '}
                            <Go className="flxdn-mono" href={r.share.url}>
                              {r.share.code}
                            </Go>{' '}
                            <span className={'flxdn-pill flxdn-pill--' + (r.share.tone || 'muted')}>{r.share.statusLabel}</span>
                            {r.share.place ? <span className="flxdn-muted"> {r.share.place}</span> : null}
                          </span>
                        </div>
                      ) : null}
                    </div>
                  </div>
                </td>
                <td className="num" data-label={t('Unit price')}>
                  <span>{r.unitLabel}</span>
                </td>
                <td className="num flxdn-lines__qty" data-label={t('Quantity')}>
                  <span>{r.qtyLabel}</span>
                </td>
                <td className="num" data-label={t('Total')}>
                  <span>{r.totalLabel}</span>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <div className="flxdn-lines__totals">
        <dl>
          {d.lines.totals.lines.map((l, i) => (
            <div key={i} className={l.look ? 'is-' + l.look : undefined}>
              <dt>{l.label}</dt>
              <dd>{l.value}</dd>
            </div>
          ))}
        </dl>
        <dl className="flxdn-lines__paid">
          {d.lines.totals.paid.map((l, i) => (
            <div key={i} className={l.look ? 'is-' + l.look : undefined}>
              <dt>{l.label}</dt>
              <dd>{l.value}</dd>
            </div>
          ))}
          {d.lines.totals.via ? <p className="flxdn-lines__via">{d.lines.totals.via}</p> : null}
        </dl>
      </div>
      {names ? (
        <details className="flxdn-lines__names">
          <summary>{t('Edit names')}</summary>
          <form
            className="flxdn-items-edit"
            onSubmit={(e) => {
              e.preventDefault();
              saveNames.mutate({ id: d.databaseId, names: names.map((n) => ({ itemId: n.itemId, name: draft[n.itemId] ?? '' })) });
            }}
          >
            {names.map((n) => (
              <label className="flxdn-field" key={n.itemId}>
                <span className="flxdn-label">{n.label}</span>
                <input type="text" className="flxdn-input" maxLength={190} value={draft[n.itemId] ?? ''} placeholder={t('On behalf of')} onChange={(e) => setDraft((x) => ({ ...x, [n.itemId]: e.target.value }))} />
              </label>
            ))}
            <div className="flxdn-formactions">
              <button type="submit" className="flxdn-btn flxdn-btn--sm flxdn-btn--primary" disabled={saveNames.isPending}>
                {t('Save names')}
              </button>
            </div>
          </form>
        </details>
      ) : null}
    </Card>
  );
}

/* ---------- Payment ---------- */

function PaymentCard({ d, islands, setAsk }: { d: D; islands: Island[]; setAsk: (a: Ask | null) => void }) {
  const { t } = useDonate();
  const done = useDone();
  const p = d.payment;
  const [refund, setRefund] = useState(false);
  const certs = useFlxMutation(FlxDonationCertificates, { onSuccess: (r) => done.ok(r.flxDonationCertificates), onError: done.fail, onSettled: () => setAsk(null) });
  const money = islands.some((i) => i.id === 'money');
  const menu = p.menu;
  const more =
    menu || money ? (
      <details className="flxdn-more">
        <summary className="flxdn-more__btn" aria-label={t('More actions')} title={t('More actions')}>
          <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
            <circle cx="5" cy="12" r="1.8" />
            <circle cx="12" cy="12" r="1.8" />
            <circle cx="19" cy="12" r="1.8" />
          </svg>
        </summary>
        <div className="flxdn-more__menu" role="menu">
          {menu ? (
            <a className="flxdn-more__item" href={menu.orderUrl} role="menuitem">
              <span>{menu.orderLabel}</span>
            </a>
          ) : null}
          {menu?.refund ? (
            <button type="button" className="flxdn-more__item flxdn-more__item--danger" role="menuitem" onClick={() => setRefund(true)}>
              <Ico name="repeat" />
              <span>{menu.refund.label}…</span>
            </button>
          ) : null}
          {money ? (
            <div className="flxdn-more__form">
              <IslandNode id="money" />
            </div>
          ) : null}
        </div>
      </details>
    ) : null;
  return (
    <Card
      title={p.title}
      className="flxdn-paycard"
      chips={
        <>
          <span className={'flxdn-pill ' + (p.recurring ? 'flxdn-pill--recurring' : 'flxdn-pill--muted')}>{p.kindLabel}</span>
          <span className={'flxdn-pill flxdn-pill--' + (p.tone || 'muted')}>{p.statusLabel}</span>
          {more}
        </>
      }
    >
      <dl className="flxdn-facts">
        {p.facts.filter((f) => f.text !== "" && f.text !== "—").map((f, i) => (
          <div key={i}>
            <dt>{f.label}</dt>
            <dd>
              <FactView f={f} />
            </dd>
          </div>
        ))}
      </dl>
      {p.certificates.length ? (
        <div className="flxdn-paycard__row">
          <h3 className="flxdn-paycard__h">{p.certificates.length > 1 ? t('Certificates') : t('Certificate')}</h3>
          <ul className="flxdn-paycard__certs">
            {p.certificates.map((c) => (
              <li key={c.label}>
                {c.url ? (
                  <a href={c.url} target="_blank" rel="noopener noreferrer">
                    {c.label}
                  </a>
                ) : (
                  <code>{c.label}</code>
                )}
                {c.revoked ? <span className="flxdn-muted"> ({t('revoked')})</span> : null}
              </li>
            ))}
          </ul>
          {p.canCertificates ? (
            <div className="flxdn-cert-actions">
              <button
                type="button"
                className="flxdn-btn"
                onClick={() =>
                  setAsk({
                    title: t('Make the certificates again'),
                    text: t('The current certificates are revoked and new ones are made, one for each name. They are not sent until you press "Send the certificates".'),
                    label: t('Make the certificates again'),
                    run: () => certs.mutate({ id: d.databaseId, action: 'REMAKE' }),
                  })
                }
              >
                {t('Make the certificates again')}
              </button>
              <button
                type="button"
                className="flxdn-btn flxdn-btn--ghost"
                onClick={() =>
                  setAsk({
                    title: t('Send the certificates'),
                    text: t('Send the certificates to the donor now?'),
                    label: t('Send the certificates'),
                    run: () => certs.mutate({ id: d.databaseId, action: 'SEND' }),
                  })
                }
              >
                {t('Send the certificates')}
              </button>
            </div>
          ) : null}
        </div>
      ) : null}
      {p.note ? (
        <div className="flxdn-paycard__row">
          <h3 className="flxdn-paycard__h">{t('Notes')}</h3>
          <p className="flxdn-paycard__note" style={{ whiteSpace: 'pre-line' }}>
            {p.note}
          </p>
        </div>
      ) : null}
      {refund && menu?.refund ? <Refund d={d} offer={menu.refund} onClose={() => setRefund(false)} /> : null}
    </Card>
  );
}

function Refund({ d, offer, onClose }: { d: D; offer: NonNullable<NonNullable<D['payment']['menu']>['refund']>; onClose: () => void }) {
  const { t } = useDonate();
  const done = useDone();
  const [amount, setAmount] = useState(String(offer.max));
  const m = useFlxMutation(FlxDonationRefund, { onSuccess: (r) => done.ok(r.flxDonationRefund, onClose), onError: done.fail });
  const n = Number(amount.replace(',', '.'));
  const valid = Number.isFinite(n) && n > 0 && n <= offer.max + 0.004;
  return (
    <Modal title={offer.label} onClose={() => !m.isPending && onClose()}>
      <form
        className="flxdn-modal__body--form"
        onSubmit={(e) => {
          e.preventDefault();
          if (valid) m.mutate({ id: d.databaseId, amount: n });
        }}
      >
        <p>{offer.help}</p>
        <label className="flxdn-field">
          <span>{offer.field}</span>
          <input type="number" className="flxdn-input" min={0.01} max={offer.max} step="0.01" required value={amount} onChange={(e) => setAmount(e.target.value)} />
        </label>
        <div className="flxdn-actions">
          <button type="button" className="flxdn-btn" disabled={m.isPending} onClick={onClose}>
            {t('Cancel')}
          </button>
          <button type="submit" className="flxdn-btn flxdn-btn--primary flxdn-btn--danger-solid" disabled={m.isPending || !valid}>
            {offer.label}
          </button>
        </div>
      </form>
    </Modal>
  );
}

/* ---------- Records ---------- */

function ContactsCard({ d }: { d: D }) {
  const { t } = useDonate();
  return (
    <Card title={t('Contacts of the people on whose behalf')} hint={t('Given to the call center so they can be told when the donation is carried out.')} className="flxdn-contacts">
      <div className="flxdn-tablewrap">
        <table className="flxdn-table flxdn-table--compact">
          <thead>
            <tr>
              <th scope="col">{t('Name')}</th>
              <th scope="col">{t('Phone')}</th>
              <th scope="col">{t('E-mail')}</th>
            </tr>
          </thead>
          <tbody>
            {d.contacts.map((c, i) => (
              <tr key={i}>
                <td className="flxdn-col-main">{c.name || '—'}</td>
                <td data-label={t('Phone')}>
                  <span>{c.phone ? <a href={c.phoneUrl}>{c.phone}</a> : '—'}</span>
                </td>
                <td data-label={t('E-mail')}>
                  <span>{c.email ? <a href={'mailto:' + c.email}>{c.email}</a> : '—'}</span>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </Card>
  );
}

function HistoryCard({ d }: { d: D }) {
  const { t } = useDonate();
  return (
    <Card title={t('History')} className="flxdn-history">
      {d.receipts.length ? (
        <>
          <h3 className="flxdn-paycard__h">{t('Receipts')}</h3>
          <ul className="flxdn-list flxdn-receipts">
            {d.receipts.map((r, i) => (
              <li key={i}>
                {r.url ? (
                  <a href={r.url} target="_blank" rel="noopener noreferrer">
                    {r.name}
                  </a>
                ) : (
                  r.name
                )}
                {r.meta ? <span className="flxdn-muted"> · {r.meta}</span> : null}
              </li>
            ))}
          </ul>
        </>
      ) : null}
      {d.history.length ? (
        <ul className="flxdn-list flxdn-audit">
          {d.history.map((h, i) => (
            <li key={i}>
              <span className="flxdn-muted">{h.date}</span> · {h.text} <span className="flxdn-muted">· {h.user}</span>
            </li>
          ))}
        </ul>
      ) : null}
    </Card>
  );
}

/* ---------- Side column ---------- */

function Person({ name, initial, contact, refLook }: { name: string; initial: string; contact: string[]; refLook?: boolean }) {
  return (
    <div className="flxdn-pcard__id">
      <span className={'flxdn-pcard__av' + (refLook ? ' flxdn-pcard__av--ref' : '')} aria-hidden="true">
        {initial}
      </span>
      <div>
        <strong>{name}</strong>
        {contact.map((c) => (
          <small key={c}>{c}</small>
        ))}
      </div>
    </div>
  );
}

function DonorCard({ d }: { d: D }) {
  const { t } = useDonate();
  const p = d.donor;
  return (
    <Card title={t('Donor')} className="flxdn-pcard">
      {!p ? (
        <p className="flxdn-muted">{t('No contact details were given with this gift.')}</p>
      ) : (
        <>
          <Person name={p.name} initial={p.initial} contact={p.contact} />
          <dl className="flxdn-pcard__stats">
            <div>
              <dt>{t('Paid gifts')}</dt>
              <dd>{p.paid}</dd>
            </div>
            <div>
              <dt>{t('Total')}</dt>
              <dd>{p.total}</dd>
            </div>
            <div>
              <dt>{t('Waiting')}</dt>
              <dd>{p.waiting}</dd>
            </div>
          </dl>
          {p.cardUrl || p.chatUrl ? (
            <div className="flxdn-pcard__links">
              {p.cardUrl ? (
                <Go className="flxdn-btn flxdn-btn--sm" href={p.cardUrl}>
                  {t('Donor card')}
                </Go>
              ) : null}
              {p.chatUrl ? (
                <Go className="flxdn-btn flxdn-btn--sm flxdn-btn--ghost" href={p.chatUrl}>
                  {t('Open chat')}
                </Go>
              ) : null}
            </div>
          ) : null}
        </>
      )}
    </Card>
  );
}

function RefCard({ d }: { d: D }) {
  const { t } = useDonate();
  const done = useDone();
  const r = d.referral;
  const [pick, setPick] = useState(String(r?.current ?? 0));
  useEffect(() => setPick(String(r?.current ?? 0)), [r?.current]);
  const m = useFlxMutation(FlxDonationSetReferral, { onSuccess: (x) => done.ok(x.flxDonationSetReferral), onError: done.fail });
  if (!r) return null;
  return (
    <Card title={t('Referral')} className="flxdn-pcard">
      {r.person ? (
        <Person name={r.person.name} initial={r.person.initial} contact={r.person.contact} refLook />
      ) : (
        <>
          <p className="flxdn-muted">{t('This gift has no referral.')}</p>
          {r.hint ? <p className="flxdn-notice flxdn-notice--info">{r.hint}</p> : null}
        </>
      )}
      {r.canEdit ? (
        <form
          className="flxdn-ref-edit"
          onSubmit={(e) => {
            e.preventDefault();
            m.mutate({ id: d.databaseId, referralId: Number(pick) || 0 });
          }}
        >
          <label className="screen-reader-text" htmlFor="flxdn-ref-pick">
            {t('Referral')}
          </label>
          <select id="flxdn-ref-pick" value={pick} onChange={(e) => setPick(e.target.value)}>
            <option value="0">{t('None')}</option>
            {r.choices.map((c) => (
              <option key={c.value} value={c.value}>
                {c.label}
              </option>
            ))}
          </select>{' '}
          <button type="submit" className="flxdn-btn flxdn-btn--sm" disabled={m.isPending || pick === String(r.current)}>
            {t('Change')}
          </button>
        </form>
      ) : null}
    </Card>
  );
}

function ManageCard({ d, setAsk }: { d: D; setAsk: (a: Ask | null) => void }) {
  const host = useHost();
  const { t } = useDonate();
  const done = useDone();
  const m = d.manage;
  const [status, setStatus] = useState('');
  const st = useFlxMutation(FlxDonationSetStatus, { onSuccess: (r) => done.ok(r.flxDonationSetStatus, () => setStatus('')), onError: done.fail, onSettled: () => setAsk(null) });
  const bulk = useFlxMutation(FlxDonationsBulk, {
    onSuccess: (r, v) => done.ok(r.flxDonationsBulk, () => v.action !== 'RESTORE' && host.openUrl(d.listUrl)),
    onError: done.fail,
    onSettled: () => setAsk(null),
  });
  if (!m) return null;
  const run = (action: 'TRASH' | 'RESTORE' | 'DELETE') => bulk.mutate({ ids: [d.databaseId], action });
  return (
    <Card title={t('Manage')} hint={m.multi ? t('This gift is one basket: every item in it changes together.') : undefined} className="flxdn-manage">
      <div className="flxdn-manage__row">
        {m.statuses.length ? (
          <form
            className="flxdn-inline-form flxdn-manage__status"
            onSubmit={(e) => {
              e.preventDefault();
              if (!status) return;
              setAsk({ title: t('Change status'), text: m.statusConfirm, label: t('Change status'), run: () => st.mutate({ id: d.databaseId, status }) });
            }}
          >
            <label htmlFor="flxdn-st">{t('Status')}</label>
            <select id="flxdn-st" value={status} required onChange={(e) => setStatus(e.target.value)}>
              <option value="" disabled>
                {t('Choose a new status')}
              </option>
              {m.statuses.map((s) => (
                <option key={s.value} value={s.value}>
                  {s.label}
                </option>
              ))}
            </select>
            <button type="submit" className="flxdn-btn flxdn-btn--sm flxdn-btn--primary" disabled={!status}>
              {t('Change status')}
            </button>
          </form>
        ) : null}
        {m.trashed ? (
          <button type="button" className="flxdn-btn flxdn-btn--sm" disabled={bulk.isPending} onClick={() => run('RESTORE')}>
            {t('Restore')}
          </button>
        ) : (
          <button type="button" className="flxdn-btn flxdn-btn--sm flxdn-btn--ghost" onClick={() => setAsk({ title: t('Move to trash'), text: m.trashConfirm, label: t('Move to trash'), run: () => run('TRASH') })}>
            {t('Move to trash')}
          </button>
        )}
        {m.canDelete ? (
          <button
            type="button"
            className="flxdn-btn flxdn-btn--sm flxdn-btn--danger"
            onClick={() => setAsk({ title: t('Delete permanently'), text: t('Delete this gift permanently? This cannot be undone.'), label: t('Delete permanently'), danger: true, run: () => run('DELETE') })}
          >
            {t('Delete permanently')}
          </button>
        ) : null}
      </div>
    </Card>
  );
}

function NotesCard({ d, setAsk }: { d: D; setAsk: (a: Ask | null) => void }) {
  const { t } = useDonate();
  const done = useDone();
  const [text, setText] = useState('');
  const [kind, setKind] = useState<'private' | 'customer'>('private');
  const add = useFlxMutation(FlxDonationNoteAdd, {
    onSuccess: (r) =>
      done.ok(r.flxDonationNoteAdd, () => {
        setText('');
        setKind('private');
      }),
    onError: done.fail,
  });
  const del = useFlxMutation(FlxDonationNoteDelete, { onSuccess: (r) => done.ok(r.flxDonationNoteDelete), onError: done.fail, onSettled: () => setAsk(null) });
  if (!d.notes) return null;
  return (
    <Card title={t('Order notes')} className="flxdn-xbox flxdn-xbox--notes">
      <ul className="flxdn-notes">
        {!d.notes.items.length ? <li className="flxdn-muted">{t('No notes yet.')}</li> : null}
        {d.notes.items.map((n) => (
          <li key={n.id} className={'flxdn-note' + (n.customer ? ' is-customer' : n.system ? ' is-system' : '')}>
            <div className="flxdn-note__text" dangerouslySetInnerHTML={{ __html: sanitizeHtml(n.html) }} />
            <div className="flxdn-note__meta">
              <span>
                {n.meta}
                {n.customer ? ' · ' + t('to the donor') : ''}
              </span>
              <button
                type="button"
                className="flxdn-linkbtn flxdn-linkbtn--danger"
                onClick={() => setAsk({ title: t('Delete'), text: t('Delete this note?'), label: t('Delete'), danger: true, run: () => del.mutate({ id: d.databaseId, noteId: n.id }) })}
              >
                {t('Delete')}
              </button>
            </div>
          </li>
        ))}
      </ul>
      <form
        className="flxdn-noteform"
        onSubmit={(e) => {
          e.preventDefault();
          if (text.trim()) add.mutate({ id: d.databaseId, text: text.trim(), customer: kind === 'customer' });
        }}
      >
        <label className="screen-reader-text" htmlFor="flxdn-note-text">
          {t('Add note')}
        </label>
        <textarea id="flxdn-note-text" rows={3} required value={text} placeholder={t('Add a note…')} onChange={(e) => setText(e.target.value)} />
        <div className="flxdn-noteform__row">
          <label className="screen-reader-text" htmlFor="flxdn-note-type">
            {t('Note type')}
          </label>
          <select id="flxdn-note-type" value={kind} onChange={(e) => setKind(e.target.value === 'customer' ? 'customer' : 'private')}>
            <option value="private">{t('Private note')}</option>
            <option value="customer">{t('Note to the donor (sent by e-mail)')}</option>
          </select>
          <button type="submit" className="flxdn-btn flxdn-btn--sm" disabled={add.isPending || !text.trim()}>
            {t('Add')}
          </button>
        </div>
      </form>
    </Card>
  );
}
