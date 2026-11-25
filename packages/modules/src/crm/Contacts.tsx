// CRM › Contacts: role chips, filters, sorting, bulk actions, CSV, pages.
import { useEffect, useMemo, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { FlxCrmContactBulk, FlxCrmContacts, FlxCrmOverview } from '@fluxwp/graphql/operations';
import type { FlxConsentChannel, FlxContactBulkAction, FlxContactFilterInput, FlxContactOrder, FlxContactOrderBy, FlxCrmContactsQuery } from '@fluxwp/graphql/types';
import { ListBar, ListPages, Loading, Modal, listPerPage } from '@fluxwp/ui';
import { Avatar, ChurnMeter, Empty, Head, Ic, RolePills, SegmentBadge, countsLabel, errText, href, useCrm, type Route } from './ui';
import { csvDownload } from './csv';

type Row = FlxCrmContactsQuery['flxContacts']['nodes'][number];
const PER = 25;
const ORDERS: ReadonlyArray<FlxContactOrderBy> = ['NAME', 'LTV', 'CHURN', 'LAST_ACTIVITY', 'LAST_PAID', 'CREATED'];
const CONSENTS: ReadonlyArray<FlxConsentChannel> = ['EMAIL', 'SMS', 'WHATSAPP', 'ADS'];

export function Contacts({ route, go }: { route: Route; go: (to: string) => void }) {
  const crm = useCrm();
  const { t, num, can, shell } = crm;
  const { client, scope } = useGraphQL();
  const qc = useQueryClient();
  const p = route.q;
  const get = (k: string) => p.get(k) ?? '';
  const archived = get('status') === 'archived';
  const orderBy = (ORDERS.includes(get('orderby') as FlxContactOrderBy) ? get('orderby') : 'LAST_ACTIVITY') as FlxContactOrderBy;
  const order: FlxContactOrder = get('order') === 'ASC' ? 'ASC' : 'DESC';
  const filter = useMemo<FlxContactFilterInput>(() => {
    const f: FlxContactFilterInput = { status: archived ? 'ARCHIVED' : 'ACTIVE' };
    if (get('q')) f.search = get('q');
    if (get('role')) f.role = get('role');
    if (get('segment')) f.segment = get('segment');
    if (get('sid')) f.segmentId = Number(get('sid'));
    if (get('tag')) f.tagId = Number(get('tag'));
    if (CONSENTS.includes(get('consent') as FlxConsentChannel)) f.consent = get('consent') as FlxConsentChannel;
    if (get('risk')) f.atRisk = true;
    return f;
  }, [p.toString()]); // eslint-disable-line react-hooks/exhaustive-deps
  const key = p.toString();

  const [per, setPer] = useState(() => listPerPage('flxcrm-contacts', PER));
  const page = Math.max(1, Number(get('paged')) || 1);
  const [selected, setSelected] = useState<Set<string>>(new Set());
  const [bulk, setBulk] = useState<{ action: FlxContactBulkAction; value: string }>({ action: 'TAG', value: '' });
  const [confirmDelete, setConfirmDelete] = useState(false);
  const [exporting, setExporting] = useState(false);
  useEffect(() => {
    setSelected(new Set());
  }, [key]);
  const offset = (page - 1) * per;
  const after = offset > 0 ? btoa('flxo:' + (offset - 1)) : null;

  const q = useFlxQuery(FlxCrmContacts, { filter, orderBy, order, first: per, after }, { placeholderData: (old) => old });
  const ov = useFlxQuery(FlxCrmOverview, {}, { staleTime: 30_000 });
  const bulkMut = useFlxMutation(FlxCrmContactBulk);

  const setParam = (changes: Record<string, string>) => {
    const n = new URLSearchParams(p);
    if (!('paged' in changes)) n.delete('paged');
    for (const [k, v] of Object.entries(changes)) (v === '' ? n.delete(k) : n.set(k, v));
    go('contacts' + (n.toString() ? '?' + n.toString() : ''));
  };
  const sortBy = (col: FlxContactOrderBy) => setParam({ orderby: col, order: orderBy === col && order === 'DESC' ? 'ASC' : 'DESC' });
  const sortLabel = (col: FlxContactOrderBy, label: string) => (
    <button type="button" className="button-link" style={{ textDecoration: 'none', color: 'inherit' }} onClick={() => sortBy(col)}>
      {label + (orderBy === col ? (order === 'DESC' ? ' ↓' : ' ↑') : '')}
    </button>
  );

  const [search, setSearch] = useState(get('q'));
  useEffect(() => setSearch(get('q')), [key]); // eslint-disable-line react-hooks/exhaustive-deps

  const rows: ReadonlyArray<Row> = q.data?.flxContacts.nodes ?? [];
  const total = q.data?.flxContacts.totalCount ?? 0;
  const allOn = rows.length > 0 && rows.every((r) => selected.has(r.id));
  const toggle = (id: string, on: boolean) =>
    setSelected((s) => {
      const n = new Set(s);
      if (on) n.add(id);
      else n.delete(id);
      return n;
    });

  const runBulk = async () => {
    try {
      const r = (await bulkMut.mutateAsync({ input: { action: bulk.action, ids: [...selected], value: bulk.value || null } })).flxContactBulk;
      if (!r || r.errors.length) throw new Error(errText(r?.errors));
      crm.tell('ok', t('Done: %s contacts changed.', num(r.changed ?? 0)));
      setSelected(new Set());
      setConfirmDelete(false);
      void qc.invalidateQueries({ queryKey: [scope, 'gql'] });
      crm.reloadShell();
    } catch (e) {
      setConfirmDelete(false);
      crm.fail(e);
    }
  };

  const exportCsv = async () => {
    setExporting(true);
    try {
      const all: Row[] = [];
      let cur: string | null = null;
      for (let i = 0; i < 200; i++) {
        const r: FlxCrmContactsQuery = await client.request(FlxCrmContacts, { filter, orderBy, order, first: 50, after: cur });
        all.push(...r.flxContacts.nodes);
        if (!r.flxContacts.pageInfo.hasNextPage) break;
        cur = r.flxContacts.pageInfo.endCursor ?? null;
      }
      csvDownload(
        'contacts.csv',
        [t('Name'), t('E-mail'), t('Phone'), t('Organisation'), t('City'), t('Roles'), t('Segment'), t('Total value'), t('Donations'), t('Orders'), t('Churn risk'), t('Last activity')],
        all.map((c) => [
          c.name,
          c.email ?? '',
          c.phone?.e164 ?? '',
          c.company ?? '',
          c.city ?? '',
          (c.roles ?? []).join(', '),
          c.segmentLabel ?? '',
          String(c.ltv?.amount ?? 0),
          String(c.donations),
          String(c.orders),
          String(c.churn),
          c.lastActivityAt?.iso ?? '',
        ]),
      );
    } catch (e) {
      crm.fail(e);
    } finally {
      setExporting(false);
    }
  };

  const filtered = [...p.keys()].some((k) => k !== 'status' && k !== 'orderby' && k !== 'order' && k !== 'paged');
  const clearable = [...p.keys()].some((k) => !['status', 'orderby', 'order', 'paged', 'role'].includes(k)) || archived;
  const sid = Number(get('sid'));
  const sg = sid ? shell.flxSegments.find((s) => s.databaseId === sid) : undefined;
  const roleCounts = new Map((ov.data?.flxCrmOverview.roles ?? []).map((r) => [r.key, r.count]));

  return (
    <>
      <Head
        title={sg ? sg.name : t('Contacts')}
        lead={sg ? sg.summary : t('Donors, customers, leads: one card per person. They arrive from your sources on their own, or you add them by hand.')}
        back={sg ? { to: 'segments', label: t('Segments') } : undefined}
        actions={
          can && (
            <>
              <button type="button" className="button" disabled={exporting} onClick={() => void exportCsv()}>
                <Ic n="download" /> {t('CSV')}
              </button>
              <a className="button" href={href('duplicates')}>
                <Ic n="users" /> {t('Possible duplicates')}
              </a>
              <a className="button button-primary" href={href('contacts/new')}>
                <Ic n="plus" /> {t('Add contact')}
              </a>
            </>
          )
        }
      />

      <div className="flxcrm-chips" role="navigation" aria-label={t('Role')}>
        <button type="button" className={'flxcrm-chip' + (get('role') === '' ? ' is-on' : '')} onClick={() => setParam({ role: '' })}>
          {t('All')} <span>{ov.data ? num(ov.data.flxCrmOverview.total) : ''}</span>
        </button>
        {shell.flxCrmMeta.roles
          .filter((r) => !r.hidden && ((roleCounts.get(r.key) ?? 0) > 0 || r.key === get('role')))
          .map((r) => (
            <button key={r.key} type="button" className={'flxcrm-chip' + (get('role') === r.key ? ' is-on' : '')} style={{ ['--rc' as string]: r.color }} onClick={() => setParam({ role: r.key })}>
              <i />
              {r.label} <span>{num(roleCounts.get(r.key) ?? 0)}</span>
            </button>
          ))}
      </div>

      <ListBar
        id="flxcrm-contacts"
        onSubmit={() => setParam({ q: search.trim() })}
        onClear={clearable ? () => go('contacts' + (get('role') ? '?role=' + get('role') : '')) : undefined}
        moreActive={[get('tag'), get('consent'), get('risk'), archived ? '1' : ''].filter(Boolean).length}
        search={<input type="search" value={search} onChange={(e) => setSearch(e.currentTarget.value)} placeholder={t('Name, e-mail, phone, organisation, city')} aria-label={t('Search contacts')} />}
        primary={
          <>
            <select value={get('segment')} onChange={(e) => setParam({ segment: e.currentTarget.value })} aria-label={t('RFM segment')}>
              <option value="">{t('All RFM segments')}</option>
              {shell.flxCrmMeta.rfm.map((s) => (
                <option key={s.key} value={s.key}>
                  {s.label}
                </option>
              ))}
              <option value="none">{t('No payments')}</option>
            </select>
            <select value={get('sid')} onChange={(e) => setParam({ sid: e.currentTarget.value })} aria-label={t('Saved segment')}>
              <option value="">{t('Saved segment')}</option>
              {shell.flxSegments.map((s) => (
                <option key={s.databaseId} value={s.databaseId}>
                  {s.name}
                </option>
              ))}
            </select>
          </>
        }
        more={
          <>
            <select value={get('tag')} onChange={(e) => setParam({ tag: e.currentTarget.value })} aria-label={t('Tag')}>
              <option value="">{t('All tags')}</option>
              {shell.flxTags.map((x) => (
                <option key={x.databaseId} value={x.databaseId}>
                  {x.name + ' (' + num(x.count) + ')'}
                </option>
              ))}
            </select>
            <select value={get('consent')} onChange={(e) => setParam({ consent: e.currentTarget.value })} aria-label={t('Consent')}>
              <option value="">{t('Any consent')}</option>
              {shell.flxCrmMeta.consents.map((c) => (
                <option key={c.key} value={c.key.toUpperCase()}>
                  {t('has %s consent', c.label)}
                </option>
              ))}
            </select>
            <label className="flxcrm-check">
              <input type="checkbox" checked={get('risk') !== ''} onChange={(e) => setParam({ risk: e.currentTarget.checked ? '1' : '' })} /> {t('Only high churn risk')}
            </label>
            <label className="flxcrm-check">
              <input type="checkbox" checked={archived} onChange={(e) => setParam({ status: e.currentTarget.checked ? 'archived' : '' })} /> {t('Archive')}
            </label>
          </>
        }
      />

      {q.isLoading ? (
        <Loading />
      ) : q.isError ? (
        <div className="flxcrm-notice is-bad">{q.error.message}</div>
      ) : rows.length === 0 ? (
        <Empty title={filtered ? t('No contacts match the filter.') : t('No contacts yet.')} hint={!filtered && can ? t('Contacts appear on their own as donations, orders, forms and WhatsApp chats come in. For older records: Settings › Sync everything.') : undefined}>
          {!filtered && can && (
            <p>
              <a className="button" href={href('settings')}>
                {t('Settings')}
              </a>
            </p>
          )}
        </Empty>
      ) : (
        <>
          {can && selected.size > 0 && (
            <div className="flxcrm-bulk">
              <span className="flxcrm-bulk__count" style={{ fontWeight: 600, color: 'var(--c-ac)' }}>
                {t('%s selected', num(selected.size))}
              </span>
              <select value={bulk.action} onChange={(e) => setBulk({ action: e.currentTarget.value as FlxContactBulkAction, value: '' })} aria-label={t('Bulk action')}>
                <option value="TAG">{t('Add tag')}</option>
                <option value="ROLE">{t('Add role')}</option>
                {archived ? <option value="RESTORE">{t('Unarchive')}</option> : <option value="ARCHIVE">{t('Archive')}</option>}
                <option value="DELETE">{t('Delete')}</option>
              </select>
              {bulk.action === 'TAG' && (
                <>
                  <input type="text" list="flxcrm-tags" value={bulk.value} onChange={(e) => setBulk({ ...bulk, value: e.currentTarget.value })} placeholder={t('Tag name')} aria-label={t('Tag name')} />
                  <datalist id="flxcrm-tags">
                    {shell.flxTags.map((x) => (
                      <option key={x.databaseId} value={x.name} />
                    ))}
                  </datalist>
                </>
              )}
              {bulk.action === 'ROLE' && (
                <select value={bulk.value} onChange={(e) => setBulk({ ...bulk, value: e.currentTarget.value })} aria-label={t('Role')}>
                  <option value="">{t('— none —')}</option>
                  {shell.flxCrmMeta.roles.map((r) => (
                    <option key={r.key} value={r.key}>
                      {r.label}
                    </option>
                  ))}
                </select>
              )}
              <button
                type="button"
                className="button"
                disabled={bulkMut.isPending || ((bulk.action === 'TAG' || bulk.action === 'ROLE') && !bulk.value.trim())}
                onClick={() => (bulk.action === 'DELETE' ? setConfirmDelete(true) : void runBulk())}
              >
                {t('Apply')}
              </button>
            </div>
          )}
          <div className="flxcrm-card flxcrm-card--flush">
            <table className="flxcrm-table flxcrm-table--contacts">
              <thead>
                <tr>
                  {can && (
                    <th className="flxcrm-col-check">
                      <input type="checkbox" checked={allOn} aria-label={t('Select all')} onChange={(e) => setSelected(e.currentTarget.checked ? new Set(rows.map((r) => r.id)) : new Set())} />
                    </th>
                  )}
                  <th>{sortLabel('NAME', t('Contact'))}</th>
                  <th>{t('Role and segment')}</th>
                  <th className="num">{sortLabel('LTV', t('Value'))}</th>
                  <th>{sortLabel('CHURN', t('Churn risk'))}</th>
                  <th>{sortLabel('LAST_ACTIVITY', t('Last activity'))}</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((c) => (
                  <tr key={c.id}>
                    {can && (
                      <td className="flxcrm-col-check">
                        <input type="checkbox" checked={selected.has(c.id)} aria-label={c.name} onChange={(e) => toggle(c.id, e.currentTarget.checked)} />
                      </td>
                    )}
                    <td data-label={t('Contact')}>
                      <a className="flxcrm-who" href={href('contact/' + c.databaseId)}>
                        <Avatar name={c.name} />
                        <span>
                          <strong>{c.name}</strong>
                          <small>{[c.email, c.phone?.display, c.city].filter(Boolean).join(' · ')}</small>
                          {(c.tags ?? []).length > 0 && (
                            <span className="flxcrm-tags">
                              {(c.tags ?? []).slice(0, 3).map((x) => (
                                <span key={x.databaseId} className="flxcrm-tag" style={{ ['--tc' as string]: x.color ?? '#64748b' }}>
                                  {x.name}
                                </span>
                              ))}
                            </span>
                          )}
                        </span>
                      </a>
                    </td>
                    <td data-label={t('Role and segment')}>
                      <span className="flxcrm-pills">
                        <RolePills keys={c.roles ?? []} />
                        {(c.ltv?.amount ?? 0) > 0 && <SegmentBadge segment={c.segment} label={c.segmentLabel} />}
                      </span>
                    </td>
                    <td className="num" data-label={t('Value')}>
                      {c.ltv && c.ltv.amount > 0 ? (
                        <>
                          <strong>{c.ltv.formatted}</strong>
                          <small>{countsLabel(crm, c.donations ?? 0, c.orders ?? 0)}</small>
                        </>
                      ) : (
                        <span className="flxcrm-muted">—</span>
                      )}
                    </td>
                    <td data-label={t('Churn risk')}>
                      <ChurnMeter churn={c.churn ?? 0} paid={(c.ltv?.amount ?? 0) > 0} risk={!!c.atRisk} />
                    </td>
                    <td data-label={t('Last activity')}>
                      <span title={c.lastActivityAt?.label}>{c.lastActivityAt?.relative ?? '—'}</span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <ListPages
            id="flxcrm-contacts"
            total={total}
            per={per}
            page={page}
            locale={crm.lang}
            onPage={(n) => setParam({ paged: n > 1 ? String(n) : '' })}
            onPer={(v) => {
              setPer(v);
              setParam({ paged: '' });
            }}
          />
        </>
      )}
      {confirmDelete && (
        <Modal title={t('Delete contact')} onClose={() => setConfirmDelete(false)}>
          <p>{t('%s contacts will be deleted. Donations and orders stay in their own plugins; only the CRM cards are deleted.', num(selected.size))}</p>
          <div className="flxcrm-modal-actions">
            <button type="button" className="button" onClick={() => setConfirmDelete(false)}>
              {t('Cancel')}
            </button>
            <button type="button" className="button button-danger" disabled={bulkMut.isPending} onClick={() => void runBulk()}>
              {t('Delete')}
            </button>
          </div>
        </Modal>
      )}
    </>
  );
}

