// Records, new certificate and people: the classic pages' layout (page-certificates.php, page-create.php,
// page-people.php) with the classic markup, so the plugin's assets/css/admin.css styles them as before.
// Reads and writes go through plugins/flx-certificates/graphql/operations. The other pages are PHP islands.
import { useCallback, useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react';
import { ListBar, ListPages, Screen, listPerPage } from '@fluxwp/ui';
import { useTranslation } from 'react-i18next';
import { useQueryClient } from '@tanstack/react-query';
import { createGraphQLClient } from '@fluxwp/graphql';
import { FlxGraphQLProvider, flxQueryKey, useFlxCan, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import {
  FlxCertificateBulk,
  FlxCertificateDirectory,
  FlxCertificatePerson,
  FlxCertificatePersonDelete,
  FlxCertificatePersonMessages,
  FlxCertificatePersonSave,
  FlxCertificateRecords,
  FlxCertificateSend,
  FlxCertificateSetReferrer,
  FlxCertificateSetStatus,
} from '@fluxwp/graphql/operations';
import type { FlxCertificateBulkAction, FlxCertificateDirectoryQuery, FlxCertificateFilterInput, FlxCertificateRecordsQuery, FlxCertificateStatus } from '@fluxwp/graphql/types';
import { useHost } from '../host';
import { translator, type Translate } from './i18n';
import { Create } from './Create';
import { CHANNEL_ICON, Forward, ask, HeadActions, HeadCtx, Modal, SlotCtx, admin, useHead, type Head, type Notice } from './shared';
import './certificates.css';

type Island = 'dashboard' | 'events' | 'queue' | 'bulk' | 'templates' | 'logs' | 'settings' | 'system';
type Route = { page: 'records'; person?: number } | { page: 'new'; id?: number } | { page: 'people' } | { page: 'add' } | { page: 'person'; id: number } | { page: Island };

/** PHP pages the screen shows under its header (flux-ui.php prints the open one hidden): wp-admin slug. */
const ISLANDS: Record<Island, string> = {
  dashboard: 'flxs-dashboard',
  events: 'flxs-etkinlikler',
  queue: 'flxs-kuyruk',
  bulk: 'flxs-toplu',
  templates: 'flxs-sablonlar',
  logs: 'flxs-kayitlar',
  settings: 'flxs-ayarlar',
  system: 'flxs-sistem',
};
const ISLAND_LABELS: Record<Island, string> = {
  dashboard: 'Dashboard',
  events: 'Events',
  queue: 'Send queue',
  bulk: 'Bulk send',
  templates: 'Templates',
  logs: 'Log',
  settings: 'Settings',
  system: 'System check',
};
const isIsland = (p: string): p is Island => p in ISLANDS;
type Row = FlxCertificateRecordsQuery['flxCertificates']['nodes'][number];
type PersonRow = FlxCertificateDirectoryQuery['flxCertificateDirectory']['nodes'][number];

interface ListFilter {
  search: string;
  status: '' | FlxCertificateStatus;
  event: string;
  template: string;
  ref: string;
  by: string;
  person: number;
}

const emptyFilter = (person = 0): ListFilter => ({ search: '', status: '', event: '', template: '', ref: '', by: '', person });

function routeOf(start?: string): Route {
  if (start === 'new') return { page: 'new' };
  if (start?.startsWith('new:')) return { page: 'new', id: Number(start.slice(4)) || undefined };
  if (start === 'people') return { page: 'people' };
  if (start === 'people:add') return { page: 'add' };
  if (start?.startsWith('person:')) return { page: 'person', id: Number(start.slice(7)) || 0 };
  if (start?.startsWith('records:')) return { page: 'records', person: Number(start.slice(8)) || undefined };
  if (start && isIsland(start)) return { page: start };
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
  if (f.person) out.personId = f.person;
  return out;
}

/** Cursor of the row before an offset (flx_gql_cursor). */
const afterOf = (offset: number) => (offset > 0 ? btoa('flxo:' + (offset - 1)) : null);

function bulkChannel(value: string): string {
  if (value === 'whatsapp') return 'whatsapp';
  if (value === 'email') return 'email';
  return 'sms';
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
  const [slot, setSlot] = useState<HTMLDivElement | null>(null);
  const [head, setHeadState] = useState<Head>({});
  const setHead = useCallback((h: Head) => setHeadState((cur) => (cur.title === h.title && cur.sub === h.sub ? cur : h)), []);
  // The island tabs this user has (wp-admin only; flux-ui.php lists them on the page).
  const islands = useMemo<Island[]>(() => {
    try {
      const raw = typeof document !== 'undefined' ? document.getElementById('flxs-islands')?.dataset.tabs : undefined;
      const list: unknown = raw ? JSON.parse(raw) : [];
      return Array.isArray(list) ? list.filter((x): x is Island => typeof x === 'string' && isIsland(x)) : [];
    } catch {
      return [];
    }
  }, []);
  const island = isIsland(route.page) ? route.page : null;
  // Opened in place from another screen: the island is not on this page, load it.
  useEffect(() => {
    if (island && host.mode === 'wp' && !document.querySelector(`[data-flxs-island="${island}"]`)) location.assign(admin(host.adminUrl, 'page=' + ISLANDS[island]));
  }, [island, host.mode, host.adminUrl]);
  const tab = route.page === 'add' || route.page === 'person' ? 'people' : route.page;
  const tabs = [{ id: 'records', label: t('Certificates') }, { id: 'new', label: t('New certificate') }, { id: 'people', label: t('People') }, ...islands.map((i) => ({ id: i as string, label: t(ISLAND_LABELS[i]) }))];
  const go = (id: string) => {
    setNotice(null);
    if (isIsland(id)) host.openUrl(admin(host.adminUrl, 'page=' + ISLANDS[id]));
    else setRoute(id === 'new' ? { page: 'new' } : id === 'people' ? { page: 'people' } : { page: 'records' });
  };
  const open = (r: Route) => {
    setNotice(null);
    setRoute(r);
    window.scrollTo({ top: 0 });
  };
  return (
    <HeadCtx.Provider value={setHead}>
      <SlotCtx.Provider value={slot}>
        <Screen<string>
          className="flxs-app"
          width="wide"
          icon={host.appIcon}
          title={head.title || t('Certificates')}
          subtitle={head.sub || undefined}
          actions={<div className="flxs-head__actions" ref={setSlot} />}
          notice={
            notice ? (
              <div className={'flxs-notice' + (notice.kind === 'err' ? ' is-error' : '')}>
                {notice.text}{' '}
                <button type="button" className="button-link" onClick={() => setNotice(null)}>
                  {t('Close')}
                </button>
              </div>
            ) : null
          }
          tabs={tabs}
          tab={tab}
          onTab={go}
        >
          <div className="flxs-wrap flx-raw">
            {island ? <IslandNode id={island} slot={slot} onHead={setHead} /> : null}
            {route.page === 'records' && <Records key={route.person ?? 0} t={t} tn={tn} person={route.person} onNotice={setNotice} onEdit={(id) => open({ page: 'new', id })} onNew={() => open({ page: 'new' })} />}
            {route.page === 'new' && <Create key={route.id ?? 0} t={t} id={route.id} onNotice={setNotice} onNew={() => open({ page: 'new' })} />}
            {route.page === 'people' && <People t={t} onNotice={setNotice} onOpen={(id) => open({ page: 'person', id })} onAdd={() => open({ page: 'add' })} onCerts={(id) => open({ page: 'records', person: id })} />}
            {route.page === 'add' && <AddPerson t={t} onNotice={setNotice} onSaved={(id) => open({ page: 'person', id })} />}
            {route.page === 'person' && <PersonDetail t={t} id={route.id} onNotice={setNotice} onBack={() => open({ page: 'people' })} onCert={() => open({ page: 'new' })} />}
          </div>
        </Screen>
      </SlotCtx.Provider>
    </HeadCtx.Provider>
  );
}

/**
 * Moves the page's hidden island (flux-ui.php) into the screen while it is open. Its header
 * (flxs_admin_header: title, subtitle, buttons) goes to the screen header, as on the classic page.
 */
function IslandNode({ id, slot, onHead }: { id: string; slot: HTMLElement | null; onHead: (h: Head) => void }) {
  const ref = useRef<HTMLDivElement>(null);
  useLayoutEffect(() => {
    const el = document.querySelector<HTMLElement>(`[data-flxs-island="${CSS.escape(id)}"]`);
    const home = el?.parentElement ?? null;
    const box = ref.current;
    if (!el || !box) return;
    box.appendChild(el);
    const hd = el.querySelector<HTMLElement>('.flxs-head--island');
    const acts = hd?.querySelector<HTMLElement>('.flxs-head__actions') ?? null;
    if (hd) onHead({ title: hd.dataset.title || undefined, sub: hd.dataset.sub || undefined });
    if (acts && slot) slot.appendChild(acts);
    return () => {
      if (acts && hd && acts.parentElement !== hd) hd.appendChild(acts);
      if (home && el.parentElement === box) home.appendChild(el);
    };
  }, [id, slot, onHead]);
  return <div ref={ref} className="flxs-islandslot" />;
}

/** One action in the classic "Actions" menu: a link, as page-certificates.php printed it. */
function MenuAction({ onClick, children }: { onClick: () => void; children: string }) {
  return (
    <a
      href="#"
      onClick={(e) => {
        e.preventDefault();
        (e.currentTarget.closest('details') as HTMLDetailsElement | null)?.removeAttribute('open');
        onClick();
      }}
    >
      {children}
    </a>
  );
}

function Records({
  t,
  tn,
  person,
  onNotice,
  onEdit,
  onNew,
}: {
  t: Translate;
  tn: (a: string, b: string, n: number) => string;
  person?: number;
  onNotice: (n: Notice) => void;
  onEdit: (id: number) => void;
  onNew: () => void;
}) {
  const host = useHost();
  const qc = useQueryClient();
  const { scope } = useGraphQL();
  const canManageCap = useFlxCan('flxs_manage');
  const [draft, setDraft] = useState<ListFilter>(() => emptyFilter(person));
  const [applied, setApplied] = useState<ListFilter>(() => emptyFilter(person));
  const [per, setPer] = useState(() => listPerPage('flxs-certs', 20));
  const [page, setPage] = useState(1);
  const [picked, setPicked] = useState<number[]>([]);
  const [action, setAction] = useState<FlxCertificateBulkAction | ''>('');
  const [eventId, setEventId] = useState(0);
  const [referrerId, setReferrerId] = useState(0);
  const [forward, setForward] = useState<Row | null>(null);
  const [refRow, setRefRow] = useState<Row | null>(null);
  const filter = useMemo(() => toFilter(applied), [applied]);
  const q = useFlxQuery(FlxCertificateRecords, { first: per, after: afterOf((page - 1) * per), filter }, { placeholderData: (old) => old });
  const bulk = useFlxMutation(FlxCertificateBulk);
  const status = useFlxMutation(FlxCertificateSetStatus);
  const referrer = useFlxMutation(FlxCertificateSetReferrer);
  const send = useFlxMutation(FlxCertificateSend);
  const data = q.data;
  const meta = data?.flxCertificateRecordsMeta;
  const rows = data?.flxCertificates.nodes ?? [];
  const total = data?.flxCertificates.totalCount ?? 0;
  const refresh = () => qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxCertificateRecords) });
  useHead(t('Certificates'));
  const apply = (next: ListFilter) => {
    setDraft(next);
    setApplied(next);
    setPage(1);
    setPicked([]);
  };
  const more = (applied.template ? 1 : 0) + (applied.ref !== '' ? 1 : 0) + (applied.by ? 1 : 0);

  const applyBulk = async () => {
    if (!picked.length || !action) {
      onNotice({ kind: 'err', text: t('Nothing selected.') });
      return;
    }
    if (!(await ask(t('Apply to the selected items?')))) return;
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
    void refresh();
  };

  const setStat = async (row: Row, next: FlxCertificateStatus) => {
    const msg = t('Revoke this certificate? The link will show "revoked".');
    if (next === 'REVOKED' && !(await ask(msg, true))) return;
    const res = await status.mutateAsync({ input: { id: row.databaseId, status: next } });
    if (res.flxCertificateSetStatus?.errors.length) onNotice({ kind: 'err', text: res.flxCertificateSetStatus.errors.map((e) => e.message).join(' ') });
    else void refresh();
  };
  const quick = (row: Row, ch: 'WHATSAPP' | 'SMS' | 'EMAIL') =>
    void send.mutateAsync({ input: { id: row.databaseId, channels: [ch] } }).then((res) => {
      const p = res.flxCertificateSend;
      const bad = p?.results.find((r) => !r.ok);
      if (p?.errors.length || bad) onNotice({ kind: 'err', text: p?.errors.map((e) => e.message).join(' ') || bad?.message || '' });
      else onNotice({ kind: 'ok', text: t('Queued.') });
      void refresh();
    });

  return (
    <>
      <HeadActions>
        {meta?.exportUrl ? (
          <a href={meta.exportUrl} className="button flxs-export">
            {t('Download Excel')}
            {total ? ` (${new Intl.NumberFormat(document.documentElement.lang || undefined).format(total)})` : ''}
          </a>
        ) : null}{' '}
        <a
          href="#"
          className="button button-primary"
          onClick={(e) => {
            e.preventDefault();
            onNew();
          }}
        >
          + {t('New certificate')}
        </a>
      </HeadActions>
      <ListBar
        id="flxs-certs"
        className="flxs-filters"
        onSubmit={() => apply(draft)}
        onClear={more || applied.search || applied.status || applied.event !== '' ? () => apply(emptyFilter(person)) : undefined}
        moreActive={more}
        search={<input type="search" value={draft.search} placeholder={t('Name, phone, email, certificate no…')} onChange={(e) => setDraft({ ...draft, search: e.target.value })} />}
        primary={
          <>
            <select aria-label={t('Status')} value={draft.status} onChange={(e) => apply({ ...draft, status: e.target.value as ListFilter['status'] })}>
              <option value="">{t('All statuses')}</option>
              <option value="ACTIVE">{t('Valid')}</option>
              <option value="REVOKED">{t('Cancelled')}</option>
            </select>
            <select aria-label={t('Event')} value={draft.event} onChange={(e) => apply({ ...draft, event: e.target.value })}>
              <option value="">{t('All events')}</option>
              <option value="0">{t('No event (%d)', meta?.noEventCount ?? 0)}</option>
              {meta?.events.map((ev) => (
                <option key={ev.id} value={ev.id}>
                  {ev.name}
                  {ev.status === 'CLOSED' ? ' · ' + t('archived') : ''} ({ev.count ?? 0})
                </option>
              ))}
            </select>
          </>
        }
        more={
          <>
            <select aria-label={t('Template')} value={draft.template} onChange={(e) => apply({ ...draft, template: e.target.value })}>
              <option value="">{t('All templates')}</option>
              {meta?.templates.map((tp) => (
                <option key={tp.id} value={tp.id}>
                  {tp.name}
                </option>
              ))}
            </select>
            <select aria-label={t('Referrer')} value={draft.ref} onChange={(e) => apply({ ...draft, ref: e.target.value })}>
              <option value="">{t('All referrers')}</option>
              <option value="0">{t('No referrer (%d)', meta?.noReferrerCount ?? 0)}</option>
              {meta?.referrers.map((u) => (
                <option key={u.id} value={u.id}>
                  {u.name} ({u.count ?? 0})
                </option>
              ))}
            </select>
            {!meta?.scoped && (
              <select aria-label={t('Created by')} value={draft.by} onChange={(e) => apply({ ...draft, by: e.target.value })}>
                <option value="">{t('Created by: anyone')}</option>
                {meta?.referrers.map((u) => (
                  <option key={u.id} value={u.id}>
                    {u.name}
                  </option>
                ))}
              </select>
            )}
          </>
        }
      />
      <form className={picked.length ? 'flxs-has-sel' : undefined} onSubmit={(e) => e.preventDefault()}>
        <div className="flxs-bulkbar">
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
              {meta?.events
                .filter((ev) => ev.status === 'OPEN')
                .map((ev) => (
                  <option key={ev.id} value={ev.id}>
                    {ev.name}
                  </option>
                ))}
            </select>
          )}
          {action === 'REFERRER' && (
            <select value={referrerId} onChange={(e) => setReferrerId(Number(e.target.value))}>
              <option value={0}>{t('— No referrer —')}</option>
              {meta?.referrers.map((u) => (
                <option key={u.id} value={u.id}>
                  {u.name}
                </option>
              ))}
            </select>
          )}
          <button type="button" className="button" onClick={() => void applyBulk()}>
            {t('Apply')}
          </button>
        </div>
        {q.isError && <p className="flxs-notice is-error">{q.error.message}</p>}
        <table className="widefat flxs-table flxs-table--certs">
          <thead>
            <tr>
              <td className="check-column">
                <input type="checkbox" checked={rows.length > 0 && picked.length === rows.length} onChange={(e) => setPicked(e.target.checked ? rows.map((r) => r.databaseId) : [])} />
              </td>
              <th>{t('Certificate')}</th>
              <th>{t('Person')}</th>
              <th>{t('Event / Template')}</th>
              <th>{t('Deliveries')}</th>
              <th>{t('Referrer / Created by')}</th>
              <th>{t('Status')}</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {!rows.length && !q.isLoading && (
              <tr>
                <td colSpan={8} className="flxs-empty">
                  {t('No certificates yet.')}{' '}
                  <a
                    href="#"
                    onClick={(e) => {
                      e.preventDefault();
                      onNew();
                    }}
                  >
                    {t('Create the first certificate')}
                  </a>
                </td>
              </tr>
            )}
            {rows.map((row) => {
              const who = personBits(row);
              const shown = row.holderName || who.name;
              const queue = admin(host.adminUrl, 'page=flxs-kuyruk&cert=' + row.databaseId);
              return (
                <tr key={row.id}>
                  <th className="check-column">
                    <input type="checkbox" checked={picked.includes(row.databaseId)} onChange={(e) => setPicked(e.target.checked ? [...picked, row.databaseId] : picked.filter((id) => id !== row.databaseId))} />
                  </th>
                  <td data-label={t('Certificate')}>
                    <div className="flxs-cert-cell">
                      {row.imageUrl ? (
                        <a href={row.pageUrl} target="_blank" rel="noreferrer" className="flxs-thumb">
                          <img src={row.imageUrl} alt="" loading="lazy" />
                        </a>
                      ) : null}
                      <div>
                        <strong>
                          <a href={row.pageUrl} target="_blank" rel="noreferrer">
                            {row.number}
                          </a>
                        </strong>
                        <div className="flxs-muted">
                          {row.createdAt?.short || '–'}
                          {row.backdated ? (
                            <>
                              {' '}
                              <span className="flxs-pill flxs-pill--muted">{t('backdated')}</span>
                            </>
                          ) : null}{' '}
                          · {tn('%d view', '%d views', row.views)}
                        </div>
                      </div>
                    </div>
                  </td>
                  <td data-label={t('Person')}>
                    <a href={admin(host.adminUrl, 'page=flxs-kisiler&person=' + (row.person?.databaseId ?? 0))}>{shown}</a>
                    {who.name && shown !== who.name ? <div className="flxs-muted">{t('Registered person: %s', who.name)}</div> : null}
                    <div className="flxs-muted">{[who.phone, who.email].filter(Boolean).join(' · ')}</div>
                  </td>
                  <td data-label={t('Event')}>
                    {row.eventName ? (
                      <>
                        <strong>{row.eventName}</strong>
                        <br />
                      </>
                    ) : null}
                    <span className="flxs-muted">{row.templateName}</span>
                  </td>
                  <td className="flxs-c-sends" data-label={t('Deliveries')}>
                    {!row.deliveries.length && <span className="flxs-muted flxs-c-none">–</span>}
                    {row.deliveries.map((d) => {
                      const ch = d.channel.toLowerCase();
                      const cls = d.failed && !d.sent ? 'failed' : d.waiting ? 'queued' : 'sent';
                      return (
                        <span key={d.channel}>
                          <a className={'flxs-chip flxs-chip--' + cls} href={queue}>
                            <span className={'dashicons ' + (CHANNEL_ICON[ch] ?? 'dashicons-format-chat')} title={ch === 'email' ? t('E-mail') : ch === 'sms' ? 'SMS' : 'WhatsApp'}></span>
                            {d.sent ? d.sent + '✓' : ''}
                            {d.waiting ? ' ' + d.waiting + '…' : ''}
                            {d.failed ? ' ' + d.failed + '✕' : ''}
                          </a>{' '}
                        </span>
                      );
                    })}
                  </td>
                  <td className="flxs-c-ref" data-label={t('Referrer')}>
                    {row.referrerName ? <strong>{row.referrerName}</strong> : <span className="flxs-muted flxs-c-none">–</span>}
                    <div className="flxs-muted">{t('created by: %s', row.createdByName)}</div>
                  </td>
                  <td className="flxs-c-status" data-label={t('Status')}>
                    <span className={'flxs-pill flxs-pill--' + (row.status === 'REVOKED' ? 'revoked' : 'active')}>{row.status === 'REVOKED' ? t('Cancelled') : t('Valid')}</span>
                  </td>
                  <td className="flxs-actions-cell">
                    <details className="flxs-menu">
                      <summary className="button button-small">{t('Actions')}</summary>
                      <div className="flxs-menu__list">
                        <a href={row.pageUrl} target="_blank" rel="noreferrer">
                          {t('Open page')}
                        </a>
                        <MenuAction onClick={() => void navigator.clipboard.writeText(row.pageUrl).then(() => onNotice({ kind: 'ok', text: t('Copied.') }))}>{t('Copy link')}</MenuAction>
                        {row.pdfUrl ? (
                          <a href={row.pdfUrl} target="_blank" rel="noreferrer">
                            {t('PDF')}
                          </a>
                        ) : null}
                        {row.status === 'ACTIVE' && (
                          <>
                            <MenuAction onClick={() => quick(row, 'WHATSAPP')}>{t('Send via WhatsApp')}</MenuAction>
                            <MenuAction onClick={() => quick(row, 'SMS')}>{t('Send via SMS')}</MenuAction>
                            <MenuAction onClick={() => quick(row, 'EMAIL')}>{t('Send via email')}</MenuAction>
                            <MenuAction onClick={() => setForward(row)}>{t('Send to someone else…')}</MenuAction>
                            {row.canEdit && <MenuAction onClick={() => setRefRow(row)}>{t('Update referrer…')}</MenuAction>}
                          </>
                        )}
                        {row.canEdit && <MenuAction onClick={() => onEdit(row.databaseId)}>{t('Edit')}</MenuAction>}
                        {row.canEdit && row.status === 'ACTIVE' && <MenuAction onClick={() => void setStat(row, 'REVOKED')}>{t('Revoke')}</MenuAction>}
                        {row.canEdit && row.status === 'REVOKED' && <MenuAction onClick={() => void setStat(row, 'ACTIVE')}>{t('Make valid')}</MenuAction>}
                        <a href={queue}>{t('Delivery history')}</a>
                      </div>
                    </details>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </form>
      <ListPages
        id="flxs-certs"
        total={total}
        per={per}
        page={page}
        onPage={setPage}
        onPer={(n) => {
          setPer(n);
          setPage(1);
        }}
      />
      {forward && meta && <Forward t={t} id={forward.databaseId} providers={meta.providers} onClose={() => setForward(null)} onNotice={onNotice} />}
      {refRow && meta && (
        <Modal t={t} title={t('Update referrer…')} onClose={() => setRefRow(null)}>
          <form
            onSubmit={(e) => {
              e.preventDefault();
              const id = Number(new FormData(e.currentTarget).get('ref'));
              void referrer.mutateAsync({ input: { id: refRow.databaseId, referrerId: id } }).then((res) => {
                if (res.flxCertificateSetReferrer?.errors.length) onNotice({ kind: 'err', text: res.flxCertificateSetReferrer.errors.map((er) => er.message).join(' ') });
                else {
                  setRefRow(null);
                  void refresh();
                }
              });
            }}
          >
            <select name="ref" defaultValue={refRow.referrerId}>
              <option value={0}>{t('— No referrer —')}</option>
              {meta.referrers.map((u) => (
                <option key={u.id} value={u.id}>
                  {u.name}
                </option>
              ))}
            </select>
            <button className="button button-primary" type="submit">
              {t('Save')}
            </button>
          </form>
        </Modal>
      )}
    </>
  );
}

function People({ t, onNotice, onOpen, onAdd, onCerts }: { t: Translate; onNotice: (n: Notice) => void; onOpen: (id: number) => void; onAdd: () => void; onCerts: (id: number) => void }) {
  const host = useHost();
  const canBulk = useFlxCan('flxs_bulk');
  const [search, setSearch] = useState('');
  const [applied, setApplied] = useState('');
  const [per, setPer] = useState(() => listPerPage('flxs-people', 30));
  const [page, setPage] = useState(1);
  const [picked, setPicked] = useState<number[]>([]);
  const [channel, setChannel] = useState('whatsapp');
  const q = useFlxQuery(FlxCertificateDirectory, { search: applied, first: per, after: afterOf((page - 1) * per) }, { placeholderData: (old) => old });
  const rows: PersonRow[] = q.data?.flxCertificateDirectory.nodes ?? [];
  useHead(
    t('People'),
    t('Everyone who received a certificate is listed here. A new certificate with the same phone or e-mail is linked to that person; people entered without contact details get a record of their own.'),
  );
  const link = (e: React.MouseEvent, fn: () => void) => {
    e.preventDefault();
    fn();
  };
  return (
    <>
      <HeadActions>
        <a href="#" className="button button-primary" onClick={(e) => link(e, onAdd)}>
          + {t('Add person')}
        </a>
      </HeadActions>
      <ListBar
        id="flxs-people"
        className="flxs-filters"
        onSubmit={() => {
          setApplied(search);
          setPage(1);
        }}
        search={<input type="search" value={search} placeholder={t('Name, phone or e-mail…')} onChange={(e) => setSearch(e.target.value)} />}
      />
      <form className={picked.length ? 'flxs-has-sel' : undefined} onSubmit={(e) => e.preventDefault()}>
        {canBulk && (
          <div className="flxs-bulkbar">
            <select value={channel} onChange={(e) => setChannel(bulkChannel(e.target.value))}>
              <option value="whatsapp">WhatsApp</option>
              <option value="sms">SMS</option>
              <option value="email">{t('E-mail')}</option>
            </select>
            <button
              type="button"
              className="button"
              onClick={() => {
                if (!picked.length) {
                  onNotice({ kind: 'err', text: t('Nothing selected.') });
                  return;
                }
                const people = picked.filter((id) => Number.isInteger(id) && id > 0).join(',');
                window.location.href = admin(host.adminUrl, 'page=flxs-toplu&people=' + people + '&channel=' + bulkChannel(channel));
              }}
            >
              {t('Message selected')}
            </button>
          </div>
        )}
        <table className="widefat flxs-table">
          <thead>
            <tr>
              <td className="check-column">
                <input type="checkbox" checked={rows.length > 0 && picked.length === rows.length} onChange={(e) => setPicked(e.target.checked ? rows.map((r) => r.databaseId) : [])} />
              </td>
              <th>{t('Full name')}</th>
              <th>{t('Phone')}</th>
              <th>{t('E-mail')}</th>
              <th>{t('Certificates count')}</th>
              <th>{t('Added')}</th>
            </tr>
          </thead>
          <tbody>
            {!rows.length && !q.isLoading && (
              <tr>
                <td colSpan={6} className="flxs-empty">
                  {t('No records.')}
                </td>
              </tr>
            )}
            {rows.map((p) => (
              <tr key={p.id}>
                <th className="check-column">
                  <input type="checkbox" checked={picked.includes(p.databaseId)} onChange={(e) => setPicked(e.target.checked ? [...picked, p.databaseId] : picked.filter((id) => id !== p.databaseId))} />
                </th>
                <td data-label={t('Full name')}>
                  <strong>
                    <a href={admin(host.adminUrl, 'page=flxs-kisiler&person=' + p.databaseId)} onClick={(e) => link(e, () => onOpen(p.databaseId))}>
                      {p.name}
                    </a>
                  </strong>
                </td>
                <td data-label={t('Phone')}>{p.phone?.display}</td>
                <td data-label={t('E-mail')}>{p.email}</td>
                <td data-label={t('Certificates count')}>
                  <a href={admin(host.adminUrl, 'page=flxs&person=' + p.databaseId)} onClick={(e) => link(e, () => onCerts(p.databaseId))}>
                    {p.certificateCount}
                  </a>
                </td>
                <td data-label={t('Added')}>
                  {(p.createdAt?.short || '').slice(0, 10)} · {p.createdByName}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </form>
      <ListPages
        id="flxs-people"
        total={q.data?.flxCertificateDirectory.totalCount ?? 0}
        per={per}
        page={page}
        onPage={setPage}
        onPer={(n) => {
          setPer(n);
          setPage(1);
        }}
      />
    </>
  );
}

/** Classic flxs_person_form(): name, phone, e-mail on one row, the note below. */
function PersonForm({
  t,
  id,
  initial,
  onNotice,
  onSaved,
}: {
  t: Translate;
  id?: number;
  initial?: { name: string; phone: string; email: string; note: string };
  onNotice: (n: Notice) => void;
  onSaved: (id: number) => void;
}) {
  const save = useFlxMutation(FlxCertificatePersonSave);
  return (
    <form
      className="flxs-card flxs-form"
      onSubmit={(e) => {
        e.preventDefault();
        const fd = new FormData(e.currentTarget);
        void save.mutateAsync({ input: { id, name: String(fd.get('name') || ''), phone: String(fd.get('phone') || ''), email: String(fd.get('email') || ''), note: String(fd.get('note') || '') } }).then((res) => {
          const payload = res.flxCertificatePersonSave;
          if (payload?.errors.length || !payload?.person) onNotice({ kind: 'err', text: payload?.errors.map((er) => er.message).join(' ') || t('Required.') });
          else onSaved(payload.person.databaseId);
        });
      }}
    >
      <div className="flxs-grid">
        <label className="flxs-field">
          <span>{t('Full name')} *</span>
          <input type="text" name="name" required defaultValue={initial?.name} />
        </label>
        <label className="flxs-field">
          <span>{t('Phone')}</span>
          <input type="tel" name="phone" defaultValue={initial?.phone} placeholder="0532 123 45 67" />
        </label>
        <label className="flxs-field">
          <span>{t('E-mail')}</span>
          <input type="email" name="email" defaultValue={initial?.email} />
        </label>
      </div>
      <label className="flxs-field">
        <span>{t('Note')}</span>
        <textarea name="note" rows={2} defaultValue={initial?.note} />
      </label>
      <p>
        <button className="button button-primary" type="submit">
          {t('Save')}
        </button>
      </p>
    </form>
  );
}

function AddPerson({ t, onNotice, onSaved }: { t: Translate; onNotice: (n: Notice) => void; onSaved: (id: number) => void }) {
  useHead(t('Add person'));
  return <PersonForm t={t} onNotice={onNotice} onSaved={onSaved} />;
}

const STATUS_PILL: Record<string, string> = { ACTIVE: 'active', REVOKED: 'revoked' };
const MSG_STATUS: Record<string, string> = { queued: 'Queued', processing: 'Sending now', sent: 'Sent', failed: 'Failed', cancelled: 'Cancelled' };

function PersonDetail({ t, id, onNotice, onBack, onCert }: { t: Translate; id: number; onNotice: (n: Notice) => void; onBack: () => void; onCert: () => void }) {
  const q = useFlxQuery(FlxCertificatePerson, { id });
  const msgs = useFlxQuery(FlxCertificatePersonMessages, { personId: id });
  const del = useFlxMutation(FlxCertificatePersonDelete);
  const canManage = useFlxCan('flxs_manage');
  const person = q.data?.flxCertificatePerson;
  const certs = q.data?.flxCertificates;
  useHead(person?.name, person ? t('Registered: %s', [person.createdAt?.short, person.createdByName].filter(Boolean).join(' · ')) : undefined);
  const link = (e: React.MouseEvent, fn: () => void) => {
    e.preventDefault();
    fn();
  };
  if (q.isLoading) return null;
  if (!person) return <p className="flxs-notice is-error">{t('Person not found.')}</p>;
  const list = msgs.data?.flxCertificatePersonMessages ?? [];
  return (
    <>
      <HeadActions>
        <a href="#" className="button button-primary" onClick={(e) => link(e, onCert)}>
          + {t('Certificate for this person')}
        </a>{' '}
        <a href="#" className="button" onClick={(e) => link(e, onBack)}>
          ← {t('People')}
        </a>
      </HeadActions>
      <div className="flxs-two">
        <div>
          <PersonForm
            t={t}
            id={person.databaseId}
            initial={{ name: person.name, phone: person.phone?.display ?? '', email: person.email ?? '', note: person.note ?? '' }}
            onNotice={onNotice}
            onSaved={() => onNotice({ kind: 'ok', text: t('Updated.') })}
          />
          {canManage && (
            <button
              type="button"
              className="button-link flxs-danger"
              onClick={async () => {
                const msg = t('Delete this person? A person with certificates cannot be deleted.');
                if (!(await ask(msg, true))) return;
                const res = await del.mutateAsync({ input: { id: person.databaseId } });
                if (res.flxCertificatePersonDelete?.errors.length) onNotice({ kind: 'err', text: res.flxCertificatePersonDelete.errors.map((e) => e.message).join(' ') });
                else onBack();
              }}
            >
              {t('Delete person')}
            </button>
          )}
        </div>
        <div>
          <div className="flxs-card">
            <h2 className="flxs-card__title">
              {t('Certificates')} ({certs?.totalCount ?? 0})
            </h2>
            {!certs?.nodes.length && <p className="flxs-muted">{t('No certificates.')}</p>}
            {certs?.nodes.map((c) => (
              <div className="flxs-li" key={c.id}>
                <a href={c.pageUrl} target="_blank" rel="noreferrer">
                  <strong>{c.number}</strong>
                </a>{' '}
                · {c.templateName} · {(c.createdAt?.short || '').slice(0, 10)} <span className={'flxs-pill flxs-pill--' + (STATUS_PILL[c.status] ?? 'active')}>{c.status === 'REVOKED' ? t('Cancelled') : t('Valid')}</span>
              </div>
            ))}
          </div>
          <div className="flxs-card">
            <h2 className="flxs-card__title">
              {t('Messages sent')} ({list.length})
            </h2>
            {!list.length && <p className="flxs-muted">{t('No messages.')}</p>}
            {list.map((m, i) => {
              const ch = m.channel.toLowerCase();
              return (
                <div className="flxs-li" key={i}>
                  <span className={'dashicons ' + (CHANNEL_ICON[ch] ?? 'dashicons-format-chat')}></span> {m.recipient} · <span className={'flxs-pill flxs-pill--' + m.status}>{t(MSG_STATUS[m.status] ?? m.status)}</span> ·{' '}
                  {m.at?.short} <span className="flxs-muted">{m.createdByName}</span>
                </div>
              );
            })}
          </div>
        </div>
      </div>
    </>
  );
}
