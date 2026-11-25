// Log: the classic log screen (flxi-kayitlar): shared list bar (search, status, channel), the log table,
// page numbers with "Per page", and the row dialog with the details and the stored body.
import { useEffect, useRef, useState } from 'react';
import { keepPreviousData } from '@tanstack/react-query';
import { useFlxQuery } from '@fluxwp/graphql/react';
import { FlxMailLog, FlxMailLogs } from '@fluxwp/graphql/operations';
import { ListBar, ListPages, Loading, listPerPage } from '@fluxwp/ui';
import { errorText, useMail } from './common';

const LIST = 'flxi-log';
/** Offset cursor of flx_gql_paginate. */
const cursorAt = (offset: number): string | undefined => (offset > 0 ? btoa('flxo:' + (offset - 1)) : undefined);

export function LogsPage({ initialStatus = '', initialTemplate = '', initialCampaign = '' }: { initialStatus?: string; initialTemplate?: string; initialCampaign?: string }) {
  const { t } = useMail();
  const [search, setSearch] = useState('');
  const [q, setQ] = useState('');
  const [status, setStatus] = useState(initialStatus);
  const [channel, setChannel] = useState('');
  const [template, setTemplate] = useState(initialTemplate);
  const [campaign, setCampaign] = useState(initialCampaign);
  const [per, setPer] = useState(() => listPerPage(LIST, 20));
  const [page, setPage] = useState(1);
  const [open, setOpen] = useState<number | null>(null);
  useEffect(() => setStatus(initialStatus), [initialStatus]);
  useEffect(() => setTemplate(initialTemplate), [initialTemplate]);
  useEffect(() => setCampaign(initialCampaign), [initialCampaign]);
  useEffect(() => setPage(1), [q, status, channel, template, campaign, per]);

  const list = useFlxQuery(
    FlxMailLogs,
    {
      first: per,
      after: cursorAt((page - 1) * per),
      search: q || undefined,
      status: status || undefined,
      channel: channel || undefined,
      template: template || undefined,
      campaign: campaign ? Number(campaign) : undefined,
    },
    { placeholderData: keepPreviousData },
  );
  const rows = list.data?.flxMailLogs.nodes ?? [];
  const total = list.data?.flxMailLogs.totalCount ?? 0;
  const filtered = Boolean(q || status || channel || template || campaign);

  return (
    <div>
      <ListBar
        id={LIST}
        className="flxi-filters"
        onSubmit={() => setQ(search.trim())}
        onClear={
          filtered
            ? () => {
                setSearch('');
                setQ('');
                setStatus('');
                setChannel('');
                setTemplate('');
                setCampaign('');
              }
            : undefined
        }
        search={<input type="search" value={search} placeholder={t('Recipient or subject')} aria-label={t('Recipient or subject')} onChange={(e) => setSearch(e.target.value)} />}
        primary={
          <>
            <select aria-label={t('Status')} value={status} onChange={(e) => setStatus(e.target.value)}>
              <option value="">{t('All statuses')}</option>
              <option value="sent">{t('Sent')}</option>
              <option value="failed">{t('Failed')}</option>
              <option value="blocked">{t('Disabled, not sent')}</option>
              <option value="sending">{t('Sending')}</option>
            </select>
            <select aria-label={t('Channel')} value={channel} onChange={(e) => setChannel(e.target.value)}>
              <option value="">{t('All channels')}</option>
              <option value="email">{t('Email')}</option>
              <option value="whatsapp">WhatsApp</option>
              <option value="sms">SMS</option>
            </select>
          </>
        }
      />
      {list.isError ? <p className="flxi-err">{errorText(list.error, t('Something went wrong.'))}</p> : null}
      {list.isLoading && !list.data ? (
        <Loading />
      ) : (
        <div className="flxi-card flxi-card--flush">
          <div className="flxi-tablewrap">
            <table className="flxi-table flxi-table--log">
              <thead>
                <tr>
                  <th>{t('Time')}</th>
                  <th>{t('Recipient')}</th>
                  <th>{t('Subject / email')}</th>
                  <th>{t('Status')}</th>
                </tr>
              </thead>
              <tbody>
                {rows.length ? null : (
                  <tr>
                    <td colSpan={4} className="flxi-empty">
                      {t('No entries.')}
                    </td>
                  </tr>
                )}
                {rows.map((row) => (
                  <tr
                    key={row.databaseId}
                    className="flxi-logrow"
                    tabIndex={0}
                    onClick={() => setOpen(row.databaseId)}
                    onKeyDown={(e) => {
                      if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        setOpen(row.databaseId);
                      }
                    }}
                  >
                    <td className="flxi-nowrap">
                      {row.createdAt?.short ?? ''}
                      <br />
                      <span className="flxi-muted">{row.channelLabel}</span>
                    </td>
                    <td className="flxi-break">{row.recipient}</td>
                    <td>
                      <strong>{row.subject || '—'}</strong>
                      <br />
                      <span className="flxi-muted">{row.templateLabel}</span>
                    </td>
                    <td>
                      <span className={'flxi-badge flxi-badge--' + (row.status === 'sent' ? 'green' : row.status === 'failed' ? 'red' : 'muted')}>{statusLabel(row.status, t)}</span>
                      {row.error ? <div className="flxi-err">{row.error.slice(0, 160)}</div> : null}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}
      <ListPages id={LIST} total={total} per={per} page={page} onPage={setPage} onPer={setPer} />
      {open !== null ? <LogDialog id={open} onClose={() => setOpen(null)} /> : null}
    </div>
  );
}

function LogDialog({ id, onClose }: { id: number; onClose: () => void }) {
  const { t } = useMail();
  const ref = useRef<HTMLDialogElement>(null);
  const q = useFlxQuery(FlxMailLog, { id });
  useEffect(() => {
    const d = ref.current;
    if (d && !d.open) {
      if (d.showModal) d.showModal();
      else d.setAttribute('open', '');
    }
  }, []);
  const r = q.data?.flxMailLog;
  const meta: [string, string][] = r
    ? (
        [
          [t('To'), r.recipient],
          [t('From'), r.sender],
          [t('Status'), statusLabel(r.status, t) + (r.error ? ' · ' + r.error : '')],
          [t('Email'), r.templateLabel],
          [t('Time'), r.createdAt?.short ?? ''],
        ] as [string, string][]
      ).filter((p) => p[1])
    : [];
  return (
    <dialog
      ref={ref}
      className="flxi-dialog"
      aria-labelledby="flxi-log-title"
      onClose={onClose}
      onClick={(e) => {
        if (e.target === e.currentTarget) ref.current?.close();
      }}
    >
      <div className="flxi-dialog__head">
        <h2 id="flxi-log-title">{q.isError ? errorText(q.error, t('Something went wrong.')) : r ? r.subject || r.recipient : '…'}</h2>
        <button type="button" className="button-link" aria-label={t('Close')} onClick={() => ref.current?.close()}>
          <span className="dashicons dashicons-no-alt" aria-hidden="true" />
          {typeof document !== 'undefined' && document.querySelector('link[href*="dashicons"]') ? null : '×'}
        </button>
      </div>
      <dl className="flxi-dl">
        {meta.map(([k, v]) => (
          <Pair key={k} k={k} v={v} />
        ))}
      </dl>
      {r ? <iframe title={t('Email preview')} sandbox="allow-same-origin" srcDoc={r.body || '<p style="font-family:sans-serif;color:#888;margin:16px">—</p>'} /> : <Loading />}
    </dialog>
  );
}

function Pair({ k, v }: { k: string; v: string }) {
  return (
    <>
      <dt>{k}</dt>
      <dd>{v}</dd>
    </>
  );
}

function statusLabel(status: string, t: (s: string) => string): string {
  if (status === 'sent') return t('Sent');
  if (status === 'failed') return t('Failed');
  if (status === 'blocked') return t('Disabled, not sent');
  if (status === 'sending') return t('Sending');
  return status;
}
