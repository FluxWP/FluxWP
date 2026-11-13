// WhatsApp ekranının durumu ve canlı eşitleme. Eski app.js'deki S nesnesi + sync() döngüsünün karşılığı.
// Ekran başına bir motor (hesap/site değişince yenisi kurulur).
import { createStore, type StoreApi } from 'zustand/vanilla';
import {
  ApiError, chatMatches, mergeMessages, sortChats, flxc,
  type ApiClient, type FlxcApi, type FlxcBootstrap, type FlxcChat, type FlxcChatPatch, type FlxcConnection,
  type FlxcDeleteScope, type FlxcFilter, type FlxcMessage, type FlxcSendArgs, type FlxcSync,
} from '@fluxwp/core';
import { browserPorts, type ModulePorts } from '../ports';

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
  /** 1.37.0+: kanal süzgeci ('' = hepsi) */
  channel: string;
  q: string;
  active: number | null;
  msgs: Record<number, ChatMsgs>;
  /** Yeni mesaj gelen sohbetler (satır parlaması): id → zaman */
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
  /** Sunucudaki poll ayarı (sn); varsayılan 10. */
  poll?: number;
  /** Visibility, blob URLs, storage. Defaults to the browser adapter so the desktop panel is unchanged. */
  ports?: ModulePorts;
}

const PAGE = 50;
const emptyMsgs = (): ChatMsgs => ({ list: [], more: true, loaded: false, loading: false, note: '' });

export class WaEngine {
  readonly api: FlxcApi;
  readonly store: StoreApi<WaState>;
  private timer: ReturnType<typeof setTimeout> | null = null;
  private fails = 0;
  private syncing = false;
  private stopped = false;
  private seq = 0;
  private listReq = 0;
  /** iyimser gönderimde yeniden deneme için argümanlar */
  private pendingArgs = new Map<number, FlxcSendArgs>();
  private readonly ports: ModulePorts;
  private unvis: (() => void) | null = null;

  constructor(private o: EngineOptions) {
    this.ports = o.ports ?? browserPorts();
    this.api = flxc(o.client);
    this.store = createStore<WaState>(() => ({
      boot: null, bootError: '', rev: 0, chats: {}, order: [], listLoading: true, listMore: false,
      filter: 'all', label: '', channel: '', q: '', active: null, msgs: {}, fresh: {}, conns: [], unread: 0, offline: '',
    }));
  }

  get s() {
    return this.store.getState();
  }
  private set(p: Partial<WaState> | ((s: WaState) => Partial<WaState>)) {
    this.store.setState(p as never);
  }

  /* ---------- yaşam döngüsü ---------- */

  async start() {
    this.stopped = false;
    this.unvis = this.ports.onVisibility((visible) => {
      if (visible) {
        this.schedule(150);
        this.markRead();
      }
    });
    try {
      const b = await this.api.bootstrap();
      this.set({ boot: b, rev: b.rev, conns: b.connections, unread: b.unread });
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
  }

  /* ---------- liste ---------- */

  async loadChats(more = false) {
    const req = ++this.listReq;
    const { filter, q, label, channel, order } = this.s;
    this.set({ listLoading: true });
    try {
      const r = await this.api.chats({ filter, q: q || undefined, label: label || undefined, channel: channel || undefined, offset: more ? order.length : 0, limit: PAGE });
      if (req !== this.listReq) return;
      this.set((s) => {
        const chats = { ...s.chats };
        for (const c of r.chats) chats[c.id] = c;
        const ids = r.chats.map((c) => c.id);
        return { chats, order: more ? [...s.order, ...ids.filter((id) => !s.order.includes(id))] : ids, listMore: r.chats.length >= PAGE, listLoading: false };
      });
    } catch (e) {
      if (req === this.listReq) this.set({ listLoading: false });
      throw e;
    }
  }

  setView(p: Partial<Pick<WaState, 'filter' | 'label' | 'channel' | 'q'>>) {
    this.set(p);
    void this.loadChats().catch(() => undefined);
  }

  /* ---------- sohbet ---------- */

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
      const r = await this.api.messages(id, before);
      this.patchMsgs(id, (m) => ({ list: mergeMessages(m.list, r.messages), more: r.more, loaded: true, loading: false, note: r.note }));
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
      const opt = { ...old } as FlxcChat;
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
      const r = await this.api.update(id, patch);
      this.upsert(r.chat);
      this.schedule(400); // sistem satırı (atama/kapatma) gelsin
    } catch (e) {
      if (old) this.upsert(old);
      throw e;
    }
  }

  async setBot(id: number, on: boolean) {
    const r = await this.api.bot(id, on);
    this.upsert(r.chat);
    this.schedule(400);
  }

  markRead() {
    const id = this.s.active;
    const c = id ? this.s.chats[id] : null;
    if (!c || !c.unread || !this.ports.visible()) return;
    this.upsert({ ...c, unread: 0 });
    const unread = Math.max(0, this.s.unread - c.unread);
    this.set({ unread });
    this.o.onUnreadChange?.(unread);
    void this.api.read(c.id).catch(() => undefined);
  }

  /* ---------- gönderim ---------- */

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
      const r = await this.api.send(id, args);
      this.pendingArgs.delete(tempId);
      this.patchMsgs(id, (m) => ({ list: mergeMessages(m.list.filter((x) => x.id !== tempId), [r.message]) }));
      const c = this.s.chats[id];
      if (c && args.type !== 'note') {
        this.upsert({
          ...c, lastTs: r.message.ts, assigned: c.assigned || (args.source !== 'ai' ? me?.id ?? 0 : 0),
          last: { dir: r.message.dir, type: r.message.type, body: r.message.body, status: r.message.status, user: r.message.user },
        });
      }
      return r.message;
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
    const r = await this.api.retry(m.id);
    this.patchMsgs(m.chat, (x) => ({ list: mergeMessages(x.list, [r.message]) }));
  }

  async react(m: FlxcMessage, emoji: string) {
    const r = await this.api.react(m.id, emoji);
    this.patchMsgs(m.chat, (x) => ({ list: mergeMessages(x.list, [r.message]) }));
  }

  async deleteNote(m: FlxcMessage) {
    return this.deleteMessage(m, 'panel');
  }

  /** 1.38.0+: herkesten sil (WhatsApp'ta da) ya da yalnız panelde gizle. Satır sunucuda kalır. */
  async deleteMessage(m: FlxcMessage, scope: FlxcDeleteScope = 'panel') {
    const r = await this.api.deleteMessage(m.id, m.dir === 'note' ? 'panel' : scope);
    this.patchMsgs(m.chat, (x) => ({
      list: r.message
        ? mergeMessages(x.list, [r.message])
        : x.list.map((y) => (y.id === m.id ? { ...y, deleted: true, type: 'deleted' as const, body: '' } : y)),
    }));
  }

  private lastTyping = 0;
  typing(id: number, state: 'composing' | 'recording' = 'composing') {
    if (Date.now() - this.lastTyping < 4500) return;
    this.lastTyping = Date.now();
    void this.api.typing(id, state).catch(() => undefined);
  }

  /* ---------- canlı eşitleme ---------- */

  schedule(ms: number) {
    if (this.stopped) return;
    if (this.timer) clearTimeout(this.timer);
    this.timer = setTimeout(() => void this.sync(), ms);
  }

  /** Asistan cevap hazırlıyor ya da az önce gelen mesaja cevap verecek: kısa aralık. */
  private botActive() {
    const now = Date.now();
    return Object.values(this.s.chats).some((c) => c.botBusy || (c.bot === 'on' && c.last?.dir === 'in' && now - c.lastInTs < 90_000));
  }

  private interval() {
    let base = Math.max(3, this.o.poll ?? 10) * 1000;
    if (!this.ports.visible()) base = Math.max(base, 60_000);
    else if (this.botActive()) base = Math.min(base, 3000);
    return Math.min(120_000, base * 2 ** Math.min(4, this.fails));
  }

  async sync() {
    if (this.syncing || this.stopped) return;
    this.syncing = true;
    const { active, rev, msgs } = this.s;
    let after = 0;
    if (active && msgs[active]?.loaded) for (const m of msgs[active]!.list) if (m.id > after) after = m.id;
    try {
      // Geniş pencere: yavaş sunucuda geç yazılan satırlar kaçmasın; aynı rev'li öğe atlanır.
      const r = await this.api.sync({ since: rev ? rev - 15_000 : 0, chat: active ?? undefined, after: after || undefined });
      this.fails = 0;
      if (r.nonce) this.o.onNonce?.(r.nonce);
      if (r.unread !== this.s.unread) this.o.onUnreadChange?.(r.unread);
      this.set({ rev: r.rev, conns: r.connections ?? this.s.conns, unread: r.unread, offline: '' });
      for (const c of r.chats) {
        const old = this.s.chats[c.id];
        if (old && old.rev === c.rev) continue;
        if (old && c.lastInTs > old.lastInTs && c.id !== this.s.active) this.set((s) => ({ fresh: { ...s.fresh, [c.id]: Date.now() } }));
        this.upsert(c);
      }
      if (active && r.messages.length) this.patchMsgs(active, (m) => ({ list: mergeMessages(m.list, r.messages) }));
      if (r.incoming.length) this.o.onIncoming?.(r.incoming);
      this.markRead();
    } catch (e) {
      this.fails++;
      const err = e as ApiError;
      if (err.status === 401 || err.status === 403 || err.code === 'rest_cookie_invalid_nonce') this.set({ offline: 'auth' });
      else if (this.fails > 2) this.set({ offline: 'net' });
    } finally {
      this.syncing = false;
      this.schedule(this.interval());
    }
  }
}
