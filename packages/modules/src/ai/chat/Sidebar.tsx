// Chat list beside the conversation: search, active / archived, running and approval markers, rename, archive, delete.
import { useQueryClient } from '@tanstack/react-query';
import { Archive, ArchiveRestore, Clock, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { FlxAiChatDelete, FlxAiChatUpdate, FlxAiChats } from '@fluxwp/graphql/operations';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { Loading, Modal } from '@fluxwp/ui';
import { useAi } from '../common';

const PAGE = 40;

export function Sidebar({ current, locked, onOpen, onNew, onGone, searchRef, onError }: { current: number; locked: boolean; onOpen: (id: number) => void; onNew: () => void; onGone: (id: number) => void; searchRef: { current: HTMLInputElement | null }; onError: (m: string) => void }) {
  const { t } = useAi();
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const [text, setText] = useState('');
  const [q, setQ] = useState('');
  const [archived, setArchived] = useState(0);
  const [limit, setLimit] = useState(PAGE);
  const [renaming, setRenaming] = useState<{ id: number; title: string } | null>(null);
  const [asking, setAsking] = useState<{ id: number; title: string } | null>(null);
  const vars = { q, archived, limit, offset: 0 };
  const list = useFlxQuery(FlxAiChats, vars, { refetchInterval: 8000 });
  const fail = (e: unknown) => onError(e instanceof Error ? e.message : t('The action could not be completed.'));
  const update = useFlxMutation(FlxAiChatUpdate, {
    onSuccess: (r) => {
      if (r.flxAiChatUpdate?.errors?.length) onError(r.flxAiChatUpdate.errors.map((e) => e.message).join(' '));
      setRenaming(null);
      void qc.invalidateQueries({ queryKey: [scope] });
    },
    onError: fail,
  });
  const del = useFlxMutation(FlxAiChatDelete, {
    onSuccess: (r) => {
      if (r.flxAiChatDelete?.errors?.length) onError(r.flxAiChatDelete.errors.map((e) => e.message).join(' '));
      else if (asking) onGone(asking.id);
      setAsking(null);
      void qc.invalidateQueries({ queryKey: [scope] });
    },
    onError: fail,
  });
  const items = list.data?.flxAiChats.items ?? [];
  return (
    <aside className="flxai-side" aria-label={t('Chats')}>
      <div className="flxai-side-h">
        <button type="button" className="flxai-btn is-primary" disabled={locked} onClick={onNew}>
          <Plus size={14} /> {t('New chat')}
        </button>
      </div>
      <form
        className="flxai-side-s"
        onSubmit={(e) => {
          e.preventDefault();
          setQ(text.trim());
          setLimit(PAGE);
        }}
      >
        <input
          ref={(el) => {
            searchRef.current = el;
          }}
          className="flxai-input"
          type="search"
          value={text}
          placeholder={t('Search chats')}
          aria-label={t('Search chats')}
          onChange={(e) => {
            setText(e.target.value);
            if (!e.target.value) setQ('');
          }}
        />
      </form>
      <span className="flxai-seg" role="group" aria-label={t('Show')}>
        {(
          [
            [0, 'Active'],
            [1, 'Archived'],
          ] as const
        ).map(([v, label]) => (
          <button key={v} type="button" className={archived === v ? 'is-on' : ''} onClick={() => (setArchived(v), setLimit(PAGE))}>
            {t(label)}
          </button>
        ))}
      </span>
      <div className="flxai-side-l">
        {list.isLoading && <Loading />}
        {list.data && !items.length && <p className="flxai-hint">{q ? t('No chats match your search.') : archived ? t('No archived chats.') : t('No chats yet.')}</p>}
        {items.map((c) => (
          <div key={c.id} className={'flxai-si' + (c.id === current ? ' is-cur' : '')}>
            <button type="button" className="flxai-si-main" disabled={locked && c.id !== current} onClick={() => onOpen(c.id)}>
              <span className="flxai-si-t">
                {c.status === 'running' && <span className="flxai-spin" title={t('Running')} />}
                {c.status === 'approval' && <span className="flxai-dot" title={t('Awaiting approval')} />}
                {c.task && <Clock size={12} />}
                <span>{c.title || t('New chat')}</span>
              </span>
              <small>{c.snippet || c.updated.relative}</small>
            </button>
            <span className="flxai-si-a">
              <button type="button" title={t('Rename')} aria-label={t('Rename')} onClick={() => setRenaming({ id: c.id, title: c.title })}>
                <Pencil size={13} />
              </button>
              <button type="button" title={c.archived ? t('Restore') : t('Archive')} aria-label={c.archived ? t('Restore') : t('Archive')} onClick={() => update.mutate({ input: { id: c.id, archived: !c.archived } })}>
                {c.archived ? <ArchiveRestore size={13} /> : <Archive size={13} />}
              </button>
              <button type="button" title={t('Delete')} aria-label={t('Delete')} disabled={locked && c.id === current} onClick={() => setAsking({ id: c.id, title: c.title })}>
                <Trash2 size={13} />
              </button>
            </span>
          </div>
        ))}
        {list.data?.flxAiChats.hasMore && (
          <button type="button" className="flxai-btn" disabled={list.isFetching} onClick={() => setLimit(limit + PAGE)}>
            {t('Show more')}
          </button>
        )}
      </div>
      {renaming && (
        <Modal title={t('Rename')} onClose={() => setRenaming(null)}>
          <form
            onSubmit={(e) => {
              e.preventDefault();
              update.mutate({ input: { id: renaming.id, title: renaming.title } });
            }}
          >
            <input className="flxai-input" autoFocus value={renaming.title} maxLength={190} aria-label={t('Title')} onChange={(e) => setRenaming({ ...renaming, title: e.target.value })} />
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
    </aside>
  );
}
