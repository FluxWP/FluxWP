// Records, new certificate and people. Same screens in wp-admin; reads and writes go through
// plugins/flx-certificates/graphql/operations. Pages that have not moved stay on the classic UI.
import { useEffect, useMemo, useState, type FormEvent, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { useQueryClient } from '@tanstack/react-query';
import type { ApiClient } from '@fluxwp/core';
import { createGraphQLClient } from '@fluxwp/graphql';
import { FlxGraphQLProvider, flxQueryKey, useFlxCan, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import {
  FlxCertificateAttachFiles,
  FlxCertificateBulk,
  FlxCertificateCreate,
  FlxCertificateCreateConfig,
  FlxCertificateDirectory,
  FlxCertificateOne,
  FlxCertificatePerson,
  FlxCertificatePersonDelete,
  FlxCertificatePersonMessages,
  FlxCertificatePersonSave,
  FlxCertificateRecords,
  FlxCertificateSend,
  FlxCertificateSendTo,
  FlxCertificateSetReferrer,
  FlxCertificateSetStatus,
  FlxCertificateUpdate,
} from '@fluxwp/graphql/operations';
import type {
  FlxCertificateBulkAction,
  FlxCertificateChannel,
  FlxCertificateCreateConfigQuery,
  FlxCertificateDirectoryQuery,
  FlxCertificateFilterInput,
  FlxCertificateRecordsQuery,
  FlxCertificateStatus,
} from '@fluxwp/graphql/types';
import { useHost } from '../host';
import { translator, type Translate } from './i18n';
import { bytesToBlob, ensureRenderer, mergePdfs, type FlxsRenderApi } from './render';
import './certificates.css';

type Route = { page: 'records' } | { page: 'new'; id?: number } | { page: 'people' } | { page: 'add' } | { page: 'person'; id: number };
type Row = FlxCertificateRecordsQuery['flxCertificates']['nodes'][number];
type Meta = FlxCertificateRecordsQuery['flxCertificateRecordsMeta'];
type PersonRow = FlxCertificateDirectoryQuery['flxCertificateDirectory']['nodes'][number];
type Notice = { kind: 'ok' | 'err'; text: string };

interface ListFilter {
  search: string;
  status: '' | FlxCertificateStatus;
  event: string;
  template: string;
  ref: string;
  by: string;
}

const emptyFilter = (): ListFilter => ({ search: '', status: '', event: '', template: '', ref: '', by: '' });

function routeOf(start?: string): Route {
  if (start === 'new') return { page: 'new' };
  if (start?.startsWith('new:')) return { page: 'new', id: Number(start.slice(4)) || undefined };
  if (start === 'people') return { page: 'people' };
  if (start === 'people:add') return { page: 'add' };
  if (start?.startsWith('person:')) return { page: 'person', id: Number(start.slice(7)) || 0 };
  return { page: 'records' };
}

function toFilter(f: ListFilter): FlxCertificateFilterInput {
  const out: FlxCertificateFilterInput = {};
  if (f.search.trim()) out.search = f.search.trim();
  if (f.status) out.status = f.status;
  if (f.event !== '') out.eventId = Number(f.event);
  if (f.template) out.templateId = Number(f.template);
  if (f.ref !== '') out.referrerId = Number(f.ref);
  if (f.by) out.createdBy = Number(f.by);
  return out;
}

function admin(base: string | undefined, query: string): string {
  const root = (base || '').replace(/\/?$/, '/');
  return root + 'admin.php?' + query;
}

function personBits(row: Row): { name: string; email: string; phone: string } {
  const p = row.person;
  if (!p || !('name' in p)) return { name: '', email: '', phone: '' };
  return { name: p.name, email: p.email ?? '', phone: p.phone?.display ?? '' };
}

export function CertificatesScreen() {
  const host = useHost();
  const client = useMemo(() => createGraphQLClient({ api: host.client }), [host.client]);
  return (
    <FlxGraphQLProvider client={client} scope={host.scope}>
      <Certificates />
    </FlxGraphQLProvider>
  );
}

function Certificates() {
  const host = useHost();
  const { i18n } = useTranslation();
  const { t, tn } = useMemo(() => translator(i18n.language), [i18n.language]);
  const [route, setRoute] = useState<Route>(() => routeOf(host.startPage));
  const [notice, setNotice] = useState<Notice | null>(null);
  return (
    <div className="flxs-app">
      <header className="flxs-head">
        <h1>{route.page === 'people' || route.page === 'add' || route.page === 'person' ? t('People') : route.page === 'new' ? t('New certificate') : t('Certificates')}</h1>
        <div className="flxs-acts">
          {host.classicUrl && <a href={host.classicUrl}>{t('Classic view')}</a>}
          {route.page !== 'records' && <button type="button" onClick={() => setRoute({ page: 'records' })}>{t('Certificates')}</button>}
          {route.page !== 'people' && <button type="button" onClick={() => setRoute({ page: 'people' })}>{t('People')}</button>}
        </div>
      </header>
      {notice && (
        <div className={'flxs-notice' + (notice.kind === 'err' ? ' is-error' : '')}>
          {notice.text} <button type="button" onClick={() => setNotice(null)}>{t('Close')}</button>
        </div>
      )}
      {route.page === 'records' && <Records t={t} tn={tn} onNotice={setNotice} onEdit={(id) => setRoute({ page: 'new', id })} onNew={() => setRoute({ page: 'new' })} onPerson={(id) => setRoute({ page: 'person', id })} />}
      {route.page === 'new' && <Create t={t} id={route.id} onNotice={setNotice} onDone={() => setRoute({ page: 'records' })} />}
      {route.page === 'people' && <People t={t} onNotice={setNotice} onOpen={(id) => setRoute({ page: 'person', id })} onAdd={() => setRoute({ page: 'add' })} />}
      {route.page === 'add' && <PersonForm t={t} onNotice={setNotice} onSaved={(id) => setRoute({ page: 'person', id })} />}
      {route.page === 'person' && <PersonDetail t={t} id={route.id} onNotice={setNotice} onBack={() => setRoute({ page: 'people' })} onCert={() => setRoute({ page: 'new' })} />}
    </div>
  );
}

function Records({ t, tn, onNotice, onEdit, onNew, onPerson }: { t: Translate; tn: (a: string, b: string, n: number) => string; onNotice: (n: Notice) => void; onEdit: (id: number) => void; onNew: () => void; onPerson: (id: number) => void }) {
  const host = useHost();
  const qc = useQueryClient();
  const { scope } = useGraphQL();
  const canManageCap = useFlxCan('flxs_manage');
  const [draft, setDraft] = useState<ListFilter>(emptyFilter);
  const [applied, setApplied] = useState<ListFilter>(emptyFilter);
  const [after, setAfter] = useState<string | null>(null);
  const [stack, setStack] = useState<Array<string | null>>([null]);
  const [picked, setPicked] = useState<number[]>([]);
  const [action, setAction] = useState<FlxCertificateBulkAction | ''>('');
  const [eventId, setEventId] = useState(0);
  const [referrerId, setReferrerId] = useState(0);
  const [forward, setForward] = useState<Row | null>(null);
  const [refRow, setRefRow] = useState<Row | null>(null);
  const filter = useMemo(() => toFilter(applied), [applied]);
  const q = useFlxQuery(FlxCertificateRecords, { first: 20, after, filter });
  const bulk = useFlxMutation(FlxCertificateBulk);
  const status = useFlxMutation(FlxCertificateSetStatus);
  const referrer = useFlxMutation(FlxCertificateSetReferrer);
  const send = useFlxMutation(FlxCertificateSend);
  const data = q.data;
  const meta: Meta | undefined = data?.flxCertificateRecordsMeta;
  const rows = data?.flxCertificates.nodes ?? [];
  const refresh = () => qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxCertificateRecords) });

  const applyBulk = async () => {
    if (!picked.length || !action) {
      onNotice({ kind: 'err', text: t('Nothing selected.') });
      return;
    }
    if (!window.confirm(t('Apply to the selected items?'))) return;
    const res = await bulk.mutateAsync({ input: { ids: picked, action, eventId, referrerId } });
    const payload = res.flxCertificateBulk;
    if (payload?.errors.length) {
      onNotice({ kind: 'err', text: payload.errors.map((e) => e.message).join(' ') });
      return;
    }
    if (payload?.redirect) {
      window.location.href = payload.redirect;
      return;
    }
    onNotice({ kind: 'ok', text: payload?.queued ? t('Queued.') : t('Updated.') });
    setPicked([]);
    refresh();
  };

  const setStat = async (row: Row, next: FlxCertificateStatus) => {
    if (next === 'REVOKED' && !window.confirm(t('Revoke this certificate? The link will show "revoked".'))) return;
    const res = await status.mutateAsync({ input: { id: row.databaseId, status: next } });
    if (res.flxCertificateSetStatus?.errors.length) onNotice({ kind: 'err', text: res.flxCertificateSetStatus.errors.map((e) => e.message).join(' ') });
    else refresh();
  };

  return (
    <div>
      <div className="flxs-acts" style={{ marginBottom: 12 }}>
        {meta?.exportUrl && <a className="flxs-btn" href={meta.exportUrl}>{t('Download Excel')}{data?.flxCertificates.totalCount ? ` (${data.flxCertificates.totalCount})` : ''}</a>}
        <button type="button" className="is-primary" onClick={onNew}>+ {t('New certificate')}</button>
      </div>
      <form className="flxs-filters" onSubmit={(e) => { e.preventDefault(); setAfter(null); setStack([null]); setApplied(draft); }}>
        <input type="search" value={draft.search} placeholder={t('Name, phone, email, certificate no…')} onChange={(e) => setDraft({ ...draft, search: e.target.value })} />
        <select aria-label={t('Status')} value={draft.status} onChange={(e) => setDraft({ ...draft, status: e.target.value as ListFilter['status'] })}>
          <option value="">{t('All statuses')}</option>
          <option value="ACTIVE">{t('Valid')}</option>
          <option value="REVOKED">{t('Cancelled')}</option>
        </select>
        <select aria-label={t('Event')} value={draft.event} onChange={(e) => setDraft({ ...draft, event: e.target.value })}>
          <option value="">{t('All events')}</option>
          <option value="0">{t('No event (%d)', meta?.noEventCount ?? 0)}</option>
          {meta?.events.map((ev) => <option key={ev.id} value={ev.id}>{ev.name}{ev.status === 'CLOSED' ? ' · ' + t('archived') : ''} ({ev.count ?? 0})</option>)}
        </select>
        <select aria-label={t('Template')} value={draft.template} onChange={(e) => setDraft({ ...draft, template: e.target.value })}>
          <option value="">{t('All templates')}</option>
          {meta?.templates.map((tp) => <option key={tp.id} value={tp.id}>{tp.name}</option>)}
        </select>
        <select aria-label={t('Referrer')} value={draft.ref} onChange={(e) => setDraft({ ...draft, ref: e.target.value })}>
          <option value="">{t('All referrers')}</option>
          <option value="0">{t('No referrer (%d)', meta?.noReferrerCount ?? 0)}</option>
          {meta?.referrers.map((u) => <option key={u.id} value={u.id}>{u.name} ({u.count ?? 0})</option>)}
        </select>
        {!meta?.scoped && (
          <select aria-label={t('Created by: anyone')} value={draft.by} onChange={(e) => setDraft({ ...draft, by: e.target.value })}>
            <option value="">{t('Created by: anyone')}</option>
            {meta?.referrers.map((u) => <option key={u.id} value={u.id}>{u.name}</option>)}
          </select>
        )}
        <button type="submit">{t('Filter')}</button>
      </form>
      <div className="flxs-bulk">
        <select value={action} onChange={(e) => setAction(e.target.value as FlxCertificateBulkAction | '')}>
          <option value="">{t('Bulk actions…')}</option>
          <option value="SEND_WHATSAPP">{t('Send via WhatsApp')}</option>
          <option value="SEND_SMS">{t('Send via SMS')}</option>
          <option value="SEND_EMAIL">{t('Send via email')}</option>
          <option value="REVOKE">{t('Revoke')}</option>
          <option value="RESTORE">{t('Make valid')}</option>
          <option value="EVENT">{t('Assign to event…')}</option>
          <option value="REFERRER">{t('Assign referrer…')}</option>
          {(meta?.canManage || canManageCap) && <option value="DELETE">{t('Delete permanently')}</option>}
        </select>
        {action === 'EVENT' && (
          <select value={eventId} onChange={(e) => setEventId(Number(e.target.value))}>
            <option value={0}>{t('— No event —')}</option>
            {meta?.events.filter((ev) => ev.status === 'OPEN').map((ev) => <option key={ev.id} value={ev.id}>{ev.name}</option>)}
          </select>
        )}
        {action === 'REFERRER' && (
          <select value={referrerId} onChange={(e) => setReferrerId(Number(e.target.value))}>
            <option value={0}>{t('— No referrer —')}</option>
            {meta?.referrers.map((u) => <option key={u.id} value={u.id}>{u.name}</option>)}
          </select>
        )}
        <button type="button" onClick={() => void applyBulk()}>{t('Apply')}</button>
      </div>
      {q.isError && <p className="flxs-notice is-error">{q.error.message}</p>}
      <table className="flxs-table">
        <thead>
          <tr>
            <th className="check"><input type="checkbox" checked={rows.length > 0 && picked.length === rows.length} onChange={(e) => setPicked(e.target.checked ? rows.map((r) => r.databaseId) : [])} /></th>
            <th>{t('Certificate')}</th><th>{t('Person')}</th><th>{t('Event / Template')}</th><th>{t('Deliveries')}</th><th>{t('Referrer / Created by')}</th><th>{t('Status')}</th><th></th>
          </tr>
        </thead>
        <tbody>
          {!rows.length && !q.isLoading && (
            <tr><td colSpan={8} className="flxs-empty">{t('No certificates yet.')} <button type="button" onClick={onNew}>{t('Create the first certificate')}</button></td></tr>
          )}
          {rows.map((row) => {
            const who = personBits(row);
            const shown = row.holderName && who.name && row.holderName !== who.name;
            return (
              <tr key={row.id}>
                <th className="check"><input type="checkbox" checked={picked.includes(row.databaseId)} onChange={(e) => setPicked(e.target.checked ? [...picked, row.databaseId] : picked.filter((id) => id !== row.databaseId))} /></th>
                <td>
                  <div className="flxs-cell">
                    {row.imageUrl && <a href={row.pageUrl} target="_blank" rel="noreferrer"><img className="flxs-thumb" src={row.imageUrl} alt="" /></a>}
                    <div>
                      <strong><a href={row.pageUrl} target="_blank" rel="noreferrer">{row.number}</a></strong>
                      <div className="flxs-mut">{row.createdAt?.label}{row.backdated ? ' ' + t('backdated') : ''} · {tn('%d view', '%d views', row.views)}</div>
                    </div>
                  </div>
                </td>
                <td>
                  <button type="button" onClick={() => row.person && onPerson(row.person.databaseId)}>{row.holderName || who.name}</button>
                  {shown && <div className="flxs-mut">{t('Registered person: %s', who.name)}</div>}
                  <div className="flxs-mut">{[who.phone, who.email].filter(Boolean).join(' · ')}</div>
                </td>
                <td>{row.eventName && <strong>{row.eventName}</strong>}<div className="flxs-mut">{row.templateName}</div></td>
                <td>
                  {!row.deliveries.length && <span className="flxs-mut">–</span>}
                  {row.deliveries.map((d) => (
                    <a key={d.channel} className="flxs-chip" href={admin(host.adminUrl, 'page=flxs-kuyruk&cert=' + row.databaseId)}>
                      {d.channel} {d.sent ? d.sent + '✓' : ''}{d.waiting ? ' ' + d.waiting + '…' : ''}{d.failed ? ' ' + d.failed + '✕' : ''}
                    </a>
                  ))}
                </td>
                <td>{row.referrerName || <span className="flxs-mut">–</span>}<div className="flxs-mut">{t('created by: %s', row.createdByName)}</div></td>
                <td><span className={'flxs-pill' + (row.status === 'REVOKED' ? ' is-off' : '')}>{row.status === 'REVOKED' ? t('Cancelled') : t('Valid')}</span></td>
                <td>
                  <details className="flxs-menu">
                    <summary className="flxs-btn">{t('Actions')}</summary>
                    <div className="flxs-menu__list">
                      <a href={row.pageUrl} target="_blank" rel="noreferrer">{t('Open page')}</a>
                      <button type="button" onClick={() => void navigator.clipboard.writeText(row.pageUrl).then(() => onNotice({ kind: 'ok', text: t('Copied.') }))}>{t('Copy link')}</button>
                      {row.pdfUrl && <a href={row.pdfUrl} target="_blank" rel="noreferrer">{t('PDF')}</a>}
                      {row.status === 'ACTIVE' && (
                        <>
                          <button type="button" onClick={() => void send.mutateAsync({ input: { id: row.databaseId, channels: ['WHATSAPP'] } }).then(refresh)}>{t('Send via WhatsApp')}</button>
                          <button type="button" onClick={() => void send.mutateAsync({ input: { id: row.databaseId, channels: ['SMS'] } }).then(refresh)}>{t('Send via SMS')}</button>
                          <button type="button" onClick={() => void send.mutateAsync({ input: { id: row.databaseId, channels: ['EMAIL'] } }).then(refresh)}>{t('Send via email')}</button>
                          <button type="button" onClick={() => setForward(row)}>{t('Send to someone else…')}</button>
                          {row.canEdit && <button type="button" onClick={() => setRefRow(row)}>{t('Update referrer…')}</button>}
                        </>
                      )}
                      {row.canEdit && <button type="button" onClick={() => onEdit(row.databaseId)}>{t('Edit')}</button>}
                      {row.canEdit && row.status === 'ACTIVE' && <button type="button" onClick={() => void setStat(row, 'REVOKED')}>{t('Revoke')}</button>}
                      {row.canEdit && row.status === 'REVOKED' && <button type="button" onClick={() => void setStat(row, 'ACTIVE')}>{t('Make valid')}</button>}
                      <a href={admin(host.adminUrl, 'page=flxs-kuyruk&cert=' + row.databaseId)}>{t('Delivery history')}</a>
                    </div>
                  </details>
                </td>
              </tr>
            );
          })}
        </tbody>
      </table>
      <div className="flxs-acts" style={{ marginTop: 12 }}>
        <button type="button" disabled={stack.length < 2} onClick={() => { const next = stack.slice(0, -1); setStack(next); setAfter(next[next.length - 1] ?? null); }}>{t('Previous')}</button>
        <button type="button" disabled={!data?.flxCertificates.pageInfo.hasNextPage} onClick={() => { const cur = data?.flxCertificates.pageInfo.endCursor; if (!cur) return; setStack([...stack, cur]); setAfter(cur); }}>{t('Next')}</button>
      </div>
      {forward && meta && <Forward t={t} row={forward} providers={meta.providers} onClose={() => setForward(null)} onNotice={onNotice} />}
      {refRow && meta && (
        <Modal t={t} title={t('Update referrer…')} onClose={() => setRefRow(null)}>
          <form onSubmit={(e) => {
            e.preventDefault();
            const id = Number(new FormData(e.currentTarget).get('ref'));
            void referrer.mutateAsync({ input: { id: refRow.databaseId, referrerId: id } }).then((res) => {
              if (res.flxCertificateSetReferrer?.errors.length) onNotice({ kind: 'err', text: res.flxCertificateSetReferrer.errors.map((er) => er.message).join(' ') });
              else { setRefRow(null); refresh(); }
            });
          }}>
            <select name="ref" defaultValue={refRow.referrerId}>
              <option value={0}>{t('— No referrer —')}</option>
              {meta.referrers.map((u) => <option key={u.id} value={u.id}>{u.name}</option>)}
            </select>
            <button className="is-primary" type="submit">{t('Save')}</button>
          </form>
        </Modal>
      )}
    </div>
  );
}

function Forward({ t, row, providers, onClose, onNotice }: { t: Translate; row: Row; providers: Meta['providers']; onClose: () => void; onNotice: (n: Notice) => void }) {
  const sendTo = useFlxMutation(FlxCertificateSendTo);
  const [channel, setChannel] = useState<FlxCertificateChannel>('WHATSAPP');
  return (
    <Modal t={t} title={t('Send to someone else…')} onClose={onClose}>
      <form onSubmit={(e) => {
        e.preventDefault();
        const fd = new FormData(e.currentTarget);
        void sendTo.mutateAsync({
          input: {
            id: row.databaseId,
            channel,
            to: String(fd.get('to') || ''),
            name: String(fd.get('name') || ''),
            subject: String(fd.get('subject') || '') || undefined,
            body: String(fd.get('body') || '') || undefined,
            provider: Number(fd.get('provider') || 0) || undefined,
            save: fd.get('save') === 'on',
          },
        }).then((res) => {
          const errs = res.flxCertificateSendTo?.errors ?? [];
          const bad = res.flxCertificateSendTo?.results.find((r) => !r.ok);
          if (errs.length || bad) onNotice({ kind: 'err', text: errs.map((er) => er.message).join(' ') || bad?.message || '' });
          else { onNotice({ kind: 'ok', text: t('Queued.') }); onClose(); }
        });
      }}>
        <select value={channel} onChange={(e) => setChannel(e.target.value as FlxCertificateChannel)}>
          <option value="WHATSAPP">WhatsApp</option>
          <option value="SMS">SMS</option>
          <option value="EMAIL">{t('E-mail')}</option>
        </select>
        <label className="flxs-field">{t('Recipient')}<input name="to" required /></label>
        <label className="flxs-field">{t('Full name')}<input name="name" /></label>
        {channel === 'EMAIL' && <label className="flxs-field">{t('E-mail')}<input name="subject" /></label>}
        <label className="flxs-field">{t('Note')}<textarea name="body" rows={3} /></label>
        <select name="provider">
          <option value="0">{t('— No referrer —')}</option>
          {providers.filter((p) => p.channel === channel).map((p) => <option key={p.channel + p.id} value={p.id}>{p.name}</option>)}
        </select>
        <label><input type="checkbox" name="save" defaultChecked /> {t('Remember in the directory')}</label>
        <button className="is-primary" type="submit">{t('Send')}</button>
      </form>
    </Modal>
  );
}

function Modal({ t, title, onClose, children }: { t: Translate; title: string; onClose: () => void; children: ReactNode }) {
  return (
    <div className="flxs-modal" role="dialog" aria-modal="true">
      <div>
        <strong>{title}</strong>
        {children}
        <button type="button" onClick={onClose}>{t('Close')}</button>
      </div>
    </div>
  );
}

interface Draft {
  key: number;
  personId: number;
  name: string;
  phone: string;
  email: string;
  values: Record<string, string>;
}

interface TplField { key: string; label: string; type: string; required?: boolean; placeholder?: string }

function fieldsOf(config: string): TplField[] {
  try {
    const parsed = JSON.parse(config) as { fields?: TplField[] };
    return (parsed.fields ?? []).filter((f) => !['static', 'qr', 'number_auto'].includes(f.type));
  } catch {
    return [];
  }
}

function Create({ t, id, onNotice, onDone }: { t: Translate; id?: number; onNotice: (n: Notice) => void; onDone: () => void }) {
  const host = useHost();
  const cfg = useFlxQuery(FlxCertificateCreateConfig, { certificateId: id ?? null });
  const existing = useFlxQuery(FlxCertificateOne, { id: id ?? 0 }, { enabled: !!id });
  const create = useFlxMutation(FlxCertificateCreate);
  const update = useFlxMutation(FlxCertificateUpdate);
  const attach = useFlxMutation(FlxCertificateAttachFiles);
  const send = useFlxMutation(FlxCertificateSend);
  const [tplId, setTplId] = useState(0);
  const [eventId, setEventId] = useState(0);
  const [referrerId, setReferrerId] = useState(0);
  const [backdate, setBackdate] = useState(false);
  const [issued, setIssued] = useState('');
  const [channels, setChannels] = useState<FlxCertificateChannel[]>([]);
  const [rows, setRows] = useState<Draft[]>([{ key: 1, personId: 0, name: '', phone: '', email: '', values: {} }]);
  const [paste, setPaste] = useState('');
  const [showPaste, setShowPaste] = useState(false);
  const [busy, setBusy] = useState('');
  const [results, setResults] = useState<Array<{ name: string; url: string; pdf: string | null; bytes: Uint8Array }>>([]);
  const [active, setActive] = useState(0);
  const canvasId = 'flxs-canvas';
  const config: FlxCertificateCreateConfigQuery['flxCertificateCreateConfig'] | undefined = cfg.data?.flxCertificateCreateConfig;
  const tpl = config?.templates.find((item) => item.id === tplId) ?? config?.templates[0];
  const fields = tpl ? fieldsOf(tpl.config) : [];

  useEffect(() => {
    if (!config) return;
    setTplId((cur) => cur || existing.data?.flxCertificate?.templateId || config.lastTemplateId || config.templates[0]?.id || 0);
    setIssued(config.today);
    if (existing.data?.flxCertificate) {
      const row = existing.data.flxCertificate;
      const who = personBits(row);
      let values: Record<string, string> = {};
      try { values = JSON.parse(row.values) as Record<string, string>; } catch { values = {}; }
      setEventId(row.eventId);
      setReferrerId(row.referrerId);
      setRows([{ key: 1, personId: row.person?.databaseId ?? 0, name: who.name || row.holderName, phone: who.phone, email: who.email, values }]);
    }
  }, [config, existing.data]);

  useEffect(() => {
    if (!config?.rendererScripts.length || !tpl) return;
    let stop = false;
    const row = rows[active] ?? rows[0];
    void ensureRenderer(config.rendererScripts, config.rendererConfig).then(async (api) => {
      if (stop || !row) return;
      const canvas = document.getElementById(canvasId) as HTMLCanvasElement | null;
      if (!canvas) return;
      const values = { ...row.values, ad_soyad: row.name };
      const bytes = await api.renderPdf(JSON.parse(tpl.config), values, { number: '—', link: '', code: '', created: backdate ? issued : config.today }, { preview: true });
      await api.renderToCanvas(bytes, canvas, 900);
    }).catch(() => undefined);
    return () => { stop = true; };
  }, [config, tpl, rows, active, backdate, issued]);

  const submit = async (e: FormEvent) => {
    e.preventDefault();
    if (!tpl || !config) return;
    const missing = rows.some((r) => !r.name.trim());
    if (missing) { onNotice({ kind: 'err', text: t('Name is required.') }); return; }
    let api: FlxsRenderApi;
    try {
      setBusy(t('Drawing the PDF…'));
      api = await ensureRenderer(config.rendererScripts, config.rendererConfig);
    } catch {
      onNotice({ kind: 'err', text: t('The PDF library could not be loaded.') });
      setBusy('');
      return;
    }
    const made: Array<{ name: string; url: string; pdf: string | null; bytes: Uint8Array }> = [];
    for (const row of id ? rows.slice(0, 1) : rows) {
      const values = JSON.stringify({ ...row.values, ad_soyad: row.name });
      const person = { id: row.personId || undefined, name: row.name, phone: row.phone, email: row.email };
      setBusy(t('Saving…'));
      const saved = id
        ? await update.mutateAsync({ input: { id, person, values, eventId, referrerId } })
        : await create.mutateAsync({ input: { templateId: tpl.id, person, values, eventId, referrerId, issued: backdate ? issued : undefined } });
      const payload = id ? saved && 'flxCertificateUpdate' in saved ? saved.flxCertificateUpdate : null : 'flxCertificateCreate' in saved ? saved.flxCertificateCreate : null;
      if (!payload || payload.errors.length || !payload.certificate) {
        onNotice({ kind: 'err', text: payload?.errors.map((er) => er.message).join(' ') || t('Required.') });
        setBusy('');
        return;
      }
      const cert = payload.certificate;
      setBusy(t('Drawing the PDF…'));
      const bytes = await api.renderPdf(JSON.parse(tpl.config), { ...row.values, ad_soyad: row.name }, { number: cert.number, link: cert.pageUrl, code: cert.code, created: backdate ? issued : config.today });
      const jpeg = await api.toJpeg(bytes, 1600, 0.82);
      let ogId = '';
      try {
        const og = await api.toShareImage(bytes, { title: cert.title, name: row.name, org: String((window.FLXS_RENDER?.org as string) || '') });
        setBusy(t('Uploading…'));
        ogId = await uploadFile(host.client, og.blob, 'paylasim.jpg');
      } catch { ogId = ''; }
      setBusy(t('Uploading…'));
      const pdfId = await uploadFile(host.client, bytesToBlob(bytes, 'application/pdf'), 'sertifika.pdf');
      const imageId = await uploadFile(host.client, jpeg.blob, 'sertifika.jpg');
      const attached = await attach.mutateAsync({ input: { id: cert.databaseId, pdfUploadId: pdfId, imageUploadId: imageId, ogUploadId: ogId || undefined, width: jpeg.w, height: jpeg.h } });
      if (channels.length && !id) {
        setBusy(t('Queueing…'));
        await send.mutateAsync({ input: { id: cert.databaseId, channels } });
      }
      made.push({ name: row.name, url: cert.pageUrl, pdf: attached.flxCertificateAttachFiles?.certificate?.pdfUrl ?? cert.pdfUrl, bytes });
    }
    setResults(made);
    setBusy(t('Done.'));
    onNotice({ kind: 'ok', text: t('Done.') });
  };

  if (cfg.isLoading) return <p className="flxs-mut">{t('Saving…')}</p>;
  if (!config?.templates.length) return <p className="flxs-notice">{t('No active template.')}</p>;

  return (
    <form className="flxs-two" onSubmit={(e) => void submit(e)}>
      <div>
        <section className="flxs-card">
          <h2>{t('Template')}</h2>
          <div className="flxs-acts">
            {config.templates.map((item) => <button type="button" key={item.id} className={item.id === tpl?.id ? 'is-primary' : ''} onClick={() => setTplId(item.id)}>{item.name}</button>)}
          </div>
        </section>
        <section className="flxs-card">
          <div className="flxs-head">
            <h2>{t('People on this certificate')}</h2>
            {!id && (
              <span className="flxs-acts">
                <button type="button" onClick={() => setRows([{ key: Date.now(), personId: 0, name: '', phone: '', email: '', values: {} }])}>{t('Clear')}</button>
                <button type="button" onClick={() => setShowPaste((v) => !v)}>{t('Paste a list')}</button>
              </span>
            )}
          </div>
          {showPaste && !id && (
            <div>
              <p className="flxs-mut">{t('Each line is one person: Name; phone; e-mail')}</p>
              <textarea rows={4} value={paste} onChange={(e) => setPaste(e.target.value)} />
              <button type="button" onClick={() => {
                const next = paste.split(/\r?\n/).map((line) => line.trim()).filter(Boolean).map((line, i) => {
                  const parts = line.split(/[;\t]/).map((p) => p.trim());
                  return { key: Date.now() + i, personId: 0, name: parts[0] || '', phone: parts[1] || '', email: parts[2] || '', values: {} };
                });
                if (next.length) setRows(next);
              }}>{t('Add to the list')}</button>
            </div>
          )}
          <label className="flxs-field">{t('Event')}
            <select value={eventId} onChange={(e) => setEventId(Number(e.target.value))}>
              <option value={0}>{t('— No event —')}</option>
              {config.events.map((ev) => <option key={ev.id} value={ev.id} disabled={ev.status === 'CLOSED' && ev.id !== eventId}>{ev.name}</option>)}
            </select>
          </label>
          <label className="flxs-field">{t('Referrer')}
            <select value={referrerId} onChange={(e) => setReferrerId(Number(e.target.value))}>
              <option value={0}>{t('— No referrer —')}</option>
              {config.referrers.map((u) => <option key={u.id} value={u.id}>{u.name}</option>)}
            </select>
          </label>
          {!id && config.canManage && (
            <label><input type="checkbox" checked={backdate} onChange={(e) => setBackdate(e.target.checked)} /> {t('Backdate')}
              {backdate && <input type="date" value={issued} max={config.today} min="2015-01-01" onChange={(e) => setIssued(e.target.value)} />}
            </label>
          )}
          <div className="flxs-rows">
            {rows.map((row, index) => (
              <div className="flxs-row" key={row.key} onFocus={() => setActive(index)}>
                <label className="flxs-field">{t('Full name')}<input required value={row.name} onChange={(e) => setRows(rows.map((r) => r.key === row.key ? { ...r, name: e.target.value } : r))} /></label>
                <label className="flxs-field">{t('Phone')}<input value={row.phone} onChange={(e) => setRows(rows.map((r) => r.key === row.key ? { ...r, phone: e.target.value } : r))} /></label>
                <label className="flxs-field">{t('E-mail')}<input value={row.email} onChange={(e) => setRows(rows.map((r) => r.key === row.key ? { ...r, email: e.target.value } : r))} /></label>
                {fields.filter((f) => f.key !== 'ad_soyad').map((f) => (
                  <label className="flxs-field" key={f.key}>{f.label}
                    <input required={!!f.required} placeholder={f.placeholder} value={row.values[f.key] ?? ''} onChange={(e) => setRows(rows.map((r) => r.key === row.key ? { ...r, values: { ...r.values, [f.key]: e.target.value } } : r))} />
                  </label>
                ))}
              </div>
            ))}
          </div>
          {!id && <button type="button" onClick={() => setRows([...rows, { key: Date.now(), personId: 0, name: '', phone: '', email: '', values: {} }])}>{t('Add a row')}</button>}
        </section>
        {!id && (
          <section className="flxs-card">
            <h2>{t('Sending')} <small className="flxs-mut">{t('optional')}</small></h2>
            {(['WHATSAPP', 'SMS', 'EMAIL'] as FlxCertificateChannel[]).map((ch) => (
              <label key={ch}><input type="checkbox" checked={channels.includes(ch)} onChange={(e) => setChannels(e.target.checked ? [...channels, ch] : channels.filter((c) => c !== ch))} /> {ch === 'EMAIL' ? t('Send via email') : ch === 'SMS' ? t('Send via SMS') : t('Send via WhatsApp')}</label>
            ))}
          </section>
        )}
        <button className="is-primary" type="submit" disabled={!!busy && busy !== t('Done.')}>{id ? t('Save and refresh the PDF') : t('Create certificates')}</button>
        {busy && <span className="flxs-mut"> {busy}</span>}
        {!!results.length && (
          <section className="flxs-card">
            <h2>{t('Created')}</h2>
            <div className="flxs-acts">
              <button type="button" onClick={() => void navigator.clipboard.writeText(results.map((r) => r.name + ': ' + r.url).join('\n'))}>{t('Copy links')}</button>
              <button type="button" onClick={() => void mergePdfs(results.map((r) => r.bytes)).then((blob) => {
                const a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = 'sertifikalar.pdf';
                a.click();
              })}>{t('Download one PDF')}</button>
              <button type="button" onClick={onDone}>{t('New list')}</button>
            </div>
            {results.map((r) => <div key={r.url}><a href={r.url} target="_blank" rel="noreferrer">{r.name}</a> {r.pdf && <a href={r.pdf}>{t('PDF')}</a>}</div>)}
          </section>
        )}
      </div>
      <aside className="flxs-card flxs-preview">
        <strong>{t('Live preview')}</strong>
        <canvas id={canvasId} />
      </aside>
    </form>
  );
}

async function uploadFile(client: ApiClient, blob: Blob, name: string): Promise<string> {
  const fd = new FormData();
  fd.append('context', 'flx-certificates/file');
  fd.append('file', blob, name);
  const res = await client<{ uploadId: string }>('flx/v1/upload', { method: 'POST', formData: fd });
  return res.uploadId;
}

function People({ t, onNotice, onOpen, onAdd }: { t: Translate; onNotice: (n: Notice) => void; onOpen: (id: number) => void; onAdd: () => void }) {
  const host = useHost();
  const canBulk = useFlxCan('flxs_bulk');
  const [search, setSearch] = useState('');
  const [applied, setApplied] = useState('');
  const [after, setAfter] = useState<string | null>(null);
  const [picked, setPicked] = useState<number[]>([]);
  const [channel, setChannel] = useState('sms');
  const q = useFlxQuery(FlxCertificateDirectory, { search: applied, first: 30, after });
  const rows: PersonRow[] = q.data?.flxCertificateDirectory.nodes ?? [];
  return (
    <div>
      <p className="flxs-mut">{t('Everyone who receives a certificate is listed here. The same phone or e-mail links to the existing person.')}</p>
      <div className="flxs-acts" style={{ margin: '8px 0' }}><button type="button" className="is-primary" onClick={onAdd}>+ {t('Add person')}</button></div>
      <form className="flxs-filters" onSubmit={(e) => { e.preventDefault(); setAfter(null); setApplied(search); }}>
        <input type="search" value={search} placeholder={t('Name, phone or e-mail…')} onChange={(e) => setSearch(e.target.value)} />
        <button type="submit">{t('Search')}</button>
      </form>
      {canBulk && (
        <div className="flxs-bulk">
          <select value={channel} onChange={(e) => setChannel(e.target.value)}>
            <option value="whatsapp">WhatsApp</option>
            <option value="sms">SMS</option>
            <option value="email">{t('E-mail')}</option>
          </select>
          <button type="button" onClick={() => {
            if (!picked.length) { onNotice({ kind: 'err', text: t('Nothing selected.') }); return; }
            window.location.href = admin(host.adminUrl, 'page=flxs-toplu&people=' + picked.join(',') + '&channel=' + channel);
          }}>{t('Message selected')}</button>
        </div>
      )}
      <table className="flxs-table">
        <thead><tr><th></th><th>{t('Full name')}</th><th>{t('Phone')}</th><th>{t('E-mail')}</th><th>{t('Certificates count')}</th><th>{t('Added')}</th></tr></thead>
        <tbody>
          {!rows.length && !q.isLoading && <tr><td colSpan={6} className="flxs-empty">{t('No people yet.')}</td></tr>}
          {rows.map((p) => (
            <tr key={p.id}>
              <th><input type="checkbox" checked={picked.includes(p.databaseId)} onChange={(e) => setPicked(e.target.checked ? [...picked, p.databaseId] : picked.filter((id) => id !== p.databaseId))} /></th>
              <td><button type="button" onClick={() => onOpen(p.databaseId)}>{p.name}</button></td>
              <td>{p.phone?.display}</td>
              <td>{p.email}</td>
              <td>{p.certificateCount}</td>
              <td className="flxs-mut">{p.createdAt?.label} · {p.createdByName}</td>
            </tr>
          ))}
        </tbody>
      </table>
      <div className="flxs-acts" style={{ marginTop: 12 }}>
        <button type="button" disabled={!q.data?.flxCertificateDirectory.pageInfo.hasNextPage} onClick={() => setAfter(q.data?.flxCertificateDirectory.pageInfo.endCursor ?? null)}>{t('Next')}</button>
      </div>
    </div>
  );
}

function PersonForm({ t, id, initial, onNotice, onSaved }: { t: Translate; id?: number; initial?: { name: string; phone: string; email: string; note: string }; onNotice: (n: Notice) => void; onSaved: (id: number) => void }) {
  const save = useFlxMutation(FlxCertificatePersonSave);
  return (
    <form className="flxs-card" onSubmit={(e) => {
      e.preventDefault();
      const fd = new FormData(e.currentTarget);
      void save.mutateAsync({ input: { id, name: String(fd.get('name') || ''), phone: String(fd.get('phone') || ''), email: String(fd.get('email') || ''), note: String(fd.get('note') || '') } }).then((res) => {
        const payload = res.flxCertificatePersonSave;
        if (payload?.errors.length || !payload?.person) onNotice({ kind: 'err', text: payload?.errors.map((er) => er.message).join(' ') || t('Required.') });
        else onSaved(payload.person.databaseId);
      });
    }}>
      <label className="flxs-field">{t('Full name')}<input name="name" required defaultValue={initial?.name} /></label>
      <label className="flxs-field">{t('Phone')}<input name="phone" defaultValue={initial?.phone} /></label>
      <label className="flxs-field">{t('E-mail')}<input name="email" type="email" defaultValue={initial?.email} /></label>
      <label className="flxs-field">{t('Note')}<textarea name="note" rows={2} defaultValue={initial?.note} /></label>
      <button className="is-primary" type="submit">{t('Save')}</button>
    </form>
  );
}

function PersonDetail({ t, id, onNotice, onBack, onCert }: { t: Translate; id: number; onNotice: (n: Notice) => void; onBack: () => void; onCert: () => void }) {
  const host = useHost();
  const q = useFlxQuery(FlxCertificatePerson, { id });
  const msgs = useFlxQuery(FlxCertificatePersonMessages, { personId: id });
  const del = useFlxMutation(FlxCertificatePersonDelete);
  const canManage = useFlxCan('flxs_manage');
  const person = q.data?.flxCertificatePerson;
  const certs = q.data?.flxCertificates;
  if (q.isLoading) return <p className="flxs-mut">{t('Saving…')}</p>;
  if (!person) return <p className="flxs-notice is-error">{t('No people yet.')}</p>;
  return (
    <div className="flxs-two">
      <div>
        <div className="flxs-acts">
          <button type="button" onClick={onBack}>{t('Back to people')}</button>
          <button type="button" className="is-primary" onClick={onCert}>{t('Certificate for this person')}</button>
        </div>
        <PersonForm t={t} id={person.databaseId} initial={{ name: person.name, phone: person.phone?.display ?? '', email: person.email ?? '', note: person.note ?? '' }} onNotice={onNotice} onSaved={() => onNotice({ kind: 'ok', text: t('Updated.') })} />
        {canManage && <button type="button" onClick={() => {
          if (!window.confirm(t('Delete this person? A person with certificates cannot be deleted.'))) return;
          void del.mutateAsync({ input: { id: person.databaseId } }).then((res) => {
            if (res.flxCertificatePersonDelete?.errors.length) onNotice({ kind: 'err', text: res.flxCertificatePersonDelete.errors.map((e) => e.message).join(' ') });
            else onBack();
          });
        }}>{t('Delete person')}</button>}
      </div>
      <div>
        <section className="flxs-card">
          <h2>{t('Certificates')} ({certs?.totalCount ?? 0})</h2>
          {!certs?.nodes.length && <p className="flxs-mut">{t('No certificates.')}</p>}
          {certs?.nodes.map((c) => <div key={c.id}><a href={c.pageUrl} target="_blank" rel="noreferrer">{c.number}</a> · {c.templateName} · {c.createdAt?.label}</div>)}
        </section>
        <section className="flxs-card">
          <h2>{t('Messages')} ({msgs.data?.flxCertificatePersonMessages.length ?? 0})</h2>
          {!msgs.data?.flxCertificatePersonMessages.length && <p className="flxs-mut">{t('No messages.')}</p>}
          {msgs.data?.flxCertificatePersonMessages.map((m, i) => <div key={i}>{m.channel} {m.recipient} · {m.status} · {m.at?.label} <span className="flxs-mut">{m.createdByName}</span></div>)}
          <p><a href={admin(host.adminUrl, 'page=flxs-kuyruk')}>{t('Delivery history')}</a></p>
        </section>
      </div>
    </div>
  );
}
