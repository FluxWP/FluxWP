// Forms › Responses: every response with filters, bulk read/unread/delete, and one response with its fields,
// replies and the reply box (close/open the conversation).
import { useEffect, useRef, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import type { FlxFormsEntriesQuery } from '@fluxwp/graphql/types';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { FlxFormsAdminForms, FlxFormsDashboard, FlxFormsEntries, FlxFormsEntriesDelete, FlxFormsEntriesMark, FlxFormsEntry, FlxFormsEntryReply, FlxFormsFormOptions } from '@fluxwp/graphql/operations';
import { Check, Loading, Modal } from '@fluxwp/ui';
import { errorText, payloadError, useForms } from './common';

type Row = FlxFormsEntriesQuery['flxFormsEntries']['nodes'][number];

export function ResponsesPage({ form, entry }: { form: number; entry: number }) {
  return entry ? <EntryView id={entry} /> : <EntryList form={form} />;
}

function useRefresh() {
  const qc = useQueryClient();
  const { scope } = useGraphQL();
  return () => {
    for (const op of [FlxFormsEntries, FlxFormsAdminForms, FlxFormsDashboard]) void qc.invalidateQueries({ queryKey: flxQueryKey(scope, op) });
  };
}

function EntryList({ form: startForm }: { form: number }) {
  const { t, go, toast, num } = useForms();
  const refreshAll = useRefresh();
  const [form, setForm] = useState(startForm);
  const [status, setStatus] = useState('');
  const [search, setSearch] = useState('');
  const [q, setQ] = useState('');
  const [cursor, setCursor] = useState<string | undefined>(undefined);
  const [rows, setRows] = useState<Row[]>([]);
  const [picked, setPicked] = useState<Set<number>>(new Set());
  const [ask, setAsk] = useState(false);

  useEffect(() => setForm(startForm), [startForm]);
  useEffect(() => {
    const timer = window.setTimeout(() => setQ(search.trim()), 300);
    return () => window.clearTimeout(timer);
  }, [search]);
  useEffect(() => {
    setCursor(undefined);
    setPicked(new Set());
  }, [q, form, status]);

  const options = useFlxQuery(FlxFormsFormOptions, {});
  const list = useFlxQuery(FlxFormsEntries, { first: 50, after: cursor, form: form || undefined, status: status || undefined, search: q || undefined });
  useEffect(() => {
    const nodes = list.data?.flxFormsEntries.nodes;
    if (!nodes) return;
    setRows((prev) => {
      const next = cursor ? [...prev, ...nodes] : nodes;
      const seen = new Set<number>();
      return next.filter((r) => (seen.has(r.databaseId) ? false : (seen.add(r.databaseId), true)));
    });
  }, [list.dataUpdatedAt]); // eslint-disable-line react-hooks/exhaustive-deps

  const mark = useFlxMutation(FlxFormsEntriesMark);
  const del = useFlxMutation(FlxFormsEntriesDelete);
  const ids = [...picked];
  const busy = mark.isPending || del.isPending;

  const refresh = () => {
    setCursor(undefined);
    setPicked(new Set());
    refreshAll();
  };
  const setRead = async (read: boolean) => {
    try {
      const r = (await mark.mutateAsync({ input: { ids, read } })).flxFormsEntriesMark;
      const err = payloadError(r?.errors);
      if (err) throw new Error(err);
      setRows((l) => l.map((x) => (picked.has(x.databaseId) ? { ...x, read } : x)));
      toast(read ? t('Marked as read.') : t('Marked as unread.'));
      refresh();
    } catch (e) {
      toast(errorText(e, t('Something went wrong.')), 'error');
    }
  };
  const remove = async () => {
    try {
      const r = (await del.mutateAsync({ input: { ids } })).flxFormsEntriesDelete;
      const err = payloadError(r?.errors);
      if (err) throw new Error(err);
      setRows((l) => l.filter((x) => !picked.has(x.databaseId)));
      toast(t('{n} responses deleted.', { n: num(r?.deleted ?? ids.length) }));
      setAsk(false);
      refresh();
    } catch (e) {
      toast(errorText(e, t('Something went wrong.')), 'error');
    }
  };

  const all = rows.length > 0 && rows.every((r) => picked.has(r.databaseId));
  const toggle = (id: number, on: boolean) =>
    setPicked((s) => {
      const n = new Set(s);
      if (on) n.add(id);
      else n.delete(id);
      return n;
    });
  const total = list.data?.flxFormsEntries.totalCount ?? rows.length;

  return (
    <div>
      <div className="flxfrm-filters">
        <select aria-label={t('All forms')} value={form} onChange={(e) => setForm(Number(e.target.value))}>
          <option value={0}>{t('All forms')}</option>
          {(options.data?.flxFormsFormOptions ?? []).map((f) => (
            <option key={f.databaseId} value={f.databaseId}>
              {f.title}
            </option>
          ))}
        </select>
        <select aria-label={t('Status')} value={status} onChange={(e) => setStatus(e.target.value)}>
          <option value="">{t('All')}</option>
          <option value="unread">{t('Unread')}</option>
          <option value="read">{t('Read')}</option>
        </select>
        <input type="search" value={search} placeholder={t('Search track code or text')} aria-label={t('Search track code or text')} onChange={(e) => setSearch(e.target.value)} />
        <span className="flxfrm-muted">{t('{n} responses', { n: num(total) })}</span>
      </div>
      {ids.length ? (
        <div className="flxfrm-filters is-bulk">
          <strong>{t('{n} selected', { n: num(ids.length) })}</strong>
          <button type="button" className="flxfrm-btn" disabled={busy} onClick={() => void setRead(true)}>
            {t('Mark as read')}
          </button>
          <button type="button" className="flxfrm-btn" disabled={busy} onClick={() => void setRead(false)}>
            {t('Mark as unread')}
          </button>
          <button type="button" className="flxfrm-btn is-danger-text" disabled={busy} onClick={() => setAsk(true)}>
            {t('Delete')}
          </button>
        </div>
      ) : null}
      {list.isLoading && !rows.length ? <Loading /> : null}
      {list.isError ? <p className="flxfrm-bad">{errorText(list.error, t('Something went wrong.'))}</p> : null}
      <div className="flxfrm-card is-flush">
        <table className="flxfrm-table">
          <thead>
            <tr>
              <th className="is-check">
                <Check aria-label={t('Select all')} checked={all} indeterminate={!all && ids.length > 0} onChange={(on) => setPicked(on ? new Set(rows.map((r) => r.databaseId)) : new Set())} />
              </th>
              <th>{t('Response')}</th>
              <th>{t('Form')}</th>
              <th>{t('Date')}</th>
            </tr>
          </thead>
          <tbody>
            {!rows.length && list.isSuccess ? (
              <tr>
                <td colSpan={4} className="flxfrm-muted">
                  {t('No responses yet.')}
                </td>
              </tr>
            ) : null}
            {rows.map((row) => (
              <tr key={row.databaseId} className={row.read ? '' : 'is-unread'}>
                <td className="is-check">
                  <Check aria-label={t('Select')} checked={picked.has(row.databaseId)} onChange={(on) => toggle(row.databaseId, on)} />
                </td>
                <td data-label={t('Response')}>
                  <button type="button" className="flxfrm-link" onClick={() => go('responses', { entry: row.databaseId })}>
                    {row.read ? null : <span className="flxfrm-dot" aria-label={t('Unread')} />}
                    <strong>{row.trackCode || '#' + row.databaseId}</strong>
                  </button>
                  <div className="flxfrm-muted flxfrm-clip">{row.preview}</div>
                </td>
                <td data-label={t('Form')}>{row.formTitle || '#' + row.formId}</td>
                <td data-label={t('Date')}>
                  <span title={row.createdAt.label}>{row.createdAt.relative}</span>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {list.data?.flxFormsEntries.pageInfo.hasNextPage ? (
        <button type="button" className="flxfrm-btn" disabled={list.isFetching} onClick={() => setCursor(list.data?.flxFormsEntries.pageInfo.endCursor ?? undefined)}>
          {t('Load more')}
        </button>
      ) : null}
      {ask ? (
        <Modal title={t('Delete')} onClose={() => setAsk(false)}>
          <p>{t('Delete {n} responses? This cannot be undone.', { n: num(ids.length) })}</p>
          <div className="flxfrm-actions is-end">
            <button type="button" className="flxfrm-btn" onClick={() => setAsk(false)}>
              {t('Cancel')}
            </button>
            <button type="button" className="flxfrm-btn is-danger" disabled={del.isPending} onClick={() => void remove()}>
              {t('Delete')}
            </button>
          </div>
        </Modal>
      ) : null}
    </div>
  );
}

function EntryView({ id }: { id: number }) {
  const { t, go, toast } = useForms();
  const refreshAll = useRefresh();
  const qc = useQueryClient();
  const { scope } = useGraphQL();
  const q = useFlxQuery(FlxFormsEntry, { id });
  const e = q.data?.flxFormsEntry;
  const mark = useFlxMutation(FlxFormsEntriesMark);
  const reply = useFlxMutation(FlxFormsEntryReply);
  const del = useFlxMutation(FlxFormsEntriesDelete);
  const [text, setText] = useState('');
  const [ask, setAsk] = useState(false);
  const marked = useRef(0);

  // Opening a response marks it read (once per response).
  useEffect(() => {
    if (!e || e.read || marked.current === e.databaseId) return;
    marked.current = e.databaseId;
    void mark.mutateAsync({ input: { ids: [e.databaseId], read: true } }).then(() => refreshAll());
  }, [e]); // eslint-disable-line react-hooks/exhaustive-deps

  const put = (next: NonNullable<typeof e>) => qc.setQueryData(flxQueryKey(scope, FlxFormsEntry, { id }), { flxFormsEntry: next });

  const send = async (close?: boolean) => {
    try {
      const r = (await reply.mutateAsync({ input: { id, message: text.trim(), close } })).flxFormsEntryReply;
      const err = payloadError(r?.errors);
      if (err) throw new Error(err);
      if (r?.entry) put(r.entry);
      setText('');
      toast(text.trim() ? t('Reply sent.') : close ? t('Conversation closed.') : t('Conversation opened.'));
      refreshAll();
    } catch (err) {
      toast(errorText(err, t('Something went wrong.')), 'error');
    }
  };
  const unread = async () => {
    try {
      const r = (await mark.mutateAsync({ input: { ids: [id], read: false } })).flxFormsEntriesMark;
      const err = payloadError(r?.errors);
      if (err) throw new Error(err);
      toast(t('Marked as unread.'));
      refreshAll();
      go('responses', { form: e?.formId });
    } catch (err) {
      toast(errorText(err, t('Something went wrong.')), 'error');
    }
  };
  const remove = async () => {
    try {
      const r = (await del.mutateAsync({ input: { ids: [id] } })).flxFormsEntriesDelete;
      const err = payloadError(r?.errors);
      if (err) throw new Error(err);
      toast(t('Response deleted.'));
      refreshAll();
      go('responses', { form: e?.formId });
    } catch (err) {
      toast(errorText(err, t('Something went wrong.')), 'error');
    }
  };

  return (
    <div className="flxfrm-entry">
      <div className="flxfrm-filters">
        <button type="button" className="flxfrm-btn" onClick={() => go('responses', { form: e?.formId })}>
          ← {t('Responses')}
        </button>
        {e ? (
          <>
            <button type="button" className="flxfrm-btn flxfrm-push" disabled={mark.isPending} onClick={() => void unread()}>
              {t('Mark as unread')}
            </button>
            <button type="button" className="flxfrm-btn is-danger-text" onClick={() => setAsk(true)}>
              {t('Delete')}
            </button>
          </>
        ) : null}
      </div>
      {q.isLoading ? <Loading /> : null}
      {q.isError ? <p className="flxfrm-bad">{errorText(q.error, t('Something went wrong.'))}</p> : null}
      {q.data && !e ? <p className="flxfrm-empty">{t('This response was not found.')}</p> : null}
      {e ? (
        <div className="flxfrm-grid2 is-entry">
          <div className="flxfrm-card">
            <h2>{e.formTitle || '#' + e.formId}</h2>
            <p className="flxfrm-muted">
              {t('Track code')}: <strong>{e.trackCode}</strong> · {e.createdAt.label}
              {e.ip ? ' · IP ' + e.ip : ''}
              {e.closed ? <span className="flxfrm-badge">{t('Closed')}</span> : null}
            </p>
            <dl className="flxfrm-fields">
              {(e.fields ?? []).map((f, i) => (
                <div key={i}>
                  <dt>{f.label}</dt>
                  <dd>
                    {f.isLink ? (
                      <a href={f.value} target="_blank" rel="noopener noreferrer">
                        {f.value}
                      </a>
                    ) : (
                      f.value
                    )}
                  </dd>
                </div>
              ))}
            </dl>
          </div>
          <div className="flxfrm-card">
            <h2>{t('Replies')}</h2>
            {e.replies?.length ? (
              <ul className="flxfrm-replies">
                {e.replies.map((r) => (
                  <li key={r.databaseId} className={r.byStaff ? 'is-staff' : ''}>
                    <span className="flxfrm-muted">
                      {r.author || (r.byStaff ? t('Staff') : t('Visitor'))} · <span title={r.createdAt.label}>{r.createdAt.relative}</span>
                    </span>
                    {r.state ? <span className="flxfrm-muted">{r.state === 'closed' ? t('Closed the conversation.') : t('Opened the conversation.')}</span> : null}
                    {r.body ? <p>{r.body}</p> : null}
                    {r.attachments.map((a) => (
                      <a key={a} href={a} target="_blank" rel="noopener noreferrer" className="flxfrm-clip">
                        {a.split('/').pop()}
                      </a>
                    ))}
                  </li>
                ))}
              </ul>
            ) : (
              <p className="flxfrm-muted">{t('No replies yet.')}</p>
            )}
            <textarea rows={4} value={text} placeholder={t('Write a reply')} aria-label={t('Write a reply')} onChange={(ev) => setText(ev.target.value)} />
            <div className="flxfrm-actions">
              <button type="button" className="flxfrm-btn is-primary" disabled={reply.isPending || !text.trim()} onClick={() => void send()}>
                {t('Send reply')}
              </button>
              <button type="button" className="flxfrm-btn flxfrm-push" disabled={reply.isPending} onClick={() => void send(!e.closed)}>
                {e.closed ? t('Open conversation') : t('Close conversation')}
              </button>
            </div>
          </div>
        </div>
      ) : null}
      {ask ? (
        <Modal title={t('Delete')} onClose={() => setAsk(false)}>
          <p>{t('Delete this response and its replies? This cannot be undone.')}</p>
          <div className="flxfrm-actions is-end">
            <button type="button" className="flxfrm-btn" onClick={() => setAsk(false)}>
              {t('Cancel')}
            </button>
            <button type="button" className="flxfrm-btn is-danger" disabled={del.isPending} onClick={() => void remove()}>
              {t('Delete')}
            </button>
          </div>
        </Modal>
      ) : null}
    </div>
  );
}
