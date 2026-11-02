// Flux Chat WhatsApp (flx-chat/v1/app/*). Sözleşme: docs/fluxapp/MODUL-API.md §1
import type { ApiClient } from '../api/client';
import type { Page } from './common';

export type MsgBy = 'customer' | 'bot' | 'staff';

export interface Chat {
  id: number;
  name: string;
  phone: string;
  avatar: string;
  unread: number;
  last: { text: string; at: string; at_label: string; dir: 'in' | 'out'; by: MsgBy } | null;
  bot: { on: boolean };
  tags: string[];
  assigned: { id: number; name: string } | null;
  blocked: boolean;
}

export interface ChatDetail extends Chat {
  notes?: string;
  donor?: { name?: string; total_label?: string; count?: number; url?: string } | null;
}

export interface Message {
  id: number;
  dir: 'in' | 'out';
  by: MsgBy;
  author: string;
  text: string;
  media: { type: 'image' | 'audio' | 'video' | 'document'; url: string; name?: string } | null;
  at: string;
  at_label: string;
  status: 'pending' | 'sent' | 'delivered' | 'read' | 'failed';
}

export type ChatFilter = 'open' | 'mine' | 'all' | 'unread';

export const cagri = (api: ApiClient) => ({
  chats: (p: { filter: ChatFilter; q?: string; page?: number }) => api<Page<Chat>>('flx-chat/v1/app/chats', { query: p }),
  chat: (id: number) => api<ChatDetail>(`flx-chat/v1/app/chats/${id}`),
  older: (id: number, before?: number) => api<{ items: Message[]; has_more: boolean }>(`flx-chat/v1/app/chats/${id}/messages`, { query: { before, limit: 40 } }),
  newer: (id: number, after: number) => api<{ items: Message[]; has_more?: boolean }>(`flx-chat/v1/app/chats/${id}/messages`, { query: { after } }),
  send: (id: number, text: string) => api<Message>(`flx-chat/v1/app/chats/${id}/send`, { method: 'POST', body: { text } }),
  read: (id: number) => api<{ ok: boolean }>(`flx-chat/v1/app/chats/${id}/read`, { method: 'POST' }),
  bot: (id: number, on: boolean) => api<Chat>(`flx-chat/v1/app/chats/${id}/bot`, { method: 'POST', body: { on } }),
});
