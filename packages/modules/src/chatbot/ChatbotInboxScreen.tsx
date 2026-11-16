// Flux AI Chatbot › Site chats. Same screen in wp-admin (wp-embed) and the FluxWP app; list, chat, reply,
// close and delete go through the Flux AI Chatbot GraphQL operations. It has everything the classic
// inbox has: filters, search, unread and typing marks, details, assistant note, product cards, files.
import { Fragment, useCallback, useEffect, useMemo, useRef, useState, type FormEvent, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { useQueryClient } from '@tanstack/react-query';
import { ArrowLeft, ExternalLink, FileText, Info, MessageCircle, Paperclip, Send, Settings, Trash2, X } from 'lucide-react';
import { createGraphQLClient } from '@fluxwp/graphql';
import { FlxGraphQLProvider, flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import {
  FlxChatbotConversationClose,
  FlxChatbotConversationDelete,
  FlxChatbotConversationList,
  FlxChatbotConversationOpen,
  FlxChatbotConversationReply,
  FlxChatbotConversationTyping,
  FlxChatbotInbox,
} from '@fluxwp/graphql/operations';
import type { FlxChatbotConversationFilter, FlxChatbotConversationRowFragment, FlxChatbotMessageFieldsFragment } from '@fluxwp/graphql/types';
import { Loading, Modal } from '@fluxwp/ui';
import { useHost } from '../host';
import { translator } from './i18n';
import type { Tr } from './fields';
import './chatbot.css';

type Row = FlxChatbotConversationRowFragment;
type Msg = FlxChatbotMessageFieldsFragment;
type Confirm = { text: string; action: string; danger: boolean; run: () => void };

const FILTERS: FlxChatbotConversationFilter[] = ['OPEN', 'BOT', 'CLOSED', 'ALL'];

const initials = (s: string | null): string => {
  const p = (s ?? '').trim().split(/\s+/).filter(Boolean);
  if (!p.length) return '?';
  return ((p[0]?.[0] ?? '') + (p[1]?.[0] ?? '')).toUpperCase();
};
const hue = (id: number): number => (id * 47) % 360;

/** List preview without markup. */
const plain = (s: string): string => s.replace(/\[([^\]]+)\]\([^)]+\)/g, '$1').replace(/[*_~`#]+/g, '').replace(/\s+/g, ' ');

const MARK = /(\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)|https?:\/\/[^\s<]+|\*\*([^*\n]+)\*\*|\*([^*\n]+)\*|_([^_\n]+)_|~([^~\n]+)~|`([^`\n]+)`)/g;

/** WhatsApp-like formatting and links (the assistant's Markdown too), as React nodes: no HTML is injected. */
function renderText(src: string): ReactNode {
  return src.split('\n').map((line, i) => {
    const text = line.replace(/^[ \t]*[-*][ \t]+/, '• ');
    const out: ReactNode[] = [];
    let last = 0;
    for (const m of text.matchAll(MARK)) {
      const at = m.index ?? 0;
      if (at > last) out.push(text.slice(last, at));
      if (m[2] !== undefined && m[3] !== undefined) out.push(<a key={at} href={m[3]} target="_blank" rel="noopener noreferrer">{m[2]}</a>);
      else if (m[4] !== undefined || m[5] !== undefined) out.push(<strong key={at}>{m[4] ?? m[5]}</strong>);
      else if (m[6] !== undefined) out.push(<em key={at}>{m[6]}</em>);
      else if (m[7] !== undefined) out.push(<s key={at}>{m[7]}</s>);
      else if (m[8] !== undefined) out.push(<code key={at}>{m[8]}</code>);
      else out.push(<a key={at} href={m[0]} target="_blank" rel="noopener noreferrer">{m[0]}</a>);
      last = at + m[0].length;
    }
    if (last < text.length) out.push(text.slice(last));
    return (
      <Fragment key={i}>
        {i > 0 && <br />}
        {out}
      </Fragment>
    );
  });
}

export function ChatbotInboxScreen() {
  const host = useHost();
  const client = useMemo(() => createGraphQLClient({ api: host.client }), [host.client]);
  return (
    <FlxGraphQLProvider client={client} scope={host.scope}>
      <Inbox />
    </FlxGraphQLProvider>
  );
}

function statusLabel(t: Tr, s: Row['status']): string {
  switch (s) {
    case 'QUEUED':
      return t('Waiting for an agent');
    case 'AGENT':
      return t('In conversation');
    case 'OFFLINE':
      return t('Left a message');
    case 'CLOSED':
      return t('Closed');
    default:
      return t('With assistant');
  }
}

function Badge({ t, status }: { t: Tr; status: Row['status'] }) {
  return <span className={'flxcb-ib is-' + status.toLowerCase()}>{statusLabel(t, status)}</span>;
}

function Avatar({ c }: { c: Row }) {
  return (
    <span className="flxcb-iav" style={{ ['--h' as string]: hue(c.databaseId) }}>
      {initials(c.name ?? c.email ?? '#')}
      {c.online && <i />}
    </span>
  );
}

function Inbox() {
  const host = useHost();
  const { i18n } = useTranslation();
  const { t } = useMemo(() => translator(i18n.language), [i18n.language]);
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const meta = useFlxQuery(FlxChatbotInbox, {});
  const [filter, setFilter] = useState<FlxChatbotConversationFilter>('OPEN');
  const [search, setSearch] = useState('');
  const [q, setQ] = useState('');
  const [cur, setCur] = useState<number>(() => Number(new URLSearchParams(window.location.search).get('conv')) || 0);
  const [confirm, setConfirm] = useState<Confirm | null>(null);
  const [error, setError] = useState('');

  useEffect(() => {
    const h = window.setTimeout(() => setQ(search.trim()), 300);
    return () => window.clearTimeout(h);
  }, [search]);

  const vars = { filter, search: q || null };
  const list = useFlxQuery(FlxChatbotConversationList, vars, {
    refetchInterval: () => (document.hidden ? 60000 : 15000),
    placeholderData: (prev) => prev,
  });
  const refreshList = useCallback(() => qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxChatbotConversationList) }), [qc, scope]);

  const choose = (id: number) => {
    setCur(id);
    try {
      const u = new URL(window.location.href);
      if (id) u.searchParams.set('conv', String(id));
      else u.searchParams.delete('conv');
      window.history.replaceState(null, '', u.toString());
    } catch {
      /* the address bar is not essential */
    }
  };

  const del = useFlxMutation(FlxChatbotConversationDelete, {
    onSuccess: (r) => {
      const p = r.flxChatbotConversationDelete;
      if (!p || p.errors.length) return setError(p?.errors[0]?.message || t('Something went wrong. Please try again.'));
      choose(0);
      void refreshList();
    },
    onError: (e) => setError(e.message || t('Something went wrong. Please try again.')),
  });

  if (meta.isLoading) return <Loading />;
  if (meta.isError || !meta.data) return <div className="flxcb"><div className="flxcb-notice is-bad">{meta.error?.message || t('Something went wrong. Please try again.')}</div></div>;
  const m = meta.data.flxChatbotInbox;
  const rows = list.data?.flxChatbotConversations.nodes ?? [];

  return (
    <div className="flxcb flxcb-inbox-wrap">
      <div className="flxcb-head">
        <div>
          <h1>{t('Site chats')}</h1>
          <p className="flxcb-lead">{t('Conversations from the chat window on your site. Chats the assistant answered are here too; visitors asking for an agent are on top.')}</p>
        </div>
        <div className="flxcb-head__actions">
          {host.classicUrl && (
            <a className="button" href={host.classicUrl}>
              {t('Classic view')}
            </a>
          )}
          {m.fluxChatUrl && (
            <a className="button" href={m.fluxChatUrl}>
              <MessageCircle size={14} />
              {t('Flux Chat')}
            </a>
          )}
          {m.settingsUrl && (
            <a className="button" href={m.settingsUrl}>
              <Settings size={14} />
              {t('Settings')}
            </a>
          )}
        </div>
      </div>
      {error && (
        <div className="flxcb-notice is-bad" role="alert">
          <span>{error}</span>
          <button type="button" className="button-link" onClick={() => setError('')}>
            {t('Dismiss')}
          </button>
        </div>
      )}

      <div className={'flxcb-inbox' + (cur ? ' has-conv' : '')}>
        <aside className="flxcb-ilist">
          <div className="flxcb-ilist__top">
            <div className="flxcb-seg" role="tablist">
              {FILTERS.map((f) => (
                <button key={f} type="button" className={f === filter ? 'is-on' : ''} onClick={() => setFilter(f)}>
                  {f === 'OPEN' ? t('Open') : f === 'BOT' ? t('With assistant') : f === 'CLOSED' ? t('Closed') : t('All')}
                </button>
              ))}
            </div>
            <input type="search" className="flxcb-isearch" value={search} placeholder={t('Search name, email, phone or page')} onChange={(e) => setSearch(e.target.value)} />
          </div>
          <ul className="flxcb-iitems">
            {!rows.length && <li className="flxcb-iitems__empty">{list.isLoading ? t('Loading…') : t('No chats in this view.')}</li>}
            {rows.map((c) => {
              const last = c.lastMessage ? (c.lastMessage.role === 'AGENT' ? t('You') + ': ' : c.lastMessage.role === 'BOT' ? '✦ ' : '') + plain(c.lastMessage.body) : '';
              return (
                <li key={c.id}>
                  <button type="button" className={'flxcb-iitem' + (c.databaseId === cur ? ' is-on' : '') + (c.unread ? ' is-unread' : '')} onClick={() => choose(c.databaseId)}>
                    <Avatar c={c} />
                    <span className="flxcb-iitem__b">
                      <span className="flxcb-iitem__r">
                        <strong>{c.title}</strong>
                        <small>{c.lastActivity?.relative}</small>
                      </span>
                      <span className="flxcb-iitem__r">
                        <span className="flxcb-iitem__last">{c.typing ? <em>{t('typing…')}</em> : last}</span>
                        {c.unread > 0 && <b>{c.unread}</b>}
                      </span>
                      <span className="flxcb-iitem__r">
                        <Badge t={t} status={c.status} />
                        {c.pageTitle && <small className="flxcb-iitem__pg">{c.pageTitle}</small>}
                      </span>
                    </span>
                  </button>
                </li>
              );
            })}
          </ul>
        </aside>

        <section className="flxcb-iconv">
          {cur ? (
            <Conversation
              key={cur}
              id={cur}
              t={t}
              canDelete={m.canDelete}
              maxMb={m.maxUploadMb}
              agentName={m.agentName}
              onBack={() => choose(0)}
              onChanged={() => void refreshList()}
              onDelete={(id) =>
                setConfirm({
                  text: t('Delete this chat and its files permanently?'),
                  action: t('Delete'),
                  danger: true,
                  run: () => del.mutate({ input: { id: String(id) } }),
                })
              }
              ask={setConfirm}
              fail={setError}
            />
          ) : (
            <div className="flxcb-iempty">
              <MessageCircle size={28} />
              <p>{t('Pick a chat on the left.')}</p>
            </div>
          )}
        </section>
      </div>

      {confirm && (
        <Modal title={confirm.action} onClose={() => setConfirm(null)}>
          <p className="flxcb-confirm">{confirm.text}</p>
          <div className="flxcb-modal-actions">
            <button type="button" className="button" onClick={() => setConfirm(null)}>
              {t('Cancel')}
            </button>
            <button
              type="button"
              className={'button ' + (confirm.danger ? 'is-danger' : 'button-primary')}
              onClick={() => {
                confirm.run();
                setConfirm(null);
              }}
            >
              {confirm.action}
            </button>
          </div>
        </Modal>
      )}
    </div>
  );
}

interface ConvState {
  conv: Row | null;
  msgs: Msg[];
  last: number;
  loading: boolean;
  error: string;
}

/** One chat: first load, then incremental polling (4 s while someone is talking, slower otherwise, slowest in a hidden tab). */
function useConversation(id: number, onChanged: () => void) {
  const { client } = useGraphQL();
  const [s, setS] = useState<ConvState>({ conv: null, msgs: [], last: 0, loading: true, error: '' });
  const lastRef = useRef(0);
  const timer = useRef(0);
  const alive = useRef(true);
  const status = useRef<Row['status'] | null>(null);

  const pull = useCallback(
    async (first: boolean) => {
      window.clearTimeout(timer.current);
      try {
        let more = true;
        let guard = 0;
        let conv: Row | null = null;
        const got: Msg[] = [];
        while (more && guard++ < 10) {
          const r = await client.request(FlxChatbotConversationOpen, { id: String(id), afterId: lastRef.current, peek: false });
          conv = r.flxChatbotConversation;
          const page = r.flxChatbotMessages;
          got.push(...page.nodes);
          lastRef.current = Math.max(lastRef.current, page.lastId);
          more = page.hasMore;
        }
        if (!alive.current) return;
        if (conv) status.current = conv.status;
        setS((o) => {
          const seen = new globalThis.Set(o.msgs.map((x) => x.databaseId));
          const fresh = got.filter((x) => !seen.has(x.databaseId));
          return { conv: conv ?? o.conv, msgs: first ? got : fresh.length ? [...o.msgs, ...fresh] : o.msgs, last: lastRef.current, loading: false, error: '' };
        });
        if (first || got.length) onChanged();
      } catch (e) {
        if (alive.current && first) setS((o) => ({ ...o, loading: false, error: e instanceof Error ? e.message : '' }));
      }
      if (alive.current) {
        const live = status.current === 'QUEUED' || status.current === 'AGENT';
        timer.current = window.setTimeout(() => void pull(false), document.hidden ? 30000 : live ? 4000 : 10000);
      }
    },
    [client, id, onChanged],
  );

  useEffect(() => {
    alive.current = true;
    lastRef.current = 0;
    void pull(true);
    const vis = () => {
      if (!document.hidden) void pull(false);
    };
    document.addEventListener('visibilitychange', vis);
    return () => {
      alive.current = false;
      window.clearTimeout(timer.current);
      document.removeEventListener('visibilitychange', vis);
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id]);

  return { s, setS, pull };
}

function Conversation({
  id,
  t,
  canDelete,
  maxMb,
  agentName,
  onBack,
  onChanged,
  onDelete,
  ask,
  fail,
}: {
  id: number;
  t: Tr;
  canDelete: boolean;
  maxMb: number;
  agentName: string;
  onBack: () => void;
  onChanged: () => void;
  onDelete: (id: number) => void;
  ask: (c: Confirm) => void;
  fail: (m: string) => void;
}) {
  const host = useHost();
  const { s, setS, pull } = useConversation(id, onChanged);
  const [text, setText] = useState('');
  const [file, setFile] = useState<File | null>(null);
  const [busy, setBusy] = useState(false);
  const box = useRef<HTMLDivElement | null>(null);
  const typed = useRef(0);
  const near = useRef(true);

  const reply = useFlxMutation(FlxChatbotConversationReply);
  const close = useFlxMutation(FlxChatbotConversationClose);
  const typing = useFlxMutation(FlxChatbotConversationTyping);
  const c = s.conv;

  useEffect(() => {
    const el = box.current;
    if (el && near.current) el.scrollTop = el.scrollHeight;
  }, [s.msgs.length, c?.status]);

  if (s.loading) return <div className="flxcb-iempty"><p>{t('Loading…')}</p></div>;
  if (!c) return <div className="flxcb-iempty"><p>{s.error || t('Something went wrong. Please try again.')}</p></div>;

  const send = async (e: FormEvent) => {
    e.preventDefault();
    const body = text.trim();
    if ((!body && !file) || busy) return;
    if (file && file.size > maxMb * 1024 * 1024) return fail(t('The file is larger than %s MB.', maxMb));
    setBusy(true);
    try {
      let uploadId: string | null = null;
      if (file) {
        const fd = new FormData();
        fd.append('file', file);
        const up = await host.client<{ uploadId: string }>('flx/v1/upload', { method: 'POST', query: { context: 'flx-chatbot/reply' }, formData: fd });
        uploadId = up.uploadId;
      }
      const r = await reply.mutateAsync({ input: { id: String(id), text: body, uploadId } });
      const p = r.flxChatbotConversationReply;
      if (!p || p.errors.length) throw new Error(p?.errors[0]?.message);
      setText('');
      setFile(null);
      near.current = true;
      setS((o) => ({ ...o, conv: p.conversation ?? o.conv }));
      await pull(false);
      onChanged();
    } catch (err) {
      fail(err instanceof Error && err.message ? err.message : t('Something went wrong. Please try again.'));
    } finally {
      setBusy(false);
    }
  };

  const typedNow = () => {
    if (typed.current) return;
    typing.mutate({ input: { id: String(id) } });
    typed.current = window.setTimeout(() => {
      typed.current = 0;
    }, 5000);
  };

  const rows: Array<[string, ReactNode]> = [];
  if (c.email) rows.push([t('Email'), <a key="e" href={'mailto:' + c.email}>{c.email}</a>]);
  if (c.phone) rows.push([t('Phone'), <a key="p" href={'tel:' + c.phone}>{c.phone}</a>]);
  if (c.pageUrl) rows.push([t('Page'), <a key="g" href={c.pageUrl} target="_blank" rel="noopener noreferrer">{c.pageTitle || c.pageUrl}</a>]);
  if (c.userId) rows.push([t('Member'), '#' + c.userId]);
  if (c.agentName) rows.push([t('Agent'), c.agentName]);
  if (c.orderId) rows.push([t('Order'), c.orderUrl ? <a key="o" href={c.orderUrl}>#{c.orderId}</a> : '#' + c.orderId]);
  if (c.rating)
    rows.push([
      t('Rating'),
      <Fragment key="r">
        <span className="flxcb-istars">
          {'★'.repeat(c.rating)}
          <i>{'★'.repeat(5 - c.rating)}</i>
        </span>
        {c.ratingNote && <q>{c.ratingNote}</q>}
      </Fragment>,
    ]);
  if (c.isLead) rows.push([t('Lead'), '✓']);

  return (
    <>
      <header className="flxcb-ihead">
        <button type="button" className="flxcb-ibtn flxcb-ihead__back" onClick={onBack} title={t('Back to list')}>
          <ArrowLeft size={18} />
        </button>
        <Avatar c={c} />
        <span className="flxcb-ihead__t">
          <strong>{c.title}</strong>
          <small>
            <Badge t={t} status={c.status} /> <span className={c.typing ? 'is-typing' : ''}>{c.typing ? t('typing…') : c.online ? t('On the page') : t('Left')}</span>
          </small>
        </span>
        <span className="flxcb-ihead__a">
          {c.fluxChatUrl && (
            <a className="button" href={c.fluxChatUrl}>
              <ExternalLink size={14} />
              {t('Open in Flux Chat')}
            </a>
          )}
          {c.status !== 'CLOSED' && (
            <button
              type="button"
              className="button"
              onClick={() =>
                ask({
                  text: t('Close the chat? It reopens if the visitor writes again.'),
                  action: t('Close chat'),
                  danger: false,
                  run: () =>
                    close.mutate(
                      { input: { id: String(id) } },
                      {
                        onSuccess: (r) => {
                          const p = r.flxChatbotConversationClose;
                          if (!p || p.errors.length) return fail(p?.errors[0]?.message || t('Something went wrong. Please try again.'));
                          setS((o) => ({ ...o, conv: p.conversation ?? o.conv }));
                          void pull(false);
                          onChanged();
                        },
                        onError: (e) => fail(e.message),
                      },
                    ),
                })
              }
            >
              <X size={14} />
              {t('Close chat')}
            </button>
          )}
          {canDelete && (
            <button type="button" className="flxcb-ibtn is-bad" title={t('Delete')} onClick={() => onDelete(id)}>
              <Trash2 size={16} />
            </button>
          )}
        </span>
      </header>

      <div className="flxcb-ibody">
        <div
          className="flxcb-imsgs"
          ref={box}
          onScroll={(e) => {
            const el = e.currentTarget;
            near.current = el.scrollHeight - el.scrollTop - el.clientHeight < 120;
          }}
        >
          {s.msgs.map((m) => (
            <Message key={m.databaseId} t={t} m={m} visitor={c.name} />
          ))}
        </div>
        <aside className="flxcb-iinfo">
          <h3>{t('Details')}</h3>
          <dl>
            {rows.map(([k, v]) => (
              <Fragment key={k}>
                <dt>{k}</dt>
                <dd>{v}</dd>
              </Fragment>
            ))}
          </dl>
          {c.summary && (
            <div className="flxcb-isum">
              <strong>
                <Info size={14} />
                {t("Assistant's note")}
              </strong>
              <p>{renderText(c.summary)}</p>
            </div>
          )}
          {c.fluxChatUrl && <p className="flxcb-muted">{t('This chat is also in the Flux Chat inbox; replies from either place reach the visitor.')}</p>}
        </aside>
      </div>

      <form className={'flxcb-icomp' + (busy ? ' is-busy' : '')} onSubmit={(e) => void send(e)}>
        {file && (
          <div className="flxcb-icomp__file">
            <FileText size={14} />
            <span>{file.name}</span>
            <button type="button" className="flxcb-ibtn" onClick={() => setFile(null)} aria-label={t('Remove')}>
              <X size={14} />
            </button>
          </div>
        )}
        <div className="flxcb-icomp__row">
          <label className="flxcb-ibtn" title={t('Attach file')}>
            <Paperclip size={18} />
            <input
              type="file"
              hidden
              onChange={(e) => {
                setFile(e.target.files?.[0] ?? null);
                e.target.value = '';
              }}
            />
          </label>
          <textarea
            rows={1}
            value={text}
            aria-label={agentName}
            placeholder={t('Type your reply… (Enter sends, Shift+Enter for a new line)')}
            onChange={(e) => {
              setText(e.target.value);
              e.target.style.height = 'auto';
              e.target.style.height = Math.min(160, e.target.scrollHeight) + 'px';
              typedNow();
            }}
            onKeyDown={(e) => {
              if (e.key === 'Enter' && !e.shiftKey && !e.nativeEvent.isComposing) {
                e.preventDefault();
                e.currentTarget.form?.requestSubmit();
              }
            }}
          />
          <button type="submit" className="button button-primary" disabled={busy}>
            <Send size={16} />
            <span>{t('Send')}</span>
          </button>
        </div>
      </form>
    </>
  );
}

function Message({ t, m, visitor }: { t: Tr; m: Msg; visitor: string | null }) {
  if (m.role === 'SYSTEM')
    return (
      <div className="flxcb-isys">
        {m.body}
        <time>{m.sentAt.label}</time>
      </div>
    );
  const who = m.role === 'VISITOR' ? visitor || t('Visitor') : m.author;
  return (
    <div className={'flxcb-im is-' + m.role.toLowerCase()}>
      <div className="flxcb-im__b">
        {m.file &&
          m.file.url &&
          (/^image\//.test(m.file.mime) ? (
            <a href={m.file.url} target="_blank" rel="noopener noreferrer" className="flxcb-iimg">
              <img src={m.file.url} alt={m.file.name} loading="lazy" />
            </a>
          ) : (
            <a href={m.file.url} target="_blank" rel="noopener noreferrer" className="flxcb-ifile">
              <FileText size={16} />
              {m.file.name}
            </a>
          ))}
        {m.body && <div className="flxcb-itx">{renderText(m.body)}</div>}
        {m.cards.length > 0 && (
          <div className="flxcb-icards">
            <small>{t('Suggested products')}</small>
            {m.cards.map((p) => (
              <a key={p.url} href={p.url} target="_blank" rel="noopener noreferrer">
                {p.image && <img src={p.image} alt="" />}
                <span>
                  {p.title}
                  <b>{p.price}</b>
                </span>
              </a>
            ))}
          </div>
        )}
      </div>
      <div className="flxcb-im__m">
        {m.role === 'BOT' ? '✦ ' : ''}
        {who} · {m.sentAt.label}
      </div>
    </div>
  );
}
