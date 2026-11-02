// Flux Tasks ekran durumu: WP'deki assets/js/state.js'in karşılığı. Tek nesne (S) + sürüm sayacı;
// bileşenler useYtg() ile sürüme abone olur, değişiklikte bump() çağrılır.
import { useSyncExternalStore } from 'react';
import type { FlxgApi, FlxgBoot, FlxgField, FlxgList, FlxgNotif, FlxgSpace, FlxgStatus, FlxgTag, FlxgTask, FlxgUser, FlxgView, FlxgViewConfig } from '@fluxwp/core';
import { tt } from './i18n';

export type Scope = { type: 'home' | 'list' | 'space'; id: number; role: string };
export type Route = { page: 'home' | 'inbox' | 'list' | 'space'; id: number; view: number; task: number };
export type Group = {
  key: string; label: string; color: string; tasks: FlxgTask[];
  patch: ((task: FlxgTask, from: string | null) => Record<string, unknown> | null) | null;
  user?: number; list?: number; statusType?: string;
};
type ScopeField = { key: string; id: number; ids: number[]; name: string; type: string; options: FlxgField['options'] };

export interface FlxgState {
  ready: boolean;
  error: string;
  me: FlxgBoot['me'] | null;
  users: Record<number, FlxgUser>;
  userList: FlxgUser[];
  config: FlxgBoot['config'];
  spaces: FlxgSpace[];
  folders: FlxgBoot['folders'];
  lists: FlxgList[];
  tags: FlxgTag[];
  rev: number;
  unread: number;
  lastNotif: number;
  route: Route;
  scope: Scope | null;
  loading: boolean;
  views: FlxgView[];
  view: FlxgView | null;
  draft: FlxgViewConfig | null;
  dirty: boolean;
  fields: FlxgField[];
  tasks: Record<number, FlxgTask & { _ctx?: boolean; _outside?: boolean }>;
  keep: Record<number, 1>;
  saving: Record<number, number>;
  selected: Record<number, 1>;
  since: string;
  q: string;
  openTask: number;
  /** Liste görünümü: altına alt görev eklenen görev. */
  addUnder: number;
  cal: { y: number; m: number; mode: 'month' | 'week' | 'agenda' | null; week: string | null };
}

export const rank = (r?: string) => ({ view: 1, comment: 2, edit: 3, admin: 4 } as Record<string, number>)[r ?? ''] ?? 0;
export const norm = (s: unknown) => String(s ?? '').toLocaleLowerCase('tr').normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/ı/g, 'i');
export const cmpStr = (a: unknown, b: unknown) => String(a ?? '').localeCompare(String(b ?? ''), 'tr', { sensitivity: 'base' });
export const between = (a: number | null, b: number | null) => {
  if (a === null && b === null) return 1000;
  if (a === null) return (b as number) - 1000;
  if (b === null) return a + 1000;
  return (a + b) / 2;
};

/* ---------------- Tarih ---------------- */
export const parseDay = (s?: string | null) => {
  const m = s ? /^(\d{4})-(\d{2})-(\d{2})(?:T(\d{2}):(\d{2}))?/.exec(s) : null;
  return m ? new Date(+m[1]!, +m[2]! - 1, +m[3]!, m[4] ? +m[4] : 0, m[5] ? +m[5] : 0) : null;
};
export const dayKey = (d: Date) => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
export const today = () => dayKey(new Date());
export const addDays = (key: string, n: number) => {
  const d = parseDay(key)!;
  d.setDate(d.getDate() + n);
  return dayKey(d);
};
export const store = {
  get(k: string): string | null {
    try { return localStorage.getItem('flxg-' + k); } catch { return null; }
  },
  set(k: string, v: string | null) {
    try { if (v === null) localStorage.removeItem('flxg-' + k); else localStorage.setItem('flxg-' + k, v); } catch { /* yok */ }
  },
  json<T>(k: string): T | null {
    try { return JSON.parse(store.get(k) ?? 'null') as T; } catch { return null; }
  },
};

export class FlxgEngine {
  S: FlxgState;
  private ver = 0;
  private subs = new Set<() => void>();
  private syncTimer: ReturnType<typeof setTimeout> | undefined;
  private inFlight = false;
  private fail = 0;
  private loadSeq = 0;
  private prefTimer: ReturnType<typeof setTimeout> | undefined;
  private prefQueue: Record<string, unknown> = {};
  private listeners: Record<string, Set<(a?: unknown) => void>> = {};
  onRoute?: (r: Route) => void;
  onNotify?: (n: FlxgNotif[]) => void;
  onError: (e: unknown) => void = () => {};

  constructor(public api: FlxgApi, initial?: Partial<Route>) {
    const now = new Date();
    this.S = {
      ready: false, error: '', me: null, users: {}, userList: [],
      config: { poll: 45, maxUpload: 20 * 1024 * 1024, priorities: [], today: today(), weekStart: 1, fieldTypes: {} },
      spaces: [], folders: [], lists: [], tags: [], rev: 0, unread: 0, lastNotif: 0,
      route: { page: 'home', id: 0, view: 0, task: 0, ...initial },
      scope: null, loading: false, views: [], view: null, draft: null, dirty: false, fields: [], tasks: {}, keep: {}, saving: {}, selected: {},
      since: '', q: '', openTask: 0, addUnder: 0,
      cal: { y: now.getFullYear(), m: now.getMonth(), mode: null, week: null },
    };
  }

  /* ---------------- Abonelik ---------------- */
  subscribe = (fn: () => void) => {
    this.subs.add(fn);
    return () => this.subs.delete(fn);
  };
  version = () => this.ver;
  bump = () => {
    this.ver++;
    this.subs.forEach((f) => f());
  };
  on(ev: string, fn: (a?: unknown) => void) {
    (this.listeners[ev] ??= new Set()).add(fn);
    return () => this.listeners[ev]!.delete(fn);
  }
  emit(ev: string, a?: unknown) {
    this.listeners[ev]?.forEach((f) => f(a));
  }

  /* ---------------- Açılış ---------------- */
  async boot() {
    try {
      const b = await this.api.boot();
      this.applyBoot(b);
      this.S.ready = true;
      await this.go(this.S.route, true);
      this.schedule();
    } catch (e) {
      this.S.error = e instanceof Error ? e.message : String(e);
      this.bump();
    }
  }
  private applyBoot(b: FlxgBoot) {
    const S = this.S;
    S.me = b.me;
    S.userList = b.users;
    S.users = Object.fromEntries(b.users.map((u) => [u.id, u]));
    if (!S.users[b.me.id]) S.users[b.me.id] = b.me;
    S.config = b.config;
    S.unread = b.unread;
    this.applyStructure(b);
  }
  applyStructure(d: Partial<FlxgBoot>) {
    const S = this.S;
    if (d.spaces) S.spaces = d.spaces;
    if (d.folders) S.folders = d.folders;
    if (d.lists) S.lists = d.lists;
    if (d.tags) S.tags = d.tags;
    if (d.rev !== undefined) S.rev = d.rev;
    this.bump();
  }
  dispose() {
    clearTimeout(this.syncTimer);
    clearTimeout(this.prefTimer);
    this.subs.clear();
  }

  /* ---------------- Yönlendirme ---------------- */
  async go(r: Partial<Route>, force = false) {
    const S = this.S;
    const next: Route = { page: 'home', id: 0, view: 0, task: 0, ...r };
    const sameScope = !force && S.scope && next.page === S.route.page && next.id === S.route.id;
    S.route = next;
    this.onRoute?.(next);
    if (next.page !== 'inbox' && next.page !== 'home' && next.page !== 'list' && next.page !== 'space') next.page = 'home';
    if (next.page === 'list' && !this.list(next.id)) next.page = 'home';
    if (next.page === 'space' && !this.space(next.id)) next.page = 'home';
    S.openTask = next.task;
    if (next.page === 'inbox') {
      this.bump();
      return;
    }
    if (sameScope) {
      if (next.view && S.view?.id !== next.view) this.pickView(next.view);
      this.bump();
      return;
    }
    S.loading = true;
    S.q = '';
    this.bump();
    try {
      const ok = await this.loadScope(next.page as Scope['type'], next.id);
      if (!ok) return;
      this.pickView(next.view);
    } catch (e) {
      this.onError(e);
    } finally {
      S.loading = false;
      this.bump();
    }
  }
  /** Görev aç/kapat (görünüm aynı kalır). */
  openTask(id: number) {
    const r = { ...this.S.route, task: id };
    this.S.route = r;
    this.S.openTask = id;
    this.onRoute?.(r);
    this.bump();
  }
  private pickView(id: number) {
    const S = this.S;
    let v = S.views.find((x) => x.id === id);
    if (!v) {
      const rem = Number(store.get('view-' + this.scopeKey()));
      v = S.views.find((x) => x.id === rem) ?? S.views[0];
    }
    this.setView(v ?? null);
    if (v) store.set('view-' + this.scopeKey(), String(v.id));
  }
  scopeKey() {
    const sc = this.S.scope;
    return sc ? sc.type + (sc.id || '') : 'home';
  }
  setView(v: FlxgView | null) {
    this.S.view = v;
    this.S.draft = v ? JSON.parse(JSON.stringify(v.config ?? {})) : null;
    this.S.dirty = false;
    if (v) this.S.route = { ...this.S.route, view: v.id };
  }
  viewCfg(): FlxgViewConfig {
    return this.S.draft ?? {};
  }
  touchDraft() {
    this.S.dirty = true;
    this.bump();
  }

  /* ---------------- Yapı yardımcıları ---------------- */
  list = (id: number) => this.S.lists.find((l) => l.id === +id) ?? null;
  space = (id: number) => this.S.spaces.find((s) => s.id === +id) ?? null;
  folder = (id: number) => this.S.folders.find((f) => f.id === +id) ?? null;
  tag = (id: number) => this.S.tags.find((x) => x.id === +id) ?? null;
  canList = (id: number, need = 'view') => rank(this.list(id)?.role) >= rank(need);
  status(task: Pick<FlxgTask, 'list' | 'status'>): FlxgStatus {
    const l = this.list(task.list);
    if (!l) return { key: task.status, label: task.status, color: '#94a3b8', type: 'open' };
    return l.statuses.find((s) => s.key === task.status) ?? l.statuses[0]!;
  }
  isDone = (task: FlxgTask) => this.status(task).type === 'done';
  priority = (n: number) => this.S.config.priorities.find((p) => p.id === +n) ?? null;
  scopeLists(): FlxgList[] {
    const sc = this.S.scope;
    if (!sc) return [];
    if (sc.type === 'list') return [this.list(sc.id)].filter(Boolean) as FlxgList[];
    if (sc.type === 'space') return this.S.lists.filter((l) => l.space_id === sc.id);
    return this.S.lists.slice();
  }
  scopeTags() {
    const spaces = new Set(this.scopeLists().map((l) => l.space_id));
    return this.S.tags.filter((x) => spaces.has(x.space_id));
  }
  scopeStatuses() {
    const out: FlxgStatus[] = [];
    const seen = new Set<string>();
    for (const l of this.scopeLists())
      for (const s of l.statuses) {
        const k = norm(s.label);
        if (!seen.has(k)) {
          seen.add(k);
          out.push({ key: k, label: s.label, color: s.color, type: s.type });
        }
      }
    return out;
  }
  fieldsFor = (listId: number) => this.S.fields.filter((f) => f.list_id === +listId);
  scopeFields(): ScopeField[] {
    const out: ScopeField[] = [];
    const seen: Record<string, ScopeField> = {};
    for (const f of this.S.fields) {
      const k = norm(f.name) + '|' + f.type;
      if (seen[k]) {
        seen[k].ids.push(f.id);
        continue;
      }
      const g = { key: 'f' + f.id, id: f.id, ids: [f.id], name: f.name, type: f.type, options: f.options ?? {} };
      seen[k] = g;
      out.push(g);
    }
    return out;
  }
  fieldValue(task: FlxgTask, col: { id: number; ids?: number[] }) {
    for (const id of col.ids ?? [col.id]) {
      const v = task.fields?.[id];
      if (v !== undefined && v !== null) return { v, field: this.S.fields.find((f) => f.id === id)! };
    }
    return null;
  }
  depthLimit(listId: number) {
    const l = this.list(listId);
    const sp = l ? this.space(l.space_id) : null;
    return sp?.subtask_depth ? +sp.subtask_depth : 3;
  }
  taskDepth(task: FlxgTask) {
    let d = 0;
    let x: FlxgTask | undefined = task;
    for (let i = 0; i < 12 && x && x.parent; i++) {
      d++;
      x = this.S.tasks[x.parent];
    }
    return d;
  }
  isMeetingList = (id: number) => this.list(id)?.kind === 'meeting';
  meetingScope = () => this.S.scope?.type === 'list' && this.isMeetingList(this.S.scope.id);

  /* ---------------- Tercihler (hesapta saklanır) ---------------- */
  pref<T = unknown>(k: string): T | null {
    return ((this.S.me?.prefs ?? {}) as Record<string, T>)[k] ?? null;
  }
  setPref(k: string, v: unknown) {
    if (!this.S.me) return;
    const p = { ...(this.S.me.prefs ?? {}) };
    if (v === null || v === undefined) delete p[k];
    else p[k] = v;
    this.S.me.prefs = p;
    this.prefQueue[k] = v ?? null;
    clearTimeout(this.prefTimer);
    this.prefTimer = setTimeout(() => {
      const q = this.prefQueue;
      this.prefQueue = {};
      this.api.me({ prefs: q }).catch(() => {});
    }, 800);
    this.bump();
  }

  /* ---------------- Kapsam yükleme ---------------- */
  private scopeQuery(): Record<string, string | number> {
    const sc = this.S.scope;
    if (!sc) return {};
    return sc.type === 'list' ? { list: sc.id } : sc.type === 'space' ? { space: sc.id } : { mine: 1 };
  }
  async loadScope(type: Scope['type'], id: number) {
    const seq = ++this.loadSeq;
    const meta = type === 'list' ? this.api.list(id) : type === 'space' ? this.api.space(id) : this.api.home();
    const q = type === 'list' ? { list: id } : type === 'space' ? { space: id } : { mine: 1 };
    const [m, r] = await Promise.all([meta, this.api.tasks(q)]);
    if (seq !== this.loadSeq) return false;
    const S = this.S;
    S.scope = { type, id: +id || 0, role: m.role ?? (type === 'home' ? 'admin' : 'view') };
    S.views = m.views ?? [];
    S.fields = type === 'list' ? m.fields ?? [] : r.fields ?? m.fields ?? [];
    S.tasks = {};
    S.keep = {};
    for (const x of r.tasks) S.tasks[x.id] = x;
    S.since = r.time;
    S.selected = {};
    return true;
  }
  async reloadTasks() {
    if (!this.S.scope) return;
    const q: Record<string, string | number> = this.scopeQuery();
    const keep = Object.keys(this.S.keep);
    if (keep.length) q.keep = keep.slice(-100).join(',');
    const r = await this.api.tasks(q);
    this.S.tasks = {};
    for (const x of r.tasks) this.S.tasks[x.id] = x;
    if (r.fields && this.S.scope.type !== 'list') this.S.fields = r.fields;
    this.S.since = r.time;
    this.bump();
  }

  /* ---------------- Filtre / sıralama / gruplama ---------------- */
  inScopeTask(task: FlxgTask) {
    const sc = this.S.scope;
    if (!sc) return false;
    if (sc.type === 'list') return task.list === sc.id;
    if (sc.type === 'space') return this.list(task.list)?.space_id === sc.id;
    return task.assignees.includes(this.S.me!.id);
  }
  isTop(x: FlxgTask) {
    const p = x.parent ? this.S.tasks[x.parent] : null;
    return !p || !!p.archived;
  }
  hiddenKind(x: FlxgTask) {
    return x.kind === 'agenda' || (x.kind === 'decision' && this.S.scope?.type !== 'home');
  }
  taskMatches(x: FlxgTask) {
    const c = this.viewCfg();
    const f = (c.filters ?? {}) as Record<string, unknown>;
    const q = norm(this.S.q || f.q || '');
    const td = today();
    const week = addDays(td, 7);
    const st = this.status(x);
    if (c.showDone === false && st.type === 'done') return false;
    if (q) {
      const hay = norm(x.title) + ' #' + x.id;
      if (!hay.includes(q) && String(x.id) !== q.replace('#', '')) return false;
    }
    const arr = (k: string) => (Array.isArray(f[k]) ? (f[k] as string[]) : []);
    if (arr('status').length && !arr('status').includes(norm(st.label))) return false;
    if (arr('assignees').length) {
      const ok = arr('assignees').some((a) => (a === 'none' ? !x.assignees.length : a === 'me' ? x.assignees.includes(this.S.me!.id) : x.assignees.includes(+a)));
      if (!ok) return false;
    }
    if (arr('priority').length && !arr('priority').includes(String(x.priority || 0))) return false;
    if (arr('tags').length && !arr('tags').some((tg) => (tg === 'none' ? !x.tags.length : x.tags.includes(+tg)))) return false;
    if (f.due) {
      const d = x.due ? x.due.slice(0, 10) : '';
      if (f.due === 'overdue' && !(d && d < td && st.type !== 'done')) return false;
      if (f.due === 'today' && d !== td) return false;
      if (f.due === 'week' && !(d && d >= td && d <= week)) return false;
      if (f.due === 'none' && d) return false;
      if (f.due === 'has' && !d) return false;
    }
    if (f.fields) {
      const cols = this.scopeFields();
      const ff = f.fields as Record<string, unknown>;
      const pass = Object.keys(ff).every((key) => {
        const want = ff[key];
        const col = cols.find((g) => g.key === key);
        if (!col) return true;
        const fv = this.fieldValue(x, col);
        const v = fv ? fv.v : null;
        const empty = v === null || v === '' || (Array.isArray(v) && !v.length) || v === false;
        if (want === '__set') return !empty;
        if (want === '__empty') return empty;
        if (Array.isArray(want)) {
          if (!want.length) return true;
          if (empty) return want.includes('__empty');
          if (Array.isArray(v)) return v.some((z) => want.includes(String(z)));
          return want.includes(String(v));
        }
        if (col.type === 'checkbox') return want === 'yes' ? v === true : !v;
        if (col.type === 'rating') return Number(v) >= Number(want);
        return true;
      });
      if (!pass) return false;
    }
    return true;
  }
  childIndex() {
    const idx: Record<number, FlxgTask[]> = {};
    for (const x of Object.values(this.S.tasks)) {
      if (x.archived || !x.parent || !this.S.tasks[x.parent] || this.hiddenKind(x)) continue;
      (idx[x.parent] ??= []).push(x);
    }
    const sort = this.viewCfg().sort;
    Object.values(idx).forEach((a) => this.sortTasks(a, sort));
    return idx;
  }
  filtered(opts: { includeSub?: boolean; context?: boolean } = {}) {
    const S = this.S;
    const out: (FlxgTask & { _ctx?: boolean; _outside?: boolean })[] = [];
    const have = new Set<number>();
    const ctx = new Set<number>();
    for (const x of Object.values(S.tasks)) {
      x._ctx = false;
      if (x.archived || this.hiddenKind(x)) continue;
      const top = opts.includeSub || this.isTop(x);
      if (this.taskMatches(x)) {
        if (top) {
          out.push(x);
          have.add(x.id);
          continue;
        }
        if (opts.context) {
          let p: FlxgTask | undefined = x;
          for (let i = 0; i < 12 && p && !this.isTop(p); i++) p = S.tasks[p.parent];
          if (p && p !== x) ctx.add(p.id);
        }
      }
    }
    for (const id of ctx) {
      if (have.has(id)) continue;
      const x = S.tasks[id]!;
      x._ctx = true;
      out.push(x);
      have.add(id);
    }
    out.forEach((x) => (x._outside = !!(S.keep[x.id] && !this.inScopeTask(x))));
    for (const id of Object.keys(S.keep)) {
      const x = S.tasks[+id];
      if (!x || have.has(x.id) || x.archived || this.hiddenKind(x) || (!opts.includeSub && !this.isTop(x))) continue;
      x._outside = true;
      out.push(x);
    }
    return this.sortTasks(out, this.viewCfg().sort);
  }
  sortTasks<T extends FlxgTask>(arr: T[], sort?: { by: string; dir: string }) {
    const by = sort?.by ?? 'position';
    const dir = sort?.dir === 'desc' ? -1 : 1;
    arr.sort((a, b) => {
      let r = 0;
      if (by === 'due' || by === 'start') {
        const da = a[by] || '';
        const db = b[by] || '';
        if (!da && db) return 1;
        if (da && !db) return -1;
        r = da < db ? -1 : da > db ? 1 : 0;
      } else if (by === 'priority') r = (a.priority || 9) - (b.priority || 9);
      else if (by === 'created' || by === 'updated') r = (a[by] || '') < (b[by] || '') ? -1 : 1;
      else if (by === 'title') r = cmpStr(a.title, b.title);
      else r = a.pos - b.pos || a.id - b.id;
      return r * dir || a.id - b.id;
    });
    return arr;
  }
  groupOptions() {
    const opts = [
      { id: 'status', label: tt('Durum') },
      { id: 'priority', label: tt('Öncelik') },
      { id: 'assignee', label: tt('Atanan') },
      { id: 'due', label: tt('Bitiş tarihi') },
      { id: 'tag', label: tt('Etiket') },
    ];
    if (this.S.scope && this.S.scope.type !== 'list') opts.push({ id: 'list', label: tt('Liste') });
    for (const f of this.scopeFields()) if (f.type === 'dropdown') opts.push({ id: f.key, label: f.name });
    opts.push({ id: 'none', label: tt('Gruplama yok') });
    return opts;
  }
  groups(tasks: FlxgTask[], by = 'status'): Group[] {
    const S = this.S;
    let groups: Group[] = [];
    const map: Record<string, Group> = {};
    const g = (key: string, label: string, color: string, patch: Group['patch'], extra?: Partial<Group>) => {
      if (!map[key]) {
        map[key] = { key, label, color, tasks: [], patch, ...extra };
        groups.push(map[key]);
      }
      return map[key];
    };
    if (by === 'status') {
      for (const s of this.scopeStatuses())
        g('st:' + s.key, s.label, s.color, (task) => {
          const m = this.list(task.list)?.statuses.find((x) => norm(x.label) === s.key);
          return m ? { status: m.key } : null;
        }, { statusType: s.type });
      for (const x of tasks) {
        const st = this.status(x);
        (map['st:' + norm(st.label)] ?? g('st:' + norm(st.label), st.label, st.color, null)).tasks.push(x);
      }
    } else if (by === 'priority') {
      for (const p of S.config.priorities) g('pr:' + p.id, p.label, p.color, () => ({ priority: p.id }));
      g('pr:0', tt('Öncelik yok'), '#cbd5e1', () => ({ priority: 0 }));
      for (const x of tasks) (map['pr:' + (x.priority || 0)] ?? map['pr:0']!).tasks.push(x);
    } else if (by === 'assignee') {
      const seen = new Set<number>();
      tasks.forEach((x) => x.assignees.forEach((u) => seen.add(u)));
      const me = S.me!.id;
      [...seen]
        .sort((a, b) => (a === me ? -1 : b === me ? 1 : cmpStr(S.users[a]?.name, S.users[b]?.name)))
        .forEach((u) => {
          const usr = S.users[u];
          g('as:' + u, usr ? usr.name : tt('Silinmiş kullanıcı'), usr ? usr.color : '#94a3b8', (task, from) => {
            let list = task.assignees.slice();
            if (from?.startsWith('as:')) list = list.filter((z) => z !== +from.slice(3));
            if (!list.includes(u)) list.push(u);
            return { assignees: list };
          }, { user: u });
        });
      g('as:none', tt('Atanmamış'), '#cbd5e1', () => ({ assignees: [] }));
      for (const x of tasks) {
        if (!x.assignees.length) map['as:none']!.tasks.push(x);
        x.assignees.forEach((u) => map['as:' + u]?.tasks.push(x));
      }
    } else if (by === 'due') {
      const td = today();
      const tm = addDays(td, 1);
      const wk = addDays(td, 7);
      const keepTime = (task: FlxgTask) => (task.due && task.due.length > 10 ? task.due.slice(10) : '');
      g('due:overdue', tt('Gecikmiş'), '#dc2626', null);
      g('due:today', tt('Bugün'), '#f59e0b', (task) => ({ due: td + keepTime(task) }));
      g('due:tomorrow', tt('Yarın'), '#3b82f6', (task) => ({ due: tm + keepTime(task) }));
      g('due:week', tt('Bu hafta'), '#8b5cf6', null);
      g('due:later', tt('Daha sonra'), '#64748b', null);
      g('due:none', tt('Tarihsiz'), '#cbd5e1', () => ({ due: '' }));
      for (const x of tasks) {
        const d = x.due ? x.due.slice(0, 10) : '';
        const k = !d ? 'none' : d < td ? 'overdue' : d === td ? 'today' : d === tm ? 'tomorrow' : d <= wk ? 'week' : 'later';
        map['due:' + k]!.tasks.push(x);
      }
    } else if (by === 'tag') {
      for (const tg of this.scopeTags()) g('tg:' + tg.id, tg.name, tg.color, null);
      g('tg:none', tt('Etiketsiz'), '#cbd5e1', null);
      for (const x of tasks) {
        if (!x.tags.length) map['tg:none']!.tasks.push(x);
        x.tags.forEach((id) => map['tg:' + id]?.tasks.push(x));
      }
      groups = groups.filter((gr) => gr.tasks.length || gr.key === 'tg:none');
    } else if (by === 'list') {
      for (const l of this.scopeLists()) g('ls:' + l.id, l.name, l.color, rank(l.role) >= 3 ? () => ({ list: l.id }) : null, { list: l.id });
      for (const x of tasks) map['ls:' + x.list]?.tasks.push(x);
    } else if (by.startsWith('f') && by !== 'none') {
      const col = this.scopeFields().find((f) => f.key === by);
      if (col) {
        const fid = (task: FlxgTask) => col.ids.find((id) => this.fieldsFor(task.list).some((f) => f.id === id));
        for (const c of col.options.choices ?? [])
          g('fv:' + c.id, c.label, c.color, (task) => {
            const id = fid(task);
            return id ? { fields: { [id]: c.id } } : null;
          });
        g('fv:none', tt('Boş'), '#cbd5e1', (task) => {
          const id = fid(task);
          return id ? { fields: { [id]: null } } : null;
        });
        for (const x of tasks) {
          const fv = this.fieldValue(x, col);
          map[fv && map['fv:' + fv.v] ? 'fv:' + fv.v : 'fv:none']!.tasks.push(x);
        }
      }
    }
    if (!groups.length) g('all', tt('Tüm görevler'), '#94a3b8', () => ({})).tasks = tasks;
    return groups;
  }

  /* ---------------- Görev değişiklikleri ---------------- */
  upsertTask(task: FlxgTask & Record<string, unknown>) {
    const old = this.S.tasks[task.id];
    const t = { ...task };
    if (t.hasDesc === undefined && typeof t.description === 'string')
      t.hasDesc = !!t.description.replace(/<img[^>]*>/g, 'x').replace(/<[^>]+>/g, '').trim();
    for (const k of ['description', 'comments_list', 'activity', 'attachments', 'children', 'subtree', 'watchers', 'watching', 'role', 'parentInfo', 'depth', 'depthLimit']) delete t[k];
    if (this.inScopeTask(t) || (this.S.scope?.type === 'home' && old)) this.S.tasks[t.id] = t;
    else delete this.S.tasks[t.id];
  }
  keepTask(id: number) {
    if (id) this.S.keep[id] = 1;
  }
  async patchTask(id: number, patch: Record<string, unknown>, opts: { silent?: boolean } = {}) {
    const S = this.S;
    this.keepTask(id);
    const cur = S.tasks[id];
    const old = cur ? JSON.parse(JSON.stringify(cur)) : null;
    S.saving[id] = (S.saving[id] ?? 0) + 1;
    const done = () => {
      S.saving[id] = Math.max(0, (S.saving[id] ?? 1) - 1);
      if (!S.saving[id]) delete S.saving[id];
    };
    if (cur) {
      for (const [k, v] of Object.entries(patch)) {
        if (k === 'fields') {
          cur.fields = { ...cur.fields };
          for (const [f, fv] of Object.entries(v as Record<string, unknown>)) {
            if (fv === null || fv === '') delete cur.fields[f];
            else cur.fields[f] = fv;
          }
        } else if (k === 'list') cur.list = Number(v);
        else if (k === 'position') cur.pos = Number(v);
        else if (!['tag_names', 'add_assignees', 'remove_assignees', 'detail'].includes(k)) (cur as unknown as Record<string, unknown>)[k] = v;
      }
    }
    if (!opts.silent) this.bump();
    try {
      const r = await this.api.update(id, patch);
      done();
      if (r.task) {
        this.upsertTask(r.task as FlxgTask & Record<string, unknown>);
        this.emit('task-saved', r.task);
      }
      this.bump();
      return r.task;
    } catch (e) {
      done();
      if (old) S.tasks[id] = old;
      this.bump();
      this.onError(e);
      throw e;
    }
  }
  async createTask(data: Record<string, unknown>) {
    const r = await this.api.create(data);
    this.upsertTask(r.task as FlxgTask & Record<string, unknown>);
    this.keepTask(r.task.id);
    this.bump();
    return r.task;
  }
  async deleteTask(id: number) {
    await this.api.remove(id);
    delete this.S.tasks[id];
    this.bump();
  }

  /* ---------------- Canlı güncelleme (hafif yoklama) ---------------- */
  private lastInput = Date.now();
  touchInput() {
    this.lastInput = Date.now();
  }
  private interval() {
    const base = Math.max(15, this.S.config.poll || 45) * 1000;
    if (this.fail) return Math.min(300000, base * 2 ** this.fail);
    return Date.now() - this.lastInput < 120000 ? Math.min(base, 10000) : base;
  }
  schedule(ms?: number) {
    clearTimeout(this.syncTimer);
    if (typeof document !== 'undefined' && document.hidden) return;
    this.syncTimer = setTimeout(() => void this.sync(), ms ?? this.interval());
  }
  async sync() {
    const S = this.S;
    if (this.inFlight || !S.me || (typeof document !== 'undefined' && document.hidden)) return;
    this.inFlight = true;
    const q: Record<string, string | number> = { since: S.since, after: S.lastNotif, ...this.scopeQuery() };
    if (S.openTask) q.task = S.openTask;
    try {
      const d = await this.api.sync(q);
      this.fail = 0;
      let changed = false;
      if (S.since && S.scope) {
        for (const id of d.gone ?? [])
          if (S.tasks[id]) {
            delete S.tasks[id];
            changed = true;
          }
        for (const x of d.tasks ?? []) {
          const o = S.tasks[x.id];
          if (!o || o.updated !== x.updated || o.comments !== x.comments || o.files !== x.files) {
            this.upsertTask(x as FlxgTask & Record<string, unknown>);
            changed = true;
          }
        }
      }
      S.since = d.time;
      if (d.unread !== S.unread) {
        S.unread = d.unread;
        changed = true;
      }
      if (d.notifs?.length) {
        S.lastNotif = Math.max(S.lastNotif, d.notifs[0]!.id);
        this.onNotify?.(d.notifs);
      }
      if (d.rev !== S.rev) this.applyStructure(await this.api.boot());
      if (d.taskChanged) this.emit('task-remote');
      if (changed) this.bump();
    } catch {
      this.fail = Math.min(this.fail + 1, 5);
    } finally {
      this.inFlight = false;
      this.schedule();
    }
  }
}

/** Bileşenin motor sürümüne aboneliği (her değişiklikte yeniden çizilir). */
export function useEngineVersion(e: FlxgEngine) {
  return useSyncExternalStore(e.subscribe, e.version, e.version);
}
