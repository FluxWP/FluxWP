// Flux AI › Chat history: the signed-in user's chats with search, archive filter, rename, archive and delete,
// and a read-only transcript. Continuing a chat happens in the chat itself (its own stream protocol).
import { useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { FlxAiChat, FlxAiChatDelete, FlxAiChatUpdate, FlxAiChats } from '@fluxwp/graphql/operations';
import type { FlxAiChatSummaryFieldsFragment } from '@fluxwp/graphql/types';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { Loading, Modal } from '@fluxwp/ui';
import { useHost } from '../host';
import { Card, NoticeBar, Pill, errText, useAi, type Notice } from './common';

type Chat = FlxAiChatSummaryFieldsFragment;
const PAGE = 30;
const FILTERS: [number, string][] = [
  [0, 'Active'],
  [1, 'Archived'],
  [-1, 'All'],
];

export function History() {
  const { t } = useAi();
  const host = useHost();
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const [notice, setNotice] = useState<Notice | null>(null);
  const [text, setText] = useState('');
  const [q, setQ] = useState('');
  const [archived, setArchived] = useState(0);
  const [limit, setLimit] = useState(PAGE);
  const [open, setOpen] = useState<number | null>(null);
  const [renaming, setRenaming] = useState<Chat | null>(null);
  const [title, setTitle] = useState('');
  const [asking, setAsking] = useState<Chat | null>(null);
  const vars = { q, archived, limit, offset: 0 };
  const list = useFlxQuery(FlxAiChats, vars);
  const refresh = () => qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxAiChats, vars) });
  const fail = (e: unknown) => setNotice({ kind: 'error', text: errText(e, t('The action could not be completed.')) });
  const check = (errors: { message: string }[] | undefined | null) => {
    if (errors?.length) {
      setNotice({ kind: 'error', text: errors.map((e) => e.message).join(' ') });
      return false;
    }
    return true;
  };
  const update = useFlxMutation(FlxAiChatUpdate, {
    onSuccess: (r) => {
      if (check(r.flxAiChatUpdate?.errors)) setRenaming(null);
      void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxAiChat, { id: open ?? 0 }) });
      void refresh();
    },
    onError: fail,
  });
  const del = useFlxMutation(FlxAiChatDelete, {
    onSuccess: (r) => {
      if (check(r.flxAiChatDelete?.errors)) {
        setNotice({ kind: 'success', text: t('Chat deleted.') });
        if (asking && asking.id === open) setOpen(null);
      }
      setAsking(null);
      void refresh();
    },
    onError: fail,
  });

  const adminUrl = host.adminUrl ?? '';
  const chatUrl = (id: number) => `${adminUrl}admin.php?page=flx-ai-chat&flxai_chat=${id}`;
  const busy = update.isPending || del.isPending;

  return (
    <>
      <NoticeBar notice={notice} onClose={() => setNotice(null)} t={t} />
      <p className="flxai-lead">{t('Your earlier chats with the assistant. Search looks in titles and in what was said. Open a chat to read it, or continue it in the chat.')}</p>
      <Card
        title={
          <span className="flxai-card-head">
            {t('Chats')}
            <span className="flxai-card-acts">
              <span className="flxai-seg" role="group" aria-label={t('Show')}>
                {FILTERS.map(([v, label]) => (
                  <button
                    key={v}
                    type="button"
                    className={archived === v ? 'is-on' : ''}
                    onClick={() => {
                      setArchived(v);
                      setLimit(PAGE);
                      setOpen(null);
                    }}
                  >
                    {t(label)}
                  </button>
                ))}
              </span>
            </span>
          </span>
        }
      >
        <form
          className="flxai-hist-search"
          onSubmit={(e) => {
            e.preventDefault();
            setQ(text.trim());
            setLimit(PAGE);
            setOpen(null);
          }}
        >
          <input className="flxai-input" type="search" value={text} placeholder={t('Search chats')} aria-label={t('Search chats')} onChange={(e) => setText(e.target.value)} />
          <button type="submit" className="flxai-btn">
            {t('Search')}
          </button>
          {q && (
            <button
              type="button"
              className="flxai-btn"
              onClick={() => {
                setText('');
                setQ('');
              }}
            >
              {t('Clear')}
            </button>
          )}
        </form>
        {list.isLoading && <Loading />}
        {list.isError && <div className="flxai-notice is-error">{list.error?.message || t('The action could not be completed.')}</div>}
        {list.data && !list.data.flxAiChats.items.length && <p className="flxai-hint">{q ? t('No chats match your search.') : archived === 1 ? t('No archived chats.') : t('No chats yet. Start one from the assistant button or the Chat page.')}</p>}
        {list.data?.flxAiChats.items.map((c) => (
          <article key={c.id} className={'flxai-hist' + (c.archived ? ' is-off' : '') + (open === c.id ? ' is-open' : '')}>
            <div className="flxai-tc-head">
              <div className="flxai-tc-main">
                <div className="flxai-tc-title">
                  <button type="button" className="flxai-link" aria-expanded={open === c.id} onClick={() => setOpen(open === c.id ? null : c.id)}>
                    {c.title || t('New chat')}
                  </button>{' '}
                  {c.status === 'approval' && <Pill kind="warn">{t('Awaiting approval')}</Pill>}
                  {c.status === 'running' && <Pill>{t('Running')}</Pill>}
                  {c.archived && <Pill>{t('Archived')}</Pill>}
                  {c.task && <Pill>{t('Task: %s', c.task)}</Pill>}
                </div>
                <div className="flxai-tc-meta">
                  <span title={c.updated.label}>{c.updated.relative}</span>
                </div>
                {c.snippet && <div className="flxai-hist-snip">{c.snippet}</div>}
              </div>
              <div className="flxai-tc-acts">
                <a className="flxai-btn is-small" href={chatUrl(c.id)}>
                  {t('Continue in chat')}
                </a>
                <button
                  type="button"
                  className="flxai-btn is-small"
                  disabled={busy}
                  onClick={() => {
                    setRenaming(c);
                    setTitle(c.title);
                  }}
                >
                  {t('Rename')}
                </button>
                <button type="button" className="flxai-btn is-small" disabled={busy} onClick={() => update.mutate({ input: { id: c.id, archived: !c.archived } })}>
                  {c.archived ? t('Restore') : t('Archive')}
                </button>
                <button type="button" className="flxai-btn is-small is-danger" disabled={busy} onClick={() => setAsking(c)}>
                  {t('Delete')}
                </button>
              </div>
            </div>
            {open === c.id && <Transcript id={c.id} />}
          </article>
        ))}
        {list.data?.flxAiChats.hasMore && (
          <p>
            <button type="button" className="flxai-btn" disabled={list.isFetching} onClick={() => setLimit(limit + PAGE)}>
              {t('Show more')}
            </button>
          </p>
        )}
      </Card>
      {renaming && (
        <Modal title={t('Rename')} onClose={() => setRenaming(null)}>
          <form
            onSubmit={(e) => {
              e.preventDefault();
              update.mutate({ input: { id: renaming.id, title } });
            }}
          >
            <input className="flxai-input" autoFocus value={title} maxLength={190} aria-label={t('Title')} onChange={(e) => setTitle(e.target.value)} />
            <div className="flxai-appr-acts">
              <button type="button" className="flxai-btn" onClick={() => setRenaming(null)}>
                {t('Cancel')}
              </button>
              <button type="submit" className="flxai-btn is-primary" disabled={update.isPending}>
                {t('Save')}
              </button>
            </div>
          </form>
        </Modal>
      )}
      {asking && (
        <Modal title={t('Delete')} onClose={() => setAsking(null)}>
          <p>{t('Delete the chat "%s" with its files? This cannot be undone.', asking.title || t('New chat'))}</p>
          <div className="flxai-appr-acts">
            <button type="button" className="flxai-btn" onClick={() => setAsking(null)}>
              {t('Cancel')}
            </button>
            <button type="button" className="flxai-btn is-danger" disabled={del.isPending} onClick={() => del.mutate({ input: { id: asking.id } })}>
              {t('Delete')}
            </button>
          </div>
        </Modal>
      )}
    </>
  );
}

function Transcript({ id }: { id: number }) {
  const { t, num } = useAi();
  const q = useFlxQuery(FlxAiChat, { id });
  if (q.isLoading) return <Loading />;
  if (q.isError || !q.data) return <div className="flxai-notice is-error">{q.error?.message || t('The action could not be completed.')}</div>;
  const c = q.data.flxAiChat;
  if (!c.messages.length) return <p className="flxai-hint">{t('This chat is empty.')}</p>;
  return (
    <div className="flxai-tx" role="log" aria-label={t('Transcript')}>
      {c.model && <p className="flxai-hint">{t('Model: %s', c.model)}</p>}
      {c.messages.map((m) => {
        if (m.role === 'tool')
          return (
            <details key={m.index} className={'flxai-tx-tool' + (m.ok === false ? ' is-err' : '')}>
              <summary>
                <code>{m.tool || t('Tool')}</code> {m.ok === false ? <Pill kind="err">{t('Failed')}</Pill> : <Pill kind="ok">{t('Done')}</Pill>}
              </summary>
              <pre>{m.error || m.text}</pre>
            </details>
          );
        return (
          <div key={m.index} className={'flxai-tx-msg is-' + m.role}>
            <div className="flxai-tx-who">
              {m.role === 'user' ? t('You') : t('Assistant')}
              {m.time && <span className="flxai-hint"> · {m.time.label}</span>}
            </div>
            {m.text && <div className="flxai-tx-text">{m.text}</div>}
            {m.tools && <div className="flxai-hint">{t('Used: %s', m.tools)}</div>}
          </div>
        );
      })}
      <p className="flxai-hint">{t('%s messages', num(c.messages.length))}</p>
    </div>
  );
}
