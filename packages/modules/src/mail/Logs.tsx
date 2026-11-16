import { useEffect, useState } from 'react';
import type { FlxMailLogsQuery } from '@fluxwp/graphql/types';
import { useFlxQuery } from '@fluxwp/graphql/react';
import { FlxMailLog, FlxMailLogs } from '@fluxwp/graphql/operations';
import { Loading } from '@fluxwp/ui';
import { errorText, useMail } from './common';

type Row = FlxMailLogsQuery['flxMailLogs']['nodes'][number];

export function LogsPage({ initialStatus = '' }: { initialStatus?: string }) {
  const { t } = useMail();
  const [search, setSearch] = useState('');
  const [q, setQ] = useState('');
  const [status, setStatus] = useState(initialStatus);
  const [channel, setChannel] = useState('');
  const [template, setTemplate] = useState('');
  const [campaign, setCampaign] = useState('');
  const [cursor, setCursor] = useState<string | undefined>(undefined);
  const [rows, setRows] = useState<Row[]>([]);
  const [open, setOpen] = useState<number | null>(null);
  useEffect(() => {
    const timer = window.setTimeout(() => setQ(search.trim()), 300);
    return () => window.clearTimeout(timer);
  }, [search]);
  useEffect(() => {
    setStatus(initialStatus);
  }, [initialStatus]);
  useEffect(() => {
    setCursor(undefined);
    setRows([]);
  }, [q, status, channel, template, campaign]);

  const list = useFlxQuery(FlxMailLogs, {
    first: 50,
    after: cursor,
    search: q || undefined,
    status: status || undefined,
    channel: channel || undefined,
    template: template || undefined,
    campaign: campaign ? Number(campaign) : undefined,
  });
  const detail = useFlxQuery(FlxMailLog, { id: open ?? 0 }, { enabled: open !== null });

  useEffect(() => {
    const nodes = list.data?.flxMailLogs.nodes;
    if (!nodes) return;
    setRows((prev) => {
      const next = cursor ? [...prev, ...nodes] : nodes;
      const seen = new Set<number>();
      return next.filter((r) => {
        if (seen.has(r.databaseId)) return false;
        seen.add(r.databaseId);
        return true;
      });
    });
  }, [list.dataUpdatedAt]); // eslint-disable-line react-hooks/exhaustive-deps

  return (
    <div>
      <form
        className="flxmail-filters"
        onSubmit={(e) => {
          e.preventDefault();
          setQ(search.trim());
        }}
      >
        <input type="search" value={search} placeholder={t('Recipient or subject')} aria-label={t('Recipient or subject')} onChange={(e) => setSearch(e.target.value)} />
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
        <input value={template} placeholder={t('Template')} aria-label={t('Template')} onChange={(e) => setTemplate(e.target.value)} />
        <input value={campaign} inputMode="numeric" placeholder={t('Campaign')} aria-label={t('Campaign')} onChange={(e) => setCampaign(e.target.value.replace(/[^\d]/g, ''))} />
        <button type="submit" className="flxmail-btn">{t('Filter')}</button>
        <span className="flxmail-muted">{t('{n} entries', { n: list.data?.flxMailLogs.totalCount ?? rows.length })}</span>
      </form>
      {list.isLoading && !rows.length ? <Loading /> : null}
      {list.isError ? <p className="flxmail-bad">{errorText(list.error, t('Something went wrong.'))}</p> : null}
      <div className="flxmail-card">
        <table className="flxmail-table">
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
                <td colSpan={4}>{t('No entries.')}</td>
              </tr>
            )}
            {rows.map((row) => (
              <tr key={row.databaseId} tabIndex={0} onClick={() => setOpen(row.databaseId)} onKeyDown={(e) => { if (e.key === 'Enter') setOpen(row.databaseId); }}>
                <td>
                  {row.createdAt?.label ?? row.createdAt?.relative ?? ''}
                  <br />
                  <span className="flxmail-muted">{row.channel}</span>
                </td>
                <td>
                  {row.recipient}
                  {row.person ? <div className="flxmail-muted">{row.person.name}</div> : null}
                </td>
                <td>
                  <strong>{row.subject || '—'}</strong>
                  <div className="flxmail-muted">{row.template}{row.campaignId ? ' · #' + row.campaignId : ''}</div>
                </td>
                <td>
                  <span className={'flxmail-badge is-' + row.status}>{statusLabel(row.status, t)}</span>
                  {row.error ? <div className="flxmail-bad">{row.error.slice(0, 160)}</div> : null}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {list.data?.flxMailLogs.pageInfo.hasNextPage ? (
        <button type="button" className="flxmail-btn" disabled={list.isFetching} onClick={() => setCursor(list.data?.flxMailLogs.pageInfo.endCursor ?? undefined)}>
          {t('Load more')}
        </button>
      ) : null}
      {open !== null ? (
        <div className="flxmail-card">
          <div className="flxmail-actions">
            <strong>{t('Details')}</strong>
            <button type="button" className="flxmail-link" onClick={() => setOpen(null)}>{t('Close')}</button>
          </div>
          {detail.isLoading ? <Loading /> : null}
          {detail.data?.flxMailLog ? (
            <>
              <p>{detail.data.flxMailLog.recipient} · {detail.data.flxMailLog.createdAt?.label}</p>
              {detail.data.flxMailLog.person ? <p>{detail.data.flxMailLog.person.name} · {detail.data.flxMailLog.person.email}</p> : null}
              {detail.data.flxMailLog.body ? <iframe title={t('Email preview')} sandbox="" className="flxmail-body" srcDoc={detail.data.flxMailLog.body} /> : <p className="flxmail-muted">{t('The body is not stored.')}</p>}
            </>
          ) : detail.isFetched ? <p>{t('No entries.')}</p> : null}
        </div>
      ) : null}
    </div>
  );
}

function statusLabel(status: string, t: (s: string) => string): string {
  if (status === 'sent') return t('Sent');
  if (status === 'failed') return t('Failed');
  if (status === 'blocked') return t('Disabled, not sent');
  if (status === 'sending') return t('Sending');
  return status;
}
