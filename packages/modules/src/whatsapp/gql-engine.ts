// WhatsApp screen state. Same shape as the runtime engine, read and written through Flux Chat GraphQL.
// Polls at 10 seconds or slower and does not poll while the tab is hidden.
import { createStore, type StoreApi } from 'zustand/vanilla';
import {
  chatMatches, mergeMessages, sortChats,
  type ApiClient, type FlxcBootstrap, type FlxcChat, type FlxcChatPatch, type FlxcConnection,
  type FlxcDeleteScope, type FlxcDir, type FlxcFilter, type FlxcMessage, type FlxcMsgType, type FlxcSection, type FlxcSendArgs, type FlxcStatus, type FlxcSync,
} from '@fluxwp/core';
import { createGraphQLClient, FlxGraphQLError, type GraphQLClient } from '@fluxwp/graphql';
import {
  FlxChatBotSet, FlxChatCheckNumber, FlxChatCreate, FlxChatInbox, FlxChatLinkPreview, FlxChatList, FlxChatMarkRead,
  FlxChatMessageDelete, FlxChatMessageReact, FlxChatMessageRetry, FlxChatMessages, FlxChatMessageSend, FlxChatOpen,
  FlxChatReplyUse, FlxChatSuggest, FlxChatTyping, FlxChatUpdate, FlxChatUpdates,
} from '@fluxwp/graphql/operations';
import { browserPorts, type ModulePorts } from '@fluxwp/runtime/src/modules/ports';
import { feedPoll, type RealtimeFeed } from '@fluxwp/runtime/src/feed';

export interface ChatMsgs {
  list: FlxcMessage[];
  more: boolean;
  loaded: boolean;
  loading: boolean;
  note: string;
}

export interface WaState {
  boot: FlxcBootstrap | null;
  bootError: string;
  rev: number;
  chats: Record<number, FlxcChat>;
  order: number[];
  listLoading: boolean;
  listMore: boolean;
  filter: FlxcFilter;
  label: string;
  channel: string;
  q: string;
  active: number | null;
  msgs: Record<number, ChatMsgs>;
  fresh: Record<number, number>;
  conns: FlxcConnection[];
  unread: number;
  offline: '' | 'net' | 'auth';
}

export interface EngineOptions {
  client: ApiClient;
  onNonce?: (n: string) => void;
  onUnreadChange?: (n: number) => void;
  onIncoming?: (items: FlxcSync['incoming']) => void;
  /** Server poll hint, seconds. Never below 10. */
  poll?: number;
  ports?: ModulePorts;
  /** Live signals (Docker sites): a "chat." signal syncs at once and polling slows down to a safety net. */
  realtime?: RealtimeFeed;
}

const PAGE = 50;
const FILTERS: Record<FlxcFilter, 'ALL' | 'UNREAD' | 'MINE' | 'UNASSIGNED' | 'AI' | 'CLOSED' | 'ANY'> = {
  all: 'ALL', unread: 'UNREAD', mine: 'MINE', unassigned: 'UNASSIGNED', ai: 'AI', closed: 'CLOSED', any: 'ANY',
};
const emptyMsgs = (): ChatMsgs => ({ list: [], more: true, loaded: false, loading: false, note: '' });
const num = (s: string): number => Number(s);
const low = (s: string): string => s.toLowerCase();

type GConn = { id: string; label: string; color: string; state: string };
type GLast = { dir: string; type: string; body: string; status: string | null; user: string };
type GChat = {
  databaseId: number; connectionId: string; jid: string; phoneDigits: string; phoneFmt: string; channel: string;
  title: string; name: string; pushName: string; avatarUrl: string; labels: string[]; notes: string; status: string;
  assignedTo: number; unread: number; pinned: boolean; lastMs: string; lastInMs: string; group: boolean; typing: number;
  rev: string; createdMs: string; unknownName: boolean; blocked: boolean; optOut: boolean; bot: string | null;
  botBusy: boolean; ai: boolean; staffUser?: number | null; lastMessage: GLast | null;
};
type GMsg = {
  databaseId: number; chatId: number; dir: string; type: string; body: string; status: string | null; error: string;
  author: string; authorId: number; source: string; sender: string; ts: string; rev: string; edited: boolean; deleted: boolean;
  media: { ptt: boolean; ready: boolean; secs: number; file: { url: string | null; name: string; mime: string; size: number } } | null;
  quoted: { databaseId: number; dir: string; type: string; body: string; author: string } | null;
  reactions: { side: string; emoji: string }[];
};

const connOf = (c: GConn): FlxcConnection => ({ id: c.id, label: c.label, color: c.color, state: c.state, at: 0 });

function chatOf(c: GChat): FlxcChat {
  const last = c.lastMessage;
  return {
    id: c.databaseId, conn: c.connectionId, jid: c.jid, phone: c.phoneDigits, phoneFmt: c.phoneFmt, channel: c.channel,
    title: c.title, name: c.name, pushName: c.pushName, avatar: c.avatarUrl, avatarAt: 0, labels: [...c.labels], notes: c.notes,
    status: low(c.status) === 'closed' ? 'closed' : 'open', assigned: c.assignedTo, unread: c.unread, pinned: c.pinned ? 1 : 0,
    lastTs: num(c.lastMs), lastInTs: num(c.lastInMs), group: c.group, typing: c.typing, rev: num(c.rev), created: num(c.createdMs),
    last: last ? { dir: low(last.dir) as FlxcDir, type: last.type as FlxcMsgType, body: last.body, status: (last.status ? low(last.status) : '') as FlxcStatus, user: last.user } : null,
    unknownName: c.unknownName, meta: c.blocked ? { blocked: 1 } : {}, bot: (c.bot ? low(c.bot) : '') as FlxcChat['bot'],
    botBusy: c.botBusy ? 1 : 0, ai: c.ai ? 1 : 0, optout: c.optOut ? 1 : 0, staffUser: c.staffUser ?? 0,
  };
}

function msgOf(m: GMsg): FlxcMessage {
  const react: Record<string, string> = {};
  for (const r of m.reactions) react[r.side] = r.emoji;
  return {
    id: m.databaseId, chat: m.chatId, wa: '', dir: low(m.dir) as FlxcDir, type: m.type as FlxcMsgType, body: m.body,
    status: (m.status ? low(m.status) : '') as FlxcStatus, error: m.error, user: m.author, userId: m.authorId, source: m.source,
    ts: num(m.ts), rev: num(m.rev), react, edited: m.edited, sender: m.sender, deleted: m.deleted || undefined,
    media: m.media ? { url: m.media.file.url ?? '', mime: m.media.file.mime, name: m.media.file.name, size: m.media.file.size, ready: m.media.ready, ptt: m.media.ptt, secs: m.media.secs, w: 0, h: 0, thumb: '' } : undefined,
    quoted: m.quoted ? { id: m.quoted.databaseId, dir: low(m.quoted.dir) as FlxcDir, type: m.quoted.type as FlxcMsgType, body: m.quoted.body, user: m.quoted.author } : undefined,
  };
}

export class WaEngine {
  readonly store: StoreApi<WaState>;
  readonly api: {
    checkNumber: (phone: string, conn?: string) => Promise<{ valid?: boolean; phone?: string; phoneFmt?: string; exists?: boolean | null; chat?: number; name?: string }>;
    newChat: (p: { phone: string; name?: string; conn?: string; check?: boolean }) => Promise<{ chat: FlxcChat }>;
    chat: (id: number) => Promise<{ chat: FlxcChat; sections: FlxcSection[] }>;
    linkPreview: (url: string) => Promise<{ ok: boolean; url: string; title?: string; desc?: string; image?: string; site?: string; domain?: string }>;
    useReply: (id: number, chatId: number) => Promise<{ text: string }>;
    ai: (chatId: number, o: { fresh?: boolean }) => Promise<{ text: string }>;
  };
  private readonly gql: GraphQLClient;
  private timer: ReturnType<typeof setTimeout> | null = null;
  private fails = 0;
  private syncing = false;
  private stopped = false;
  private seq = 0;
  private listReq = 0;
  private listCursor: string | null = null;
  private since = '0';
  private afterChat = 0;
  private lastTyping = 0;
  private readonly pendingArgs = new Map<number, FlxcSendArgs>();
  private readonly ports: ModulePorts;
  private unvis: (() => void) | null = null;
  private unrt: (() => void)[] = [];
  private again = false;

  constructor(private o: EngineOptions) {
    this.ports = o.ports ?? browserPorts();
    this.gql = createGraphQLClient({ api: o.client });
    this.store = createStore<WaState>(() => ({
      boot: null, bootError: '', rev: 0, chats: {}, order: [], listLoading: true, listMore: false,
      filter: 'all', label: '', channel: '', q: '', active: null, msgs: {}, fresh: {}, conns: [], unread: 0, offline: '',
    }));
    this.api = {
      checkNumber: async (phone, conn) => {
        const r = await this.gql.request(FlxChatCheckNumber, { phone, conn: conn ?? null });
        const c = r.flxChatCheckNumber;
        return { valid: c.valid, phone: c.phone, phoneFmt: c.phoneFmt, exists: c.exists, chat: c.chatId, name: c.name };
      },
      newChat: async (p) => {
        const r = await this.gql.request(FlxChatCreate, { input: { phone: p.phone, name: p.name ?? null, conn: p.conn ?? null, check: p.check ?? null } });
        const err = r.flxChatCreate?.errors[0];
        if (!r.flxChatCreate?.chat || err) throw new Error(err?.message || 'Could not open the chat.');
        return { chat: chatOf(r.flxChatCreate.chat) };
      },
      chat: async (id) => {
        const r = await this.gql.request(FlxChatOpen, { id: String(id) });
        if (!r.flxChat) throw new Error('Chat not found.');
        const sections: FlxcSection[] = r.flxChat.sections.map((s) => ({
          id: s.id, title: s.title, items: s.items.map((i) => ({ label: i.label, sub: i.sub, url: i.url })),
        }));
        return { chat: chatOf(r.flxChat), sections };
      },
      linkPreview: async (url) => {
        const r = await this.gql.request(FlxChatLinkPreview, { url });
        const p = r.flxChatLinkPreview;
        return p ? { ok: true, url: p.url, title: p.title, desc: p.desc, image: p.image, site: p.site, domain: p.domain } : { ok: false, url };
      },
      useReply: async (id, chatId) => {
        const r = await this.gql.request(FlxChatReplyUse, { input: { id, chatId: String(chatId) } });
        const err = r.flxChatReplyUse?.errors[0];
        if (err) throw new Error(err.message);
        return { text: r.flxChatReplyUse?.text ?? '' };
      },
      ai: async (chatId, opts) => {
        const r = await this.gql.request(FlxChatSuggest, { input: { id: String(chatId), fresh: opts.fresh ?? null, hint: null } });
        const err = r.flxChatSuggest?.errors[0];
        if (err) throw new Error(err.message);
        return { text: r.flxChatSuggest?.text ?? '' };
      },
    };
  }

  get s() {
    return this.store.getState();
  }
  private set(p: Partial<WaState> | ((s: WaState) => Partial<WaState>)) {
    this.store.setState(p as never);
  }

  async start() {
    this.stopped = false;
    const rt = this.o.realtime;
    if (rt) {
      this.unrt = [
        rt.on('chat.', () => (this.syncing ? (this.again = true) : this.schedule(0))),
        // Connection lost: go back to the normal poll at once instead of waiting out the safety net.
        rt.onLive((live) => (live ? undefined : this.schedule(500))),
      ];
    }
    this.unvis = this.ports.onVisibility((visible) => {
      if (!visible) {
        if (this.timer) clearTimeout(this.timer);
        this.timer = null;
        return;
      }
      this.schedule(150);
      this.markRead();
    });
    try {
      const r = await this.gql.request(FlxChatInbox, {});
      const b = r.flxChatInbox;
      const boot: FlxcBootstrap = {
        me: { id: b.me.databaseId, name: b.me.name, manage: b.me.manage },
        agents: b.agents.map((a) => ({ id: a.databaseId, name: a.name })),
        labels: b.labels.map((l) => ({ id: l.id, name: l.name, color: l.color })),
        connections: b.connections.map(connOf),
        replies: b.replies.map((q) => ({ id: q.databaseId, shortcut: q.shortcut, title: q.title, body: q.body, category: q.category, uses: q.uses })),
        unread: b.unread, ai: b.ai ? { id: 'ai', label: b.ai, auto: false } : null, bot: b.bot, records: {}, rev: num(b.rev),
        staff: b.staff.map((a) => ({ id: a.databaseId, name: a.name })), chips: b.chips,
      };
      this.since = b.rev;
      this.set({ boot, rev: boot.rev, conns: boot.connections, unread: b.unread });
      this.o.onUnreadChange?.(b.unread);
      await this.loadChats();
    } catch (e) {
      this.set({ bootError: (e as Error).message, listLoading: false });
      return;
    }
    this.schedule(this.interval());
  }

  stop() {
    this.stopped = true;
    if (this.timer) clearTimeout(this.timer);
    this.unvis?.();
    this.unvis = null;
    this.unrt.forEach((f) => f());
    this.unrt = [];
  }

  async loadChats(more = false) {
    const req = ++this.listReq;
    const { filter, q, label, channel } = this.s;
    this.set({ listLoading: true });
    try {
      const r = await this.gql.request(FlxChatList, {
        filter: FILTERS[filter], search: q || null, label: label || null, channel: channel || null,
        first: PAGE, after: more ? this.listCursor : null,
      });
      if (req !== this.listReq) return;
      const nodes = r.flxChats.nodes ?? [];
      this.listCursor = r.flxChats.pageInfo.endCursor ?? null;
      this.set((s) => {
        const chats = { ...s.chats };
        for (const c of nodes) chats[c.databaseId] = chatOf(c);
        const ids = nodes.map((c) => c.databaseId);
        return { chats, order: more ? [...s.order, ...ids.filter((id) => !s.order.includes(id))] : ids, listMore: r.flxChats.pageInfo.hasNextPage, listLoading: false };
      });
    } catch {
      if (req === this.listReq) this.set({ listLoading: false });
    }
  }

  setView(p: Partial<Pick<WaState, 'filter' | 'label' | 'channel' | 'q'>>) {
    this.listCursor = null;
    this.set(p);
    void this.loadChats().catch(() => undefined);
  }

  open(id: number | null) {
    this.set({ active: id, fresh: id ? { ...this.s.fresh, [id]: 0 } : this.s.fresh });
    if (!id) return;
    const m = this.s.msgs[id];
    if (!m || !m.loaded) void this.loadOlder(id);
    if (!this.s.chats[id]) void this.api.chat(id).then((r) => this.upsert(r.chat)).catch(() => undefined);
    this.markRead();
  }

  private patchMsgs(id: number, f: (m: ChatMsgs) => Partial<ChatMsgs>) {
    this.set((s) => {
      const cur = s.msgs[id] ?? emptyMsgs();
      return { msgs: { ...s.msgs, [id]: { ...cur, ...f(cur) } } };
    });
  }

  async loadOlder(id: number) {
    const cur = this.s.msgs[id];
    if (cur?.loading || (cur?.loaded && !cur.more)) return;
    this.patchMsgs(id, () => ({ loading: true }));
    const before = cur?.loaded ? cur.list.find((m) => m.id > 0)?.id : undefined;
    try {
      const r = await this.gql.request(FlxChatMessages, { chatId: String(id), before: before ?? null, first: 40 });
      const page = r.flxChatMessages;
      this.patchMsgs(id, (m) => ({ list: mergeMessages(m.list, page.messages.map(msgOf)), more: page.hasMore, loaded: true, loading: false, note: page.note }));
    } catch (e) {
      this.patchMsgs(id, () => ({ loading: false, loaded: true, more: false, note: (e as Error).message }));
    }
  }

  upsert(c: FlxcChat) {
    this.set((s) => {
      const chats = { ...s.chats, [c.id]: c };
      let order = s.order;
      const inView = order.includes(c.id);
      const match = chatMatches(c, s.filter, s.boot?.me.id ?? 0, s.label, s.q, s.channel);
      if (match && !inView && !s.q) order = [...order, c.id];
      if (!match && inView && c.id !== s.active && !s.q) order = order.filter((x) => x !== c.id);
      if (order !== s.order || inView) order = [...order].sort((a, b) => sortChats(chats[a]!, chats[b]!));
      return { chats, order };
    });
  }

  async update(id: number, patch: FlxcChatPatch) {
    const old = this.s.chats[id];
    if (old) {
      const opt = { ...old };
      if (patch.pinned !== undefined) opt.pinned = patch.pinned ? 1 : 0;
      if (patch.status) opt.status = patch.status;
      if (patch.assigned !== undefined) opt.assigned = patch.assigned;
      if (patch.labels) opt.labels = patch.labels;
      if (patch.unread !== undefined) opt.unread = patch.unread;
      if (patch.notes !== undefined) opt.notes = patch.notes;
      if (patch.name !== undefined) opt.name = patch.name;
      this.upsert(opt);
    }
    try {
      const r = await this.gql.request(FlxChatUpdate, {
        input: {
          id: String(id), name: patch.name ?? null, notes: patch.notes ?? null, labels: patch.labels ?? null,
          status: patch.status === 'closed' ? 'CLOSED' : patch.status === 'open' ? 'OPEN' : null,
          assigned: patch.assigned ?? null, pinned: patch.pinned ?? null, unread: patch.unread ?? null, staffUser: patch.staffUser ?? null,
        },
      });
      const err = r.flxChatUpdate?.errors[0];
      if (!r.flxChatUpdate?.chat || err) throw new Error(err?.message || 'Could not update the chat.');
      this.upsert(chatOf(r.flxChatUpdate.chat));
      this.schedule(400);
    } catch (e) {
      if (old) this.upsert(old);
      throw e;
    }
  }

  async setBot(id: number, on: boolean) {
    const r = await this.gql.request(FlxChatBotSet, { input: { id: String(id), on } });
    const err = r.flxChatBotSet?.errors[0];
    if (!r.flxChatBotSet?.chat || err) throw new Error(err?.message || 'Could not update the assistant.');
    this.upsert(chatOf(r.flxChatBotSet.chat));
    this.schedule(400);
  }

  markRead() {
    const id = this.s.active;
    const c = id ? this.s.chats[id] : null;
    if (!c || !c.unread || !this.ports.visible()) return;
    const was = c.unread;
    this.upsert({ ...c, unread: 0 });
    const unread = Math.max(0, this.s.unread - was);
    this.set({ unread });
    this.o.onUnreadChange?.(unread);
    void this.gql.request(FlxChatMarkRead, { input: { id: String(c.id) } }).catch(() => undefined);
  }

  async send(id: number, args: FlxcSendArgs, retryOf?: number) {
    const me = this.s.boot?.me;
    const tempId = retryOf ?? -(++this.seq + Date.now());
    const quoted = args.quoted ? this.s.msgs[id]?.list.find((m) => m.id === args.quoted) : undefined;
    const temp: FlxcMessage = {
      id: tempId, chat: id, wa: '', dir: args.type === 'note' ? 'note' : 'out', type: args.file ? (args.type ?? 'document') : (args.type ?? 'text'),
      body: args.body ?? '', status: 'pending', error: '', user: me?.name ?? '', userId: me?.id ?? 0, source: args.source ?? 'agent',
      ts: Date.now(), rev: 0, react: {}, edited: false, sender: '', local: true,
      quoted: quoted ? { id: quoted.id, dir: quoted.dir, type: quoted.type, body: quoted.body, user: quoted.user } : undefined,
      media: args.file ? { url: this.ports.objectUrl(args.file), mime: args.file.type, name: args.name ?? '', size: args.file.size, ready: true, ptt: args.type === 'audio', secs: 0, w: 0, h: 0, thumb: '' } : undefined,
    };
    this.pendingArgs.set(tempId, args);
    this.patchMsgs(id, (m) => ({ list: mergeMessages(m.list.filter((x) => x.id !== tempId), [temp]) }));
    try {
      let uploadId: string | null = null;
      if (args.file) {
        const fd = new FormData();
        fd.append('file', args.file, args.name);
        const up = await this.o.client<{ uploadId: string }>('flx/v1/upload', { method: 'POST', query: { context: 'flx-chat/outbound' }, formData: fd });
        uploadId = up.uploadId;
      }
      const r = await this.gql.request(FlxChatMessageSend, {
        input: {
          chatId: String(id), body: args.body ?? null, type: args.type ?? null, uploadId, voice: args.type === 'audio',
          quoted: args.quoted ?? null, source: args.source ?? null, preview: null,
        },
      });
      const err = r.flxChatMessageSend?.errors[0];
      if (!r.flxChatMessageSend?.message || err) throw new Error(err?.message || 'Could not send.');
      const message = msgOf(r.flxChatMessageSend.message);
      this.pendingArgs.delete(tempId);
      this.patchMsgs(id, (m) => ({ list: mergeMessages(m.list.filter((x) => x.id !== tempId), [message]) }));
      const c = this.s.chats[id];
      if (c && args.type !== 'note') {
        this.upsert({
          ...c, lastTs: message.ts, assigned: c.assigned || (args.source !== 'ai' ? me?.id ?? 0 : 0),
          last: { dir: message.dir, type: message.type, body: message.body, status: message.status, user: message.user },
        });
      }
      return message;
    } catch (e) {
      this.patchMsgs(id, (m) => ({ list: m.list.map((x) => (x.id === tempId ? { ...x, status: 'failed', error: (e as Error).message } : x)) }));
      throw e;
    }
  }

  async retry(m: FlxcMessage) {
    if (m.local) {
      const a = this.pendingArgs.get(m.id);
      if (a) return this.send(m.chat, a, m.id);
      return;
    }
    const r = await this.gql.request(FlxChatMessageRetry, { input: { id: m.id } });
    const err = r.flxChatMessageRetry?.errors[0];
    if (!r.flxChatMessageRetry?.message || err) throw new Error(err?.message || 'Could not resend.');
    this.patchMsgs(m.chat, (x) => ({ list: mergeMessages(x.list, [msgOf(r.flxChatMessageRetry!.message!)]) }));
  }

  async react(m: FlxcMessage, emoji: string) {
    const r = await this.gql.request(FlxChatMessageReact, { input: { id: m.id, emoji } });
    const err = r.flxChatMessageReact?.errors[0];
    if (!r.flxChatMessageReact?.message || err) throw new Error(err?.message || 'Could not react.');
    this.patchMsgs(m.chat, (x) => ({ list: mergeMessages(x.list, [msgOf(r.flxChatMessageReact!.message!)]) }));
  }

  async deleteNote(m: FlxcMessage) {
    return this.deleteMessage(m, 'panel');
  }

  async deleteMessage(m: FlxcMessage, scope: FlxcDeleteScope = 'panel') {
    const r = await this.gql.request(FlxChatMessageDelete, { input: { id: m.id, scope: m.dir === 'note' ? 'panel' : scope } });
    const err = r.flxChatMessageDelete?.errors[0];
    if (err) throw new Error(err.message);
    const message = r.flxChatMessageDelete?.message ? msgOf(r.flxChatMessageDelete.message) : null;
    this.patchMsgs(m.chat, (x) => ({
      list: message ? mergeMessages(x.list, [message]) : x.list.map((y) => (y.id === m.id ? { ...y, deleted: true, type: 'deleted' as const, body: '' } : y)),
    }));
  }

  typing(id: number, state: 'composing' | 'recording' = 'composing') {
    if (Date.now() - this.lastTyping < 4500) return;
    this.lastTyping = Date.now();
    void this.gql.request(FlxChatTyping, { input: { id: String(id), state } }).catch(() => undefined);
  }

  schedule(ms: number) {
    if (this.stopped || !this.ports.visible()) return;
    if (this.timer) clearTimeout(this.timer);
    this.timer = setTimeout(() => void this.sync(), ms);
  }

  private interval() {
    const base = Math.max(10, this.o.poll ?? 10) * 1000;
    return feedPoll(this.o.realtime, Math.min(120_000, base * 2 ** Math.min(4, this.fails)));
  }

  async sync() {
    if (this.syncing || this.stopped || !this.ports.visible()) return;
    this.syncing = true;
    const { active, msgs } = this.s;
    let after = 0;
    if (active && msgs[active]?.loaded) for (const m of msgs[active]!.list) if (m.id > after) after = m.id;
    try {
      const r = await this.gql.request(FlxChatUpdates, {
        since: this.since, after: after || null, chatId: active ? String(active) : null, afterChat: this.afterChat || null,
      });
      const u = r.flxChatUpdates;
      this.fails = 0;
      this.since = u.continueRev;
      this.afterChat = u.continueId;
      if (u.unread !== this.s.unread) this.o.onUnreadChange?.(u.unread);
      this.set({ rev: num(u.continueRev), conns: u.connections.map(connOf), unread: u.unread, offline: '' });
      for (const c of u.chats) {
        const row = chatOf(c);
        const old = this.s.chats[row.id];
        if (old && old.rev === row.rev) continue;
        if (old && row.lastInTs > old.lastInTs && row.id !== this.s.active) this.set((s) => ({ fresh: { ...s.fresh, [row.id]: Date.now() } }));
        this.upsert(row);
      }
      if (active && u.messages.length) this.patchMsgs(active, (m) => ({ list: mergeMessages(m.list, u.messages.map(msgOf)) }));
      if (u.incoming.length) this.o.onIncoming?.(u.incoming.map((i) => ({ id: i.databaseId, chat: i.chatId, title: i.title, body: i.body, avatar: '' })));
      this.markRead();
      this.schedule(u.truncated || u.messagesTruncated ? 200 : this.interval());
    } catch (e) {
      this.fails++;
      const code = e instanceof FlxGraphQLError ? e.code : '';
      if (code === 'UNAUTHENTICATED' || code === 'FORBIDDEN') this.set({ offline: 'auth' });
      else if (this.fails > 2) this.set({ offline: 'net' });
      this.schedule(this.interval());
    } finally {
      this.syncing = false;
      if (this.again) {
        this.again = false;
        this.schedule(0); // a signal arrived while this sync was running
      }
    }
  }
}
