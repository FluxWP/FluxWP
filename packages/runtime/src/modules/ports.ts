import { appResources, normalizeLocale } from '../i18n';
// Platform ports for shared module engines (WhatsApp, Tasks, …).
// Desktop/web uses the document + localStorage adapter; React Native passes AppState + MMKV.
// Engines must not touch `document` or `localStorage` themselves.

export interface SyncStore {
  get(key: string): string | null;
  set(key: string, value: string | null): void;
}

/** Desktop tasks views also read JSON blobs (`store.json`). */
export interface TaskStore extends SyncStore {
  json<T>(key: string): T | null;
}

export type LabelFn = (text: string, vars?: Record<string, string | number>) => string;

export interface NotifyPayload {
  title: string;
  body: string;
  icon?: string;
  tag?: string;
  onClick?: () => void;
}

export interface ModulePorts {
  visible(): boolean;
  /** Fires when the app moves between foreground and background. Returns an unsubscribe. */
  onVisibility(fn: (visible: boolean) => void): () => void;
  storage: SyncStore;
  /** Preview URL for a just-picked file (blob: on the web, empty when the platform has none). */
  objectUrl(blob: Blob): string;
  focus(): void;
  beep(): void;
  showNotification(n: NotifyPayload): void;
  /** Group labels inside the tasks engine. Turkish source string in, localized string out. */
  t: LabelFn;
}

const fill = (text: string, vars?: Record<string, string | number>) => {
  let out = text;
  if (vars) for (const [k, v] of Object.entries(vars)) out = out.split('{' + k + '}').join(String(v));
  return out;
};

/** Tasks engine group labels. Desktop replaces this with its full dictionary (`setTaskLabels`). */
const taskEn: Record<string, string> = {
  Durum: 'Status',
  Öncelik: 'Priority',
  Atanan: 'Assignee',
  'Bitiş tarihi': 'Due date',
  Etiket: 'Tag',
  Liste: 'List',
  'Gruplama yok': 'No grouping',
  'Öncelik yok': 'No priority',
  'Silinmiş kullanıcı': 'Deleted user',
  Atanmamış: 'Unassigned',
  Gecikmiş: 'Overdue',
  Bugün: 'Today',
  Yarın: 'Tomorrow',
  'Bu hafta': 'This week',
  'Daha sonra': 'Later',
  Tarihsiz: 'No date',
  Etiketsiz: 'No tag',
  Boş: 'Empty',
  'Tüm görevler': 'All tasks',
};

let taskLabel: LabelFn = (text, vars) => fill(text, vars);

/** Desktop calls this once so the engine uses the same dictionary as the panel. */
export function setTaskLabels(fn: LabelFn): void {
  taskLabel = fn;
}

export function taskText(locale: string, text: string, vars?: Record<string, string | number>): string {
  const lang = normalizeLocale(locale);
  const key = taskEn[text] ?? text;
  const tree = appResources[lang].translation as { tasks?: { engineLabels?: Record<string,string> } };
  return fill(tree.tasks?.engineLabels?.[key] ?? (lang === 'tr' ? text : taskLabel(key)), vars);
}

const memory = new Map<string, string>();

export function memoryStore(seed: Record<string, string> = {}): SyncStore {
  const box = new Map<string, string>(Object.entries(seed));
  return {
    get: (k) => box.get(k) ?? null,
    set: (k, v) => {
      if (v === null) box.delete(k);
      else box.set(k, v);
    },
  };
}

type Dom = {
  hidden: boolean;
  addEventListener(type: string, fn: () => void): void;
  removeEventListener(type: string, fn: () => void): void;
};
type WebStore = { getItem(k: string): string | null; setItem(k: string, v: string): void; removeItem(k: string): void };
type WebWindow = { focus(): void };

/** DOM globals via bracket access so this file stays free of direct document/window/localStorage property reads. */
function webGlobals(): { doc?: Dom; store?: WebStore; win?: WebWindow } {
  const g = globalThis as Record<string, unknown>;
  return {
    doc: g['document'] as Dom | undefined,
    store: g['localStorage'] as WebStore | undefined,
    win: g['window'] as WebWindow | undefined,
  };
}

/** Browser / desktop adapter. Safe to construct where the DOM does not exist (then always visible). */
export function browserPorts(over: Partial<ModulePorts> = {}): ModulePorts {
  const storage: SyncStore = {
    get(k) {
      try {
        const store = webGlobals().store;
        return store ? store.getItem('flxg-' + k) : (memory.get(k) ?? null);
      } catch {
        return null;
      }
    },
    set(k, v) {
      try {
        const store = webGlobals().store;
        if (!store) {
          if (v === null) memory.delete(k);
          else memory.set(k, v);
          return;
        }
        if (v === null) store.removeItem('flxg-' + k);
        else store.setItem('flxg-' + k, v);
      } catch {
        /* private mode */
      }
    },
  };
  return {
    visible: () => {
      const doc = webGlobals().doc;
      return !doc || !doc.hidden;
    },
    onVisibility(fn) {
      const doc = webGlobals().doc;
      if (!doc) return () => undefined;
      const h = () => fn(!webGlobals().doc?.hidden);
      doc.addEventListener('visibilitychange', h);
      return () => doc.removeEventListener('visibilitychange', h);
    },
    storage,
    objectUrl: (blob) => (typeof URL !== 'undefined' && typeof URL.createObjectURL === 'function' ? URL.createObjectURL(blob) : ''),
    focus: () => {
      try {
        webGlobals().win?.focus();
      } catch {
        /* no window */
      }
    },
    beep() {
      try {
        const Ctx = typeof AudioContext !== 'undefined' ? AudioContext : undefined;
        if (!Ctx) return;
        const ctx = new Ctx();
        const o = ctx.createOscillator();
        const g = ctx.createGain();
        o.frequency.value = 880;
        g.gain.setValueAtTime(0.08, ctx.currentTime);
        g.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.25);
        o.connect(g).connect(ctx.destination);
        o.start();
        o.stop(ctx.currentTime + 0.26);
      } catch {
        /* no audio */
      }
    },
    showNotification(n) {
      if (typeof Notification === 'undefined') return;
      const show = () => {
        const x = new Notification(n.title, { body: n.body, icon: n.icon, tag: n.tag });
        x.onclick = () => n.onClick?.();
      };
      if (Notification.permission === 'granted') show();
      else if (Notification.permission === 'default') void Notification.requestPermission().then((p) => p === 'granted' && show());
    },
    t: (text, vars) => taskLabel(text, vars),
    ...over,
  };
}

/** In-memory ports for tests. `setVisible` fires subscribers. */
export function memoryPorts(opts: { visible?: boolean; store?: SyncStore } = {}): ModulePorts & { setVisible(v: boolean): void } {
  let visible = opts.visible ?? true;
  const subs = new Set<(v: boolean) => void>();
  return {
    visible: () => visible,
    setVisible(v) {
      visible = v;
      subs.forEach((fn) => fn(v));
    },
    onVisibility(fn) {
      subs.add(fn);
      return () => subs.delete(fn);
    },
    storage: opts.store ?? memoryStore(),
    objectUrl: () => 'blob:memory',
    focus: () => undefined,
    beep: () => undefined,
    showNotification: () => undefined,
    t: (text, vars) => taskText('tr', text, vars),
  };
}

function withJson(s: SyncStore): TaskStore {
  return {
    get: (k) => s.get(k),
    set: (k, v) => s.set(k, v),
    json<T>(k: string): T | null {
      try {
        return JSON.parse(s.get(k) ?? 'null') as T;
      } catch {
        return null;
      }
    },
  };
}

/** The tasks panel reads and writes this store (`flxg-*` keys). Same object the desktop views import. */
export const store: TaskStore = withJson(browserPorts().storage);
