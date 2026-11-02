// Flux Chat tam REST (flx-chat/v1). WP admin'deki ekranla aynı veri; uygulamada belirteçle,
// WP'de çerez + nonce ile çağrılır. Kaynak: plugins/flx-chat/includes/rest.php, store.php.
import type { ApiClient } from '../api/client';

export type FlxcMsgType =
  | 'text' | 'note' | 'image' | 'video' | 'audio' | 'document' | 'sticker'
  | 'location' | 'contact' | 'poll' | 'deleted' | 'unknown';
export type FlxcDir = 'in' | 'out' | 'note' | 'system';
export type FlxcStatus = 'pending' | 'sent' | 'delivered' | 'read' | 'played' | 'failed' | '';
export type FlxcBot = '' | 'on' | 'off' | 'paused';
export type FlxcFilter = 'all' | 'unread' | 'mine' | 'unassigned' | 'ai' | 'closed' | 'any';

export interface FlxcChat {
  id: number;
  conn: string;
  jid: string;
  phone: string;
  phoneFmt: string;
  title: string;
  name: string;
  pushName: string;
  avatar: string;
  avatarAt: number;
  labels: string[];
  notes: string;
  status: 'open' | 'closed';
  assigned: number;
  unread: number;
  pinned: number;
  lastTs: number;
  lastInTs: number;
  group: boolean;
  /** 0 | 1 yazıyor | 2 ses kaydediyor */
  typing: number;
  rev: number;
  created: number;
  last: { dir: FlxcDir; type: FlxcMsgType; body: string; status: FlxcStatus; user: string } | null;
  unknownName: boolean;
  meta: { blocked?: unknown };
  bot: FlxcBot;
  botBusy: 0 | 1;
  ai: 0 | 1;
  optout: 0 | 1;
}

export interface FlxcMedia {
  url: string;
  mime: string;
  name: string;
  size: number;
  ready: boolean;
  ptt: boolean;
  secs: number;
  w: number;
  h: number;
  thumb: string;
}

export interface FlxcLinkPreview {
  ok?: boolean;
  url: string;
  title?: string;
  desc?: string;
  image?: string;
  site?: string;
  domain?: string;
}

export interface FlxcMessage {
  id: number;
  chat: number;
  wa: string;
  dir: FlxcDir;
  type: FlxcMsgType;
  body: string;
  status: FlxcStatus;
  error: string;
  user: string;
  userId: number;
  source: string;
  ts: number;
  rev: number;
  /** taraf ('in' | 'out' | jid) → emoji */
  react: Record<string, string>;
  edited: boolean;
  sender: string;
  deleted?: boolean;
  lp?: FlxcLinkPreview | false;
  receipts?: unknown[];
  rcheck?: { v: string; s: string };
  media?: FlxcMedia;
  loc?: { lat: number; lng: number; name: string };
  vcardPhone?: string;
  quoted?: { id: number; dir: FlxcDir; type: FlxcMsgType; body: string; user: string };
  /** yalnız istemci: iyimser gönderim */
  local?: boolean;
}

export interface FlxcLabel { id: string; name: string; color: string }
export interface FlxcConnection { id: string; label: string; color: string; state: string; at: number }
export interface FlxcReply { id: number; shortcut: string; title: string; body: string; category: string; uses: number }
export interface FlxcAgent { id: number; name: string }

export interface FlxcBootstrap {
  me: { id: number; name: string; manage: boolean };
  agents: FlxcAgent[];
  labels: FlxcLabel[];
  connections: FlxcConnection[];
  replies: FlxcReply[];
  unread: number;
  ai: { id: string; label: string; auto: boolean } | null;
  bot: boolean;
  records: Record<string, unknown>;
  rev: number;
}

export interface FlxcSync {
  rev: number;
  chats: FlxcChat[];
  messages: FlxcMessage[];
  incoming: { id: number; chat: number; title: string; body: string; avatar: string }[];
  connections: FlxcConnection[];
  unread: number;
  nonce?: string;
}

export interface FlxcSection {
  id: string;
  title: string;
  items: { label: string; sub?: string; url?: string; insert?: string }[];
}

export interface FlxcChatPatch {
  name?: string;
  notes?: string;
  labels?: string[];
  status?: 'open' | 'closed';
  assigned?: number;
  pinned?: boolean;
  unread?: number;
}

export interface FlxcSendArgs {
  type?: 'text' | 'note' | 'audio' | 'image' | 'video' | 'document';
  body?: string;
  quoted?: number;
  source?: 'agent' | 'quick' | 'ai';
  preview?: boolean;
  file?: Blob;
  name?: string;
}

const P = 'flx-chat/v1';

export const flxc = (api: ApiClient) => ({
  bootstrap: () => api<FlxcBootstrap>(`${P}/bootstrap`),
  sync: (q: { since: number; chat?: number; after?: number }) => api<FlxcSync>(`${P}/sync`, { query: { ...q, _t: Date.now() } }),
  chats: (q: { filter?: FlxcFilter; q?: string; label?: string; offset?: number; limit?: number }) =>
    api<{ chats: FlxcChat[] }>(`${P}/chats`, { query: { ...q, _t: Date.now() } }),
  newChat: (b: { phone: string; name?: string; conn?: string; check?: boolean }) => api<{ chat: FlxcChat }>(`${P}/chats`, { method: 'POST', body: b }),
  checkNumber: (phone: string, conn?: string) =>
    api<{ valid: boolean; phone?: string; phoneFmt?: string; exists?: boolean | null; chat?: number; name?: string }>(`${P}/check-number`, { method: 'POST', body: { phone, conn } }),
  chat: (id: number) => api<{ chat: FlxcChat; sections: FlxcSection[] }>(`${P}/chats/${id}`, { query: { _t: Date.now() } }),
  update: (id: number, patch: FlxcChatPatch) => api<{ chat: FlxcChat }>(`${P}/chats/${id}`, { method: 'POST', body: patch }),
  messages: (id: number, before?: number) =>
    api<{ messages: FlxcMessage[]; more: boolean; note: string }>(`${P}/chats/${id}/messages`, { query: { before, _t: Date.now() } }),
  send: (id: number, a: FlxcSendArgs) => {
    if (a.file) {
      const fd = new FormData();
      fd.set('type', a.type ?? 'document');
      if (a.body) fd.set('body', a.body);
      if (a.quoted) fd.set('quoted', String(a.quoted));
      if (a.name) fd.set('name', a.name);
      fd.set('file', a.file, a.name ?? 'file');
      return api<{ message: FlxcMessage }>(`${P}/chats/${id}/messages`, { method: 'POST', formData: fd });
    }
    return api<{ message: FlxcMessage }>(`${P}/chats/${id}/messages`, {
      method: 'POST',
      body: { type: a.type ?? 'text', body: a.body ?? '', quoted: a.quoted, source: a.source, preview: a.preview ?? true },
    });
  },
  read: (id: number) => api<{ ok: boolean }>(`${P}/chats/${id}/read`, { method: 'POST' }),
  typing: (id: number, state: 'composing' | 'recording' = 'composing') => api<{ ok: boolean }>(`${P}/chats/${id}/typing`, { method: 'POST', body: { state } }),
  bot: (id: number, on: boolean) => api<{ chat: FlxcChat }>(`${P}/chats/${id}/bot`, { method: 'POST', body: { state: on ? 'on' : 'off' } }),
  ai: (id: number, o: { fresh?: boolean; hint?: string } = {}) => api<{ text: string; cached?: boolean }>(`${P}/chats/${id}/ai`, { method: 'POST', body: o }),
  retry: (mid: number) => api<{ message: FlxcMessage }>(`${P}/messages/${mid}/retry`, { method: 'POST' }),
  react: (mid: number, emoji: string) => api<{ message: FlxcMessage }>(`${P}/messages/${mid}/react`, { method: 'POST', body: { emoji } }),
  deleteNote: (mid: number) => api<{ ok: boolean }>(`${P}/messages/${mid}`, { method: 'DELETE' }),
  linkPreview: (url: string) => api<FlxcLinkPreview>(`${P}/link-preview`, { query: { url } }),
  replies: () => api<{ replies: FlxcReply[] }>(`${P}/replies`),
  useReply: (rid: number, chat: number) => api<{ text: string }>(`${P}/replies/${rid}/use`, { method: 'POST', body: { chat } }),
  /** Medya dosyası (uygulamada belirteçle blob olarak). */
  media: (url: string) => api<Blob>(url, { as: 'blob' }),
});

export type FlxcApi = ReturnType<typeof flxc>;

/** Sohbet listesi sıralaması: sabitlenen üstte, sonra son mesaj zamanı. */
export function sortChats(a: FlxcChat, b: FlxcChat): number {
  return b.pinned - a.pinned || b.lastTs - a.lastTs || b.id - a.id;
}

/** Sohbet görünümdeki filtreye uyuyor mu (sync ile gelen değişiklikleri listeye yerleştirmek için). Aramada sunucu karar verir. */
export function chatMatches(c: FlxcChat, f: FlxcFilter, me: number, label = '', q = ''): boolean {
  if (q) return true;
  if (label && !c.labels.includes(label)) return false;
  switch (f) {
    case 'unread': return c.unread > 0 && c.status !== 'closed';
    case 'mine': return c.assigned === me && c.status !== 'closed';
    case 'unassigned': return !c.assigned && c.status !== 'closed';
    case 'closed': return c.status === 'closed';
    case 'ai': return c.ai === 1 && c.status !== 'closed';
    case 'any': return true;
    default: return c.status !== 'closed';
  }
}

/** Mesajları id'ye göre birleştir (sync + sayfalama + iyimser gönderim). */
export function mergeMessages(list: FlxcMessage[], incoming: FlxcMessage[]): FlxcMessage[] {
  if (!incoming.length) return list;
  const byId = new Map(list.map((m) => [m.id, m]));
  for (const m of incoming) byId.set(m.id, m);
  return [...byId.values()].sort((a, b) => a.ts - b.ts || a.id - b.id);
}
