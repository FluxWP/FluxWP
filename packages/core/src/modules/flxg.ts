// Flux Tasks tam REST (flx-tasks/v1). WP'deki görev uygulamasıyla aynı veri; uygulamada belirteçle,
// WP admin'de çerez + nonce ile çağrılır. Kaynak: plugins/flx-tasks/includes/rest.php, store.php.
import type { ApiClient } from '../api/client';

export type FlxgRole = 'view' | 'comment' | 'edit' | 'admin';

export interface FlxgUser { id: number; name: string; initials: string; avatar: string; color: string }
export interface FlxgStatus { key: string; label: string; color: string; type: 'open' | 'done' }
export interface FlxgSpace {
  id: number; name: string; description?: string; color: string; icon?: string; owner_id: number;
  access: 'all' | 'members'; default_role?: FlxgRole; subtask_depth?: number; position: number; archived?: number; role: FlxgRole;
}
export interface FlxgFolder { id: number; space_id: number; name: string; color?: string; position: number }
export interface FlxgList {
  id: number; space_id: number; folder_id: number; name: string; color: string; owner_id?: number;
  access: 'all' | 'members'; statuses: FlxgStatus[]; kind?: 'task' | 'meeting'; position: number; role: FlxgRole;
}
export interface FlxgTag { id: number; space_id: number; name: string; color: string }
export interface FlxgFieldChoice { id: string; label: string; color: string }
export interface FlxgField {
  id: number; list_id: number; name: string; type: string;
  options: { choices?: FlxgFieldChoice[]; max?: number; currency?: string; decimals?: number; hint?: string };
}
export interface FlxgViewConfig {
  filters?: Record<string, unknown>;
  group?: string;
  sort?: { by: string; dir: 'asc' | 'desc' };
  fields?: string[];
  cover?: boolean;
  showDone?: boolean;
  dateField?: 'due' | 'start';
  subtasks?: 'open' | 'closed' | 'off';
  subFilter?: boolean;
  wrap?: boolean;
  widths?: Record<string, number>;
  collapsed?: string[];
}
export interface FlxgView {
  id: number; scope: 'list' | 'space' | 'home'; scope_id: number; user_id: number; name: string;
  type: 'list' | 'board' | 'calendar'; config: FlxgViewConfig; position: number;
}
export interface FlxgTask {
  id: number; list: number; parent: number; kind: string; title: string; status: string; priority: number;
  start: string; due: string; cover: number; pos: number; creator: number; created: string | null; updated: string | null;
  completed: string | null; assignees: number[]; tags: number[]; fields: Record<string, unknown>;
  comments: number; files: number; subtasks: number; archived: number; hasDesc?: boolean; description?: string;
  agenda?: number; decisions?: number;
}
export interface FlxgFile {
  id: number; task: number; comment: number; field: number; doc?: number; context: string; user: number;
  name: string; mime: string; size: number; image: boolean; thumb?: string; w?: number; h?: number; created: string | null;
}
export interface FlxgComment { id: number | string; task?: number; user: number; body: string; created: string; edited?: string | null; files: FlxgFile[]; sending?: boolean; _tmp?: string }
export interface FlxgActivity { id: number; user: number; action: string; data: Record<string, unknown>; created: string }
export interface FlxgTaskDetail extends FlxgTask {
  description: string;
  comments_list: FlxgComment[];
  activity: FlxgActivity[];
  attachments: FlxgFile[];
  watchers: number[];
  watching: boolean;
  children: (FlxgTask & { sending?: boolean; _tmp?: string })[];
  subtree: FlxgTask[];
  role: FlxgRole;
  parentInfo?: { id: number; title: string } | null;
  depth?: number;
  depthLimit?: number;
  childFiles?: Record<number, FlxgFile[]>;
}
export interface FlxgNotif {
  id: number; type: string; actor: number; task: number; list: number; title: string; read: boolean; created: string;
  data?: { label?: string; excerpt?: string };
}
export interface FlxgStructure {
  spaces: FlxgSpace[]; folders: FlxgFolder[]; lists: FlxgList[]; docs: { id: number; space_id: number; folder_id: number; title: string }[];
  tags: FlxgTag[]; rev: number;
}
export interface FlxgBoot extends FlxgStructure {
  me: FlxgUser & { can: { create: boolean; manage: boolean; ai: boolean }; email: boolean; prefs: Record<string, unknown> };
  users: FlxgUser[];
  unread: number;
  time: string;
  config: { poll: number; maxUpload: number; priorities: { id: number; label: string; color: string }[]; today: string; weekStart: number; fieldTypes: Record<string, string> };
}
export interface FlxgScopeMeta { role?: FlxgRole; fields?: FlxgField[]; views: FlxgView[] }
export interface FlxgSync {
  time: string; unread: number; rev: number; notifs: FlxgNotif[]; tasks: FlxgTask[]; gone: number[]; taskChanged?: boolean;
}

const NS = 'flx-tasks/v1/';

export function flxg(api: ApiClient) {
  const get = <T>(p: string, query?: Record<string, string | number | boolean | undefined | null>) => api<T>(NS + p, { query });
  const send = <T>(method: 'POST' | 'PATCH' | 'DELETE', p: string, body?: unknown) => api<T>(NS + p, { method, body });
  return {
    boot: () => get<FlxgBoot>('boot'),
    me: (body: { prefs?: Record<string, unknown>; email?: boolean }) => send<unknown>('POST', 'me', body),
    home: () => get<FlxgScopeMeta>('home'),
    list: (id: number) => get<FlxgScopeMeta & { list: FlxgList }>('lists/' + id),
    space: (id: number) => get<FlxgScopeMeta & { space: FlxgSpace }>('spaces/' + id),
    tasks: (q: Record<string, string | number | undefined>) => get<{ tasks: FlxgTask[]; time: string; fields?: FlxgField[] }>('tasks', q),
    task: (id: number) => get<{ task: FlxgTaskDetail; unread?: number }>('tasks/' + id),
    create: (data: Record<string, unknown>) => send<{ task: FlxgTask }>('POST', 'tasks', data),
    update: (id: number, patch: Record<string, unknown>) => send<{ task: FlxgTask | FlxgTaskDetail }>('PATCH', 'tasks/' + id, patch),
    remove: (id: number) => send<{ deleted: number }>('DELETE', 'tasks/' + id),
    bulk: (body: { ids: number[]; patch?: Record<string, unknown>; delete?: boolean }) => send<unknown>('POST', 'tasks/bulk', body),
    watch: (id: number, watch: boolean) => send<{ watchers: number[] }>('POST', `tasks/${id}/watch`, { watch }),
    comment: (id: number, body: string, files: number[]) => send<FlxgComment>('POST', `tasks/${id}/comments`, { body, files }),
    editComment: (id: number, body: string) => send<unknown>('PATCH', 'comments/' + id, { body }),
    deleteComment: (id: number) => send<unknown>('DELETE', 'comments/' + id),
    upload: (file: File, params: Record<string, string | number>) => {
      const fd = new FormData();
      fd.append('file', file);
      for (const [k, v] of Object.entries(params)) fd.append(k, String(v));
      return api<FlxgFile>(NS + 'files', { method: 'POST', formData: fd });
    },
    file: (id: number, size?: string) => api<Blob>(NS + 'files/' + id, { query: { size }, as: 'blob' }),
    deleteFile: (id: number) => send<unknown>('DELETE', 'files/' + id),
    tag: (space: number, name: string) => send<{ tag: FlxgTag; tags: FlxgTag[] }>('POST', 'tags', { space, name }),
    createView: (body: Record<string, unknown>) => send<{ view: FlxgView; views: FlxgView[] }>('POST', 'views', body),
    updateView: (id: number, body: Record<string, unknown>) => send<{ view: FlxgView }>('PATCH', 'views/' + id, body),
    deleteView: (id: number) => send<{ views: FlxgView[] }>('DELETE', 'views/' + id),
    notifications: (q: { before?: number; unread?: number }) => get<{ items: FlxgNotif[]; unread: number }>('notifications', q),
    read: (body: { all?: boolean; task?: number; ids?: number[] }) => send<{ unread: number }>('POST', 'notifications/read', body),
    ai: (id: number, action: string, hint: string) => send<{ text?: string; html?: string; items?: string[] }>('POST', 'ai/task/' + id, { action, hint }),
    sync: (q: Record<string, string | number | undefined>) => get<FlxgSync>('sync', q),
  };
}
export type FlxgApi = ReturnType<typeof flxg>;
