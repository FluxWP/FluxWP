// Responses of one form: list with filters, bulk read and delete, CSV link, survey chart, and the
// response drawer (answers, conversation, reply with files, close/open ticket, delete).
import { useEffect, useMemo, useRef, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import {
  FlxFormResponse,
  FlxFormResponseDelete,
  FlxFormResponseRead,
  FlxFormResponseReply,
  FlxFormResponses,
  FlxFormResponsesDelete,
  FlxFormResponsesRead,
  FlxFormsExportUrl,
  FlxFormsPanel,
  FlxFormsStorageClear,
} from '@fluxwp/graphql/operations';
import type { FlxFormResponseQuery, FlxFormResponsesQuery, FlxFormResponseStatus, FlxFormReplyState } from '@fluxwp/graphql/types';
import { Icon, Loading } from '@fluxwp/ui';
import { useHost } from '../host';
import type { Translate } from './i18n';
import { Confirm, copyText, errorText, NoticeBar, type Notice } from './ui';

type Item = FlxFormResponsesQuery['flxFormResponses']['nodes'][number];
type Full = NonNullable<FlxFormResponseQuery['flxFormResponse']>;

const PAGE = 25;
const STATUSES: FlxFormResponseStatus[] = ['UNREAD', 'READ', 'UNREAD_FOLLOWUP', 'READ_FOLLOWUP'];
const isNew = (s: FlxFormResponseStatus) => s === 'UNREAD' || s === 'UNREAD_FOLLOWUP';

export function Responses({ t, lang, formId, openId, onOpen, onBack }: { t: Translate; lang: string; formId: number; openId: number; onOpen: (id: number) => void; onBack: () => void }) {
  const host = useHost();
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const [search, setSearch] = useState('');
  const [term, setTerm] = useState('');
  const [unread, setUnread] = useState(false);
  const [status, setStatus] = useState<FlxFormResponseStatus | ''>('');
  const [cursors, setCursors] = useState<(string | null)[]>([null]);
  const [picked, setPicked] = useState<Set<string>>(new Set());
  const [notice, setNotice] = useState<Notice | null>(null);
  const [confirm, setConfirm] = useState<'delete' | 'clear' | null>(null);
  const [chart, setChart] = useState(false);
  const page = cursors.length - 1;

  // The search runs a moment after typing stops.
  useEffect(() => {
    const id = window.setTimeout(() => {
      setTerm(search.trim());
      setCursors([null]);
      setPicked(new Set());
    }, 350);
    return () => window.clearTimeout(id);
  }, [search]);

  const vars = { formId: String(formId), unread: unread || null, status: status || null, search: term || null, first: PAGE, after: cursors[page] ?? null };
  const q = useFlxQuery(FlxFormResponses, vars, { placeholderData: (prev) => prev });
  const exportUrl = useFlxQuery(FlxFormsExportUrl, { formId: String(formId) }, { enabled: host.mode === 'wp', staleTime: 10 * 60_000 });

  const refresh = () => {
    void qc.invalidateQueries({ queryKey: [scope, 'gql', FlxFormResponses.name] });
    void qc.invalidateQueries({ queryKey: [scope, 'gql', FlxFormsPanel.name] });
    host.refreshCounts?.();
  };
  const fail = (e: unknown) => setNotice({ kind: 'error', text: errorText(e, t('The action could not be completed.')) });
  const done = (text: string) => setNotice({ kind: 'success', text });

  const bulkRead = useFlxMutation(FlxFormResponsesRead, {
    onSuccess: (r) => {
      const p = r.flxFormResponsesRead;
      if (!p || p.errors.length) return fail(p?.errors[0]?.message);
      setPicked(new Set());
      refresh();
      done(t('Marked as read.'));
    },
    onError: fail,
  });
  const bulkDelete = useFlxMutation(FlxFormResponsesDelete, {
    onSuccess: (r) => {
      const p = r.flxFormResponsesDelete;
      setConfirm(null);
      if (!p || p.errors.length) return fail(p?.errors[0]?.message);
      setPicked(new Set());
      refresh();
      done(t('Deleted.'));
    },
    onError: (e) => {
      setConfirm(null);
      fail(e);
    },
  });
  const clear = useFlxMutation(FlxFormsStorageClear, {
    onSuccess: (r) => {
      setConfirm(null);
      const p = r.flxFormsStorageClear;
      if (!p || p.errors.length) return fail(p?.errors[0]?.message);
      done(t('Unused files were deleted.'));
    },
    onError: (e) => {
      setConfirm(null);
      fail(e);
    },
  });

  const nodes = q.data?.flxFormResponses.nodes ?? [];
  const total = q.data?.flxFormResponses.totalCount ?? nodes.length;
  const title = nodes[0]?.formTitle ?? t('Form');
  const formType = nodes[0]?.formType ?? 'FORM';
  const allOn = nodes.length > 0 && nodes.every((n) => picked.has(n.id));
  const toggle = (id: string) =>
    setPicked((o) => {
      const n = new Set(o);
      if (n.has(id)) n.delete(id);
      else n.add(id);
      return n;
    });
  const reset = () => setCursors([null]);

  return (
    <div className="flxf-resp">
      <div className="flxf-bar">
        <button type="button" className="flxf-btn" onClick={onBack}>
          <span aria-hidden="true">←</span>
          {t('All forms')}
        </button>
        <h2 className="flxf-bar__title">{title}</h2>
        <span className="flxf-bar__spacer" />
        {formType === 'SURVEY' && (
          <button type="button" className={'flxf-btn' + (chart ? ' is-primary' : '')} aria-pressed={chart} onClick={() => setChart((v) => !v)}>
            <Icon name="chart" size={14} />
            {t('Chart')}
          </button>
        )}
        {host.mode === 'wp' && exportUrl.data?.flxFormsExportUrl && (
          <a className="flxf-btn" href={exportUrl.data.flxFormsExportUrl} title={t('Download the responses of this form as a CSV file')}>
            <Icon name="download" size={14} />
            {t('Download CSV')}
          </a>
        )}
        <button type="button" className="flxf-btn" onClick={() => setConfirm('clear')} title={t('Delete uploaded files that no response uses any more')}>
          <Icon name="trash" size={14} />
          {t('Clear unused files')}
        </button>
      </div>

      <NoticeBar notice={notice} label={t('Close')} onClose={() => setNotice(null)} />

      <section className="flxf-card">
        <div className="flxf-filters">
          <label className="flxf-search">
            <Icon name="search" size={14} />
            <input type="search" value={search} placeholder={t('Search answers, replies and tracking codes')} aria-label={t('Search')} onChange={(e) => setSearch(e.currentTarget.value)} />
          </label>
          <label className="flxf-inline">
            <input
              type="checkbox"
              checked={unread}
              onChange={(e) => {
                setUnread(e.currentTarget.checked);
                reset();
              }}
            />
            {t('Unread only')}
          </label>
          <label className="flxf-inline">
            <span>{t('Status')}</span>
            <select
              value={status}
              onChange={(e) => {
                setStatus(e.currentTarget.value as FlxFormResponseStatus | '');
                reset();
              }}
            >
              <option value="">{t('Any')}</option>
              {STATUSES.map((s) => (
                <option key={s} value={s}>
                  {statusLabel(s, t)}
                </option>
              ))}
            </select>
          </label>
          <span className="flxf-muted flxf-filters__count">{t('%s responses', total.toLocaleString(lang))}</span>
        </div>

        {picked.size > 0 && (
          <div className="flxf-bulk" role="toolbar" aria-label={t('Selected responses')}>
            <strong>{t('%s selected', picked.size)}</strong>
            <button type="button" className="flxf-btn" disabled={bulkRead.isPending} onClick={() => bulkRead.mutate({ input: { ids: [...picked] } })}>
              <Icon name="check" size={14} />
              {t('Mark as read')}
            </button>
            <button type="button" className="flxf-btn is-danger" onClick={() => setConfirm('delete')}>
              <Icon name="trash" size={14} />
              {t('Delete')}
            </button>
            <button type="button" className="flxf-btn" onClick={() => setPicked(new Set())}>
              {t('Clear selection')}
            </button>
          </div>
        )}

        {chart && <SurveyChart nodes={nodes} t={t} />}

        {q.isPending ? (
          <Loading />
        ) : q.isError ? (
          <p className="flxf-empty">{t('The responses could not be loaded.')}</p>
        ) : !nodes.length ? (
          <div className="flxf-empty-card">
            <Icon name="mail" size={28} />
            <h2>{term || unread || status ? t('No response matches') : t('No responses yet')}</h2>
            <p>{term || unread || status ? t('Change the search or the filters.') : t('Submitted responses will appear here.')}</p>
          </div>
        ) : (
          <div className="flxf-table-wrap">
            <table className="flxf-table">
              <thead>
                <tr>
                  <th className="is-check">
                    <input
                      type="checkbox"
                      aria-label={t('Select all on this page')}
                      checked={allOn}
                      onChange={(e) => setPicked(e.currentTarget.checked ? new Set(nodes.map((n) => n.id)) : new Set())}
                    />
                  </th>
                  <th>{t('Content')}</th>
                  <th>{t('Tracking code')}</th>
                  <th>{t('Date')}</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                {nodes.map((n) => (
                  <Row key={n.id} n={n} t={t} on={picked.has(n.id)} onPick={() => toggle(n.id)} onOpen={() => onOpen(Number(n.id))} />
                ))}
              </tbody>
            </table>
          </div>
        )}

        {(page > 0 || q.data?.flxFormResponses.pageInfo.hasNextPage) && (
          <div className="flxf-pager">
            <button type="button" className="flxf-btn" disabled={page === 0} onClick={() => {
                setCursors((c) => c.slice(0, -1));
                setPicked(new Set());
              }}>
              {t('Previous')}
            </button>
            <span>{t('Page %s', page + 1)}</span>
            <button
              type="button"
              className="flxf-btn"
              disabled={!q.data?.flxFormResponses.pageInfo.hasNextPage}
              onClick={() => {
                setCursors((c) => [...c, q.data?.flxFormResponses.pageInfo.endCursor ?? null]);
                setPicked(new Set());
              }}
            >
              {t('Next')}
            </button>
          </div>
        )}
      </section>

      {openId > 0 && <Drawer id={String(openId)} t={t} onClose={() => onOpen(0)} onChanged={refresh} />}

      <Confirm
        open={confirm === 'delete'}
        title={t('Delete the selected responses?')}
        body={t('%s responses and their replies will be deleted. This cannot be undone.', picked.size)}
        confirm={t('Delete')}
        cancel={t('Cancel')}
        danger
        busy={bulkDelete.isPending}
        onConfirm={() => bulkDelete.mutate({ input: { ids: [...picked] } })}
        onCancel={() => setConfirm(null)}
      />
      <Confirm
        open={confirm === 'clear'}
        title={t('Delete unused files?')}
        body={t('Uploaded files that no stored response points to will be deleted.')}
        confirm={t('Delete files')}
        cancel={t('Cancel')}
        danger
        busy={clear.isPending}
        onConfirm={() => clear.mutate({})}
        onCancel={() => setConfirm(null)}
      />
    </div>
  );
}

function statusLabel(s: FlxFormResponseStatus, t: Translate): string {
  switch (s) {
    case 'UNREAD':
      return t('New');
    case 'UNREAD_FOLLOWUP':
      return t('New reply');
    case 'READ_FOLLOWUP':
      return t('Read, with replies');
    case 'PENDING_PAYMENT':
      return t('Waiting for payment');
    default:
      return t('Read');
  }
}

function Row({ n, t, on, onPick, onOpen }: { n: Item; t: Translate; on: boolean; onPick: () => void; onOpen: () => void }) {
  const fresh = isNew(n.status);
  return (
    <tr className={'is-click' + (fresh ? ' is-new' : '')} onClick={onOpen}>
      <td className="is-check" onClick={(e) => e.stopPropagation()}>
        <input type="checkbox" aria-label={t('Select response %s', n.trackCode)} checked={on} onChange={onPick} />
      </td>
      <td data-label={t('Content')}>
        <button
          type="button"
          className="flxf-link"
          onClick={(e) => {
            e.stopPropagation();
            onOpen();
          }}
          title={n.preview}
        >
          {n.preview || '—'}
        </button>
      </td>
      <td data-label={t('Tracking code')}>
        <span className="flxf-code">
          <code>{n.trackCode}</code>
          <button
            type="button"
            className="flxf-icon-btn"
            aria-label={t('Copy the tracking code')}
            onClick={(e) => {
              e.stopPropagation();
              copyText(n.trackCode);
            }}
          >
            <Icon name="clipboard" size={14} />
          </button>
        </span>
      </td>
      <td data-label={t('Date')}>{n.createdAt.label}</td>
      <td className="is-act">
        <span className={'flxf-badge ' + (fresh ? 'is-new' : '')}>{statusLabel(n.status, t)}</span>
      </td>
    </tr>
  );
}

/** How often each answer was given, per question, from the loaded responses (the classic panel's survey chart). */
function SurveyChart({ nodes, t }: { nodes: Item[]; t: Translate }) {
  const data = useMemo(() => {
    const by = new Map<string, Map<string, number>>();
    for (const n of nodes) {
      for (const f of n.fields) {
        if (f.isFile || !f.label) continue;
        const m = by.get(f.label) ?? new Map<string, number>();
        for (const v of f.value.split(', ')) {
          const k = v.trim();
          if (k) m.set(k, (m.get(k) ?? 0) + 1);
        }
        by.set(f.label, m);
      }
    }
    return [...by.entries()].slice(0, 12);
  }, [nodes]);
  if (!data.length) return <p className="flxf-muted">{t('There are no answers to chart yet.')}</p>;
  return (
    <div className="flxf-chart" role="img" aria-label={t('Survey answers')}>
      <p className="flxf-muted">{t('Counted from the responses on this page.')}</p>
      {data.map(([label, counts]) => {
        const max = Math.max(...counts.values());
        return (
          <div key={label} className="flxf-chart__q">
            <h3>{label}</h3>
            {[...counts.entries()]
              .sort((a, b) => b[1] - a[1])
              .slice(0, 10)
              .map(([v, c]) => (
                <div key={v} className="flxf-chart__row">
                  <span title={v}>{v}</span>
                  <i style={{ width: Math.max(4, Math.round((c / max) * 100)) + '%' }} />
                  <b>{c}</b>
                </div>
              ))}
          </div>
        );
      })}
    </div>
  );
}

interface UploadResult {
  success?: boolean;
  data?: { success?: boolean; file?: { url?: string }; error?: string; m?: string };
}

function Drawer({ id, t, onClose, onChanged }: { id: string; t: Translate; onClose: () => void; onChanged: () => void }) {
  const host = useHost();
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const q = useFlxQuery(FlxFormResponse, { id }, { retry: false });
  const [text, setText] = useState('');
  const [files, setFiles] = useState<{ name: string; url: string }[]>([]);
  const [busyUpload, setBusyUpload] = useState(false);
  const [notice, setNotice] = useState<Notice | null>(null);
  const [confirm, setConfirm] = useState<'delete' | 'close' | null>(null);
  const markedRef = useRef('');
  const thread = useRef<HTMLDivElement | null>(null);

  const fail = (e: unknown) => setNotice({ kind: 'error', text: errorText(e, t('The action could not be completed.')) });
  const put = (r: Full) => qc.setQueryData<FlxFormResponseQuery>(flxQueryKey(scope, FlxFormResponse, { id }), { flxFormResponse: r });
  const r = q.data?.flxFormResponse ?? null;

  const read = useFlxMutation(FlxFormResponseRead, {
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxFormResponse, { id }) });
      onChanged();
    },
  });
  useEffect(() => {
    if (r && isNew(r.status) && markedRef.current !== r.id) {
      markedRef.current = r.id;
      read.mutate({ input: { id: r.id } });
    }
  }, [r, read]);

  useEffect(() => {
    thread.current?.scrollTo?.({ top: thread.current.scrollHeight });
  }, [r?.replies.length]);

  useEffect(() => {
    const key = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && !confirm) onClose();
    };
    window.addEventListener('keydown', key);
    return () => window.removeEventListener('keydown', key);
  }, [onClose, confirm]);

  const send = useFlxMutation(FlxFormResponseReply, {
    onSuccess: (res) => {
      const p = res.flxFormResponseReply;
      setConfirm(null);
      if (!p || p.errors.length || !p.response) return fail(p?.errors[0]?.message);
      put(p.response);
      setText('');
      setFiles([]);
      setNotice({ kind: 'success', text: t('Sent.') });
      onChanged();
    },
    onError: (e) => {
      setConfirm(null);
      fail(e);
    },
  });
  const del = useFlxMutation(FlxFormResponseDelete, {
    onSuccess: (res) => {
      const p = res.flxFormResponseDelete;
      setConfirm(null);
      if (!p || p.errors.length) return fail(p?.errors[0]?.message);
      onChanged();
      onClose();
    },
    onError: (e) => {
      setConfirm(null);
      fail(e);
    },
  });

  const submit = (state: FlxFormReplyState) =>
    send.mutate({ input: { submissionId: id, message: text.trim() || null, attachments: files.map((f) => f.url), state } });

  const attach = async (file: File) => {
    if (!r) return;
    setBusyUpload(true);
    setNotice(null);
    try {
      const fd = new FormData();
      fd.append('id', 'flxf-react');
      fd.append('pl', 'resp');
      fd.append('response_id', r.id);
      fd.append('response_track', r.trackCode);
      fd.append('async-upload', file);
      const res = await host.client<UploadResult>('Emsfb/v1/forms/file/upload', { method: 'POST', formData: fd });
      const url = res.data?.file?.url;
      if (!res.data?.success || !url) throw new Error(res.data?.error || res.data?.m || t('The file could not be uploaded.'));
      setFiles((o) => [...o, { name: file.name, url }]);
    } catch (e) {
      fail(e);
    } finally {
      setBusyUpload(false);
    }
  };

  return (
    <div className="flxf-drawer" role="presentation" onClick={onClose}>
      <aside className="flxf-drawer__panel" role="dialog" aria-modal="true" aria-label={r ? r.formTitle : t('Response')} onClick={(e) => e.stopPropagation()}>
        <header className="flxf-drawer__head">
          <div>
            <h2>{r?.formTitle ?? t('Response')}</h2>
            {r && (
              <p className="flxf-muted">
                {r.createdAt.label} · <code>{r.trackCode}</code>
              </p>
            )}
          </div>
          <div className="flxf-drawer__tools">
            {r && (
              <button type="button" className="flxf-icon-btn" aria-label={t('Copy the tracking code')} title={t('Copy the tracking code')} onClick={() => copyText(r.trackCode)}>
                <Icon name="clipboard" size={16} />
              </button>
            )}
            {r && (
              <button type="button" className="flxf-icon-btn is-danger" aria-label={t('Delete')} title={t('Delete')} onClick={() => setConfirm('delete')}>
                <Icon name="trash" size={16} />
              </button>
            )}
            <button type="button" className="flxf-icon-btn" aria-label={t('Close')} onClick={onClose}>
              <Icon name="no-alt" size={16} />
            </button>
          </div>
        </header>

        <div className="flxf-drawer__body">
          <NoticeBar notice={notice} label={t('Close')} onClose={() => setNotice(null)} />
          {q.isPending ? (
            <Loading />
          ) : q.isError || !r ? (
            <p className="flxf-empty">{t('This response could not be found. It may have been deleted.')}</p>
          ) : (
            <>
              {!r.open && (
                <p className="flxf-ticket is-closed">
                  <Icon name="lock" size={14} />
                  {t('This ticket is closed.')}
                </p>
              )}
              <dl className="flxf-fields">
                {r.fields.map((f, i) => (
                  <div key={i}>
                    <dt>{f.label || t('Field')}</dt>
                    <dd>
                      {f.isFile ? (
                        <a href={f.value} target="_blank" rel="noopener noreferrer">
                          {fileName(f.value)}
                        </a>
                      ) : (
                        f.value
                      )}
                    </dd>
                  </div>
                ))}
              </dl>

              <h3 className="flxf-sub">{t('Conversation')}</h3>
              <div className="flxf-thread" ref={thread} aria-live="polite">
                {!r.replies.length && <p className="flxf-muted">{t('No replies yet.')}</p>}
                {r.replies.map((x) => (
                  <article key={x.id} className={'flxf-msg is-' + x.author.toLowerCase() + (x.kind !== 'REPLY' ? ' is-marker' : '')}>
                    <header>
                      <strong>{x.by}</strong>
                      <time dateTime={x.createdAt.iso}>{x.createdAt.label}</time>
                    </header>
                    {x.kind !== 'REPLY' && <p className="flxf-msg__marker">{x.kind === 'CLOSED' ? t('The ticket was closed.') : t('The ticket was opened again.')}</p>}
                    {x.body && <p>{x.body}</p>}
                    {x.attachments.map((u) => (
                      <a key={u} className="flxf-file" href={u} target="_blank" rel="noopener noreferrer">
                        <Icon name="upload" size={13} />
                        {fileName(u)}
                      </a>
                    ))}
                  </article>
                ))}
              </div>

              <form
                className="flxf-reply"
                onSubmit={(e) => {
                  e.preventDefault();
                  if (text.trim() || files.length) submit('REPLY');
                }}
              >
                <label className="flxf-sr" htmlFor="flxf-reply-text">
                  {t('Your reply')}
                </label>
                <textarea id="flxf-reply-text" rows={4} value={text} maxLength={5000} placeholder={t('Write a reply')} onChange={(e) => setText(e.currentTarget.value)} />
                {files.length > 0 && (
                  <ul className="flxf-chips">
                    {files.map((f) => (
                      <li key={f.url}>
                        <Icon name="upload" size={12} />
                        {f.name}
                        <button type="button" aria-label={t('Remove %s', f.name)} onClick={() => setFiles((o) => o.filter((x) => x.url !== f.url))}>
                          ×
                        </button>
                      </li>
                    ))}
                  </ul>
                )}
                <div className="flxf-reply__row">
                  <label className={'flxf-btn' + (busyUpload ? ' is-busy' : '')}>
                    <Icon name="upload" size={14} />
                    {busyUpload ? t('Uploading…') : t('Attach a file')}
                    <input
                      type="file"
                      className="flxf-sr"
                      disabled={busyUpload}
                      onChange={(e) => {
                        const f = e.currentTarget.files?.[0];
                        e.currentTarget.value = '';
                        if (f) void attach(f);
                      }}
                    />
                  </label>
                  <span className="flxf-bar__spacer" />
                  <button type="button" className="flxf-btn" disabled={send.isPending} onClick={() => (r.open ? setConfirm('close') : submit('OPEN'))}>
                    <Icon name={r.open ? 'lock' : 'unlock'} size={14} />
                    {r.open ? t('Close the ticket') : t('Open the ticket')}
                  </button>
                  <button type="submit" className="flxf-btn is-primary" disabled={send.isPending || busyUpload || (!text.trim() && !files.length)}>
                    <Icon name="arrow-right-alt2" size={14} />
                    {send.isPending ? t('Sending…') : t('Send')}
                  </button>
                </div>
                <p className="flxf-muted">{t('The visitor gets an email when a reply is sent.')}</p>
              </form>
            </>
          )}
        </div>
      </aside>

      <Confirm
        open={confirm === 'delete'}
        title={t('Delete this response?')}
        body={t('The response and its replies will be deleted. This cannot be undone.')}
        confirm={t('Delete')}
        cancel={t('Cancel')}
        danger
        busy={del.isPending}
        onConfirm={() => del.mutate({ input: { id } })}
        onCancel={() => setConfirm(null)}
      />
      <Confirm
        open={confirm === 'close'}
        title={t('Close the ticket?')}
        body={t('The ticket is marked closed. You can open it again at any time.')}
        confirm={t('Close the ticket')}
        cancel={t('Cancel')}
        busy={send.isPending}
        onConfirm={() => submit('CLOSE')}
        onCancel={() => setConfirm(null)}
      />
    </div>
  );
}

function fileName(url: string): string {
  try {
    const p = new URL(url).pathname.split('/').filter(Boolean);
    return decodeURIComponent(p[p.length - 1] ?? url);
  } catch {
    return url;
  }
}
