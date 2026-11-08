// Flux Tasks ortak parçaları: simgeler, avatar, durum/öncelik/etiket/tarih, açılır kutu, menü, modal, bildirim balonu.
// WP'deki assets/js/core.js ve views.js'teki karşılıklarıyla aynı sınıf adlarını üretir (flxg.css aynı kalır).
import { createContext, useContext, useEffect, useLayoutEffect, useRef, useState, useSyncExternalStore, type CSSProperties, type ReactNode } from 'react';
import { createPortal } from 'react-dom';
import type { FlxgFile, FlxgTask, FlxgUser } from '@fluxwp/core';
import { useHost } from '../host';
import { addDays, dayKey, parseDay, today, type FlxgEngine } from './engine';
import { tt, flxgLocale } from './i18n';

/* ---------------- Bağlam ---------------- */
export interface Ctx {
  e: FlxgEngine;
  ui: Ui;
}
export const FlxgCtx = createContext<Ctx | null>(null);
export function useYtg() {
  const c = useContext(FlxgCtx);
  if (!c) throw new Error('FlxgCtx missing');
  useSyncExternalStore(c.e.subscribe, c.e.version, c.e.version);
  return c;
}

/* ---------------- Açılır kutu / modal / balon durumu ---------------- */
type PopOpts = { alignRight?: boolean; cls?: string; inline?: boolean; onClose?: () => void };
type Pop = { anchor: HTMLElement; content: ReactNode; opts: PopOpts; key: number };
type ModalAction = { label: string; primary?: boolean; danger?: boolean; onClick?: () => unknown };
type ModalT = { id: number; title: string; body: ReactNode; actions?: ModalAction[]; wide?: boolean; onClose?: () => void };
type Toast = { id: number; msg: string; error?: boolean; action?: { label: string; fn: () => void } };

export class Ui {
  pop: Pop | null = null;
  modals: ModalT[] = [];
  toasts: Toast[] = [];
  lightbox: { files: FlxgFile[]; index: number } | null = null;
  private v = 0;
  private subs = new Set<() => void>();
  private seq = 0;
  subscribe = (f: () => void) => {
    this.subs.add(f);
    return () => this.subs.delete(f);
  };
  version = () => this.v;
  bump() {
    this.v++;
    this.subs.forEach((f) => f());
  }
  openPop(anchor: HTMLElement, content: ReactNode, opts: PopOpts = {}) {
    this.closePop();
    this.pop = { anchor, content, opts, key: ++this.seq };
    this.bump();
  }
  closePop = () => {
    if (!this.pop) return;
    const p = this.pop;
    this.pop = null;
    p.opts.onClose?.();
    this.bump();
  };
  menu(anchor: HTMLElement, items: (MenuItem | null | false)[], opts?: PopOpts) {
    this.openPop(anchor, <Menu items={items.filter(Boolean) as MenuItem[]} ui={this} />, opts);
  }
  modal(m: Omit<ModalT, 'id'>) {
    this.closePop();
    const id = ++this.seq;
    this.modals = [...this.modals, { ...m, id }];
    this.bump();
    return () => this.closeModal(id);
  }
  closeModal(id: number) {
    const m = this.modals.find((x) => x.id === id);
    this.modals = this.modals.filter((x) => x.id !== id);
    m?.onClose?.();
    this.bump();
  }
  confirm(title: string, text: string, okLabel?: string, danger?: boolean) {
    return new Promise<boolean>((resolve) => {
      let done = false;
      this.modal({
        title,
        body: <p className="flxg-muted">{text}</p>,
        actions: [
          { label: tt("Cancel"), onClick: () => ((done = true), resolve(false)) },
          { label: okLabel ?? tt("OK"), primary: !danger, danger, onClick: () => ((done = true), resolve(true)) },
        ],
        onClose: () => !done && resolve(false),
      });
    });
  }
  prompt(title: string, value = '', okLabel?: string) {
    return new Promise<string | null>((resolve) => {
      let done = false;
      let cur = value;
      let close = () => {};
      const ok = () => {
        done = true;
        resolve(cur.trim());
      };
      close = this.modal({
        title,
        body: (
          <input
            className="flxg-input"
            defaultValue={value}
            autoFocus
            onChange={(ev) => (cur = ev.target.value)}
            onKeyDown={(ev) => ev.key === 'Enter' && (ok(), close())}
          />
        ),
        actions: [{ label: tt("Cancel") }, { label: okLabel ?? tt("Save"), primary: true, onClick: ok }],
        onClose: () => !done && resolve(null),
      });
    });
  }
  toast(msg: string, o: { error?: boolean; action?: Toast['action'] } = {}) {
    const id = ++this.seq;
    this.toasts = [...this.toasts, { id, msg, ...o }];
    this.bump();
    setTimeout(() => {
      this.toasts = this.toasts.filter((x) => x.id !== id);
      this.bump();
    }, o.error ? 6000 : 3500);
  }
  fail = (e: unknown) => this.toast(e instanceof Error ? e.message : String(e), { error: true });
}

/* ---------------- Simgeler (WP ile aynı çizimler) ---------------- */
const IC: Record<string, string> = {
  check: 'M20 6 9 17l-5-5',
  eye: 'M2 12s3-7 10-7 10 7 10 7-3 7-10 7S2 12 2 12zM12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z',
  eyeOff: 'M9.88 9.88a3 3 0 1 0 4.24 4.24M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68M6.61 6.61A13.53 13.53 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61M2 2l20 20',
  menu: 'M4 6h16M4 12h16M4 18h16',
  doc: 'M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2zM14 2v6h6M16 13H8M16 17H8M10 9H8',
  printer: 'M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M6 14h12v8H6z',
  meeting: 'M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2zM9 16a2 2 0 1 0 0-4 2 2 0 0 0 0 4zM15 16h2',
  x: 'M18 6 6 18M6 6l12 12',
  plus: 'M12 5v14M5 12h14',
  search: 'M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16zm10 2-4.35-4.35',
  filter: 'M3 4h18l-7 8.5V19l-4 2v-8.5L3 4z',
  list: 'M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01',
  board: 'M4 4h5v16H4zM10 4h5v10h-5zM16 4h4v13h-4z',
  calendar: 'M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z',
  bell: 'M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9M10.3 21a1.94 1.94 0 0 0 3.4 0',
  home: 'M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z',
  user: 'M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z',
  users: 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75',
  flag: 'M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1zM4 22v-7',
  tag: 'M12.6 2.6A2 2 0 0 0 11.2 2H4a2 2 0 0 0-2 2v7.2a2 2 0 0 0 .6 1.4l8.7 8.7a2.4 2.4 0 0 0 3.4 0l6.6-6.6a2.4 2.4 0 0 0 0-3.4zM7.5 8a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1z',
  clock: 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20zM12 6v6l4 2',
  msg: 'M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z',
  clip: 'm21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48',
  image: 'M5 3h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2zM8.5 10a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3zM21 15l-5-5L5 21',
  file: 'M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2zM14 2v6h6',
  dots: 'M12 13a1 1 0 1 0 0-2 1 1 0 0 0 0 2zM19 13a1 1 0 1 0 0-2 1 1 0 0 0 0 2zM5 13a1 1 0 1 0 0-2 1 1 0 0 0 0 2z',
  chevD: 'm6 9 6 6 6-6',
  chevU: 'm18 15-6-6-6 6',
  chevR: 'm9 18 6-6-6-6',
  chevL: 'm15 18-6-6 6-6',
  folder: 'M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2z',
  settings: 'M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2zM12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z',
  chart: 'M3 3v18h18M18 17V9M13 17V5M8 17v-3',
  sparkle: 'M9.94 15.5A2 2 0 0 0 8.5 14.06l-6.14-1.58a.5.5 0 0 1 0-.96L8.5 9.94A2 2 0 0 0 9.94 8.5l1.58-6.14a.5.5 0 0 1 .96 0L14.06 8.5A2 2 0 0 0 15.5 9.94l6.14 1.58a.5.5 0 0 1 0 .96L15.5 14.06a2 2 0 0 0-1.44 1.44l-1.58 6.14a.5.5 0 0 1-.96 0zM20 3v4M22 5h-4',
  trash: 'M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2',
  edit: 'M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z',
  link: 'M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71',
  sidebar: 'M5 3h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2zM9 3v18',
  sort: 'M3 6h18M7 12h10M10 18h4',
  group: 'M3 3h7v7H3zM14 3h7v7h-7zM14 14h7v7h-7zM3 14h7v7H3z',
  columns: 'M12 3v18M3 5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z',
  send: 'm22 2-7 20-4-9-9-4zM22 2 11 13',
  star: 'm12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z',
  inbox: 'M22 12h-6l-2 3h-4l-2-3H2M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z',
  back: 'M19 12H5M12 19l-7-7 7-7',
  upload: 'M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12',
  download: 'M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3',
  subtask: 'M6 3v12a3 3 0 0 0 3 3h9M14 14l4 4-4 4',
  copy: 'M8 8h12v12H8zM16 8V4H4v12h4',
  archive: 'M21 8v13H3V8M1 3h22v5H1zM10 12h4',
  move: 'M5 9l-3 3 3 3M9 5l3-3 3 3M15 19l-3 3-3-3M19 9l3 3-3 3M2 12h20M12 2v20',
  lock: 'M5 11h14v10H5zM8 11V7a4 4 0 0 1 8 0v4',
  bold: 'M6 12h9a4 4 0 0 1 0 8H6zM6 4h7a4 4 0 0 1 0 8H6z',
  italic: 'M19 4h-9M14 20H5M15 4 9 20',
  ul: 'M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01',
  ol: 'M10 6h11M10 12h11M10 18h11M4 6h1v4M4 10h2M6 18H4c0-1 2-2 2-3s-1-1.5-2-1',
  checklist: 'm3 7 2 2 4-4M3 17l2 2 4-4M13 6h8M13 12h8M13 18h8',
  heading: 'M6 12h12M6 20V4M18 20V4',
  quote: 'M3 21c3 0 7-1 7-8V5c0-1.25-.756-2.017-2-2H4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2 1 0 1 0 1 1v1c0 1-1 2-2 2s-1 .008-1 1.031V20c0 1 0 1 1 1zM15 21c3 0 7-1 7-8V5c0-1.25-.757-2.017-2-2h-4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2h.75c0 2.25.25 4-2.75 4v3c0 1 0 1 1 1z',
  code: 'm16 18 6-6-6-6M8 6l-6 6 6 6',
  at: 'M12 16a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM16 8v5a3 3 0 0 0 6 0v-1a10 10 0 1 0-4 8',
  gavel: 'm14 13-7.5 7.5a2.12 2.12 0 0 1-3-3L11 10M16 16l6-6M8 8l6-6M9 7l8 8M21 11l-8-8',
};
export function Ic({ n, s = 16, cls }: { n: string; s?: number; cls?: string }) {
  return (
    <svg viewBox="0 0 24 24" width={s} height={s} fill={n === 'star-fill' ? 'currentColor' : 'none'} stroke="currentColor" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" className={'flxg-ic' + (cls ? ' ' + cls : '')}>
      <path d={IC[n === 'star-fill' ? 'star' : n] ?? IC.dots} />
    </svg>
  );
}

/* ---------------- Biçimlendirme ---------------- */
export function fmtDue(s: string, opts: { full?: boolean } = {}) {
  const d = parseDay(s);
  if (!d) return '';
  const loc = flxgLocale();
  const key = s.slice(0, 10);
  const td = today();
  const time = s.length > 10 ? ' ' + d.toLocaleTimeString(loc, { hour: '2-digit', minute: '2-digit' }) : '';
  if (!opts.full) {
    if (key === td) return tt("Today") + time;
    if (key === addDays(td, 1)) return tt("Tomorrow") + time;
    if (key === addDays(td, -1)) return tt("Yesterday") + time;
  }
  const sameYear = d.getFullYear() === new Date().getFullYear();
  return d.toLocaleDateString(loc, sameYear && !opts.full ? { day: 'numeric', month: 'short' } : { day: 'numeric', month: 'short', year: 'numeric' }) + time;
}
export function dueState(s: string, done: boolean) {
  if (!s || done) return '';
  const key = s.slice(0, 10);
  const td = today();
  if (key < td) return 'overdue';
  if (key === td) return 'today';
  if (key === addDays(td, 1)) return 'soon';
  return '';
}
export function ago(iso?: string | null) {
  if (!iso) return '';
  const loc = flxgLocale();
  const d = new Date(iso);
  const diff = (d.getTime() - Date.now()) / 1000;
  const a = Math.abs(diff);
  const rtf = new Intl.RelativeTimeFormat(loc, { numeric: 'auto' });
  if (a < 45) return tt("just now");
  if (a < 3600) return rtf.format(Math.round(diff / 60), 'minute');
  if (a < 86400) return rtf.format(Math.round(diff / 3600), 'hour');
  if (a < 86400 * 7) return rtf.format(Math.round(diff / 86400), 'day');
  return d.toLocaleDateString(loc, { day: 'numeric', month: 'short', year: d.getFullYear() === new Date().getFullYear() ? undefined : 'numeric' });
}
export const fullTime = (iso?: string | null) => (iso ? new Date(iso).toLocaleString(flxgLocale(), { dateStyle: 'medium', timeStyle: 'short' }) : '');
export function bytes(b?: number | null) {
  if (b === null || b === undefined) return '—';
  const u = ['B', 'KB', 'MB', 'GB'];
  let i = 0;
  let n = Number(b);
  while (n >= 1024 && i < u.length - 1) {
    n /= 1024;
    i++;
  }
  return new Intl.NumberFormat(flxgLocale(), { maximumFractionDigits: i > 1 ? 1 : 0 }).format(n) + ' ' + u[i];
}
export function richText(body: string, users: Record<number, FlxgUser>, meId: number) {
  let esc = String(body || '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]!);
  esc = esc.replace(/@\{(\d+)\}/g, (_, id) => {
    const u = users[+id];
    return '<span class="flxg-mention' + (+id === meId ? ' is-me' : '') + '">@' + (u ? u.name.replace(/[&<>]/g, '') : tt("Deleted user")) + '</span>';
  });
  esc = esc.replace(/(https?:\/\/[^\s<]+[^\s<.,;:!?)\]'"])/g, '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>');
  esc = esc.replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>');
  esc = esc.replace(/`([^`\n]+)`/g, '<code>$1</code>');
  return esc.replace(/\n/g, '<br>');
}
export function fileKind(mime: string, name: string) {
  if (/^image\//.test(mime)) return 'image';
  if (/pdf/.test(mime)) return 'pdf';
  if (/^video\//.test(mime)) return 'video';
  if (/^audio\//.test(mime)) return 'audio';
  if (/sheet|excel|csv/.test(mime) || /\.(xlsx?|csv|ods)$/i.test(name || '')) return 'sheet';
  if (/word|document|text/.test(mime)) return 'doc';
  if (/zip|rar|7z|tar/.test(mime)) return 'zip';
  return 'other';
}

/* ---------------- Dosya kaynağı (uygulamada belirteçle blob) ---------------- */
const fileCache = new Map<string, Promise<string>>();
/** Dosyanın blob adresi (önbellekli). */
export function fileSrc(e: FlxgEngine, scope: string, id: number, size?: string) {
  const key = scope + '|' + id + '|' + (size ?? '');
  let p = fileCache.get(key);
  if (!p) {
    p = e.api.file(id, size).then((b) => URL.createObjectURL(b));
    p.catch(() => fileCache.delete(key));
    fileCache.set(key, p);
  }
  return p;
}
export function useFileSrc(id: number | undefined, size?: string) {
  const { e } = useContext(FlxgCtx)!;
  const host = useHost();
  const [src, setSrc] = useState('');
  useEffect(() => {
    if (!id) return;
    let live = true;
    fileSrc(e, host.scope, id, size).then((s) => live && setSrc(s), () => {});
    return () => {
      live = false;
    };
  }, [id, size, e, host.scope]);
  return src;
}
export async function downloadFile(e: FlxgEngine, f: FlxgFile) {
  const b = await e.api.file(f.id);
  const url = URL.createObjectURL(b);
  const a = document.createElement('a');
  a.href = url;
  a.download = f.name;
  document.body.appendChild(a);
  a.click();
  a.remove();
  setTimeout(() => URL.revokeObjectURL(url), 30000);
}
export function FileImg({ id, size, alt, onClick }: { id: number; size?: string; alt?: string; onClick?: () => void }) {
  const src = useFileSrc(id, size);
  return src ? <img src={src} alt={alt ?? ''} loading="lazy" draggable={false} onClick={onClick} /> : <span className="flxg-sk" style={{ display: 'block', width: '100%', height: '100%' }} />;
}

/* ---------------- Avatar, durum, öncelik, etiket, tarih ---------------- */
export function Avatar({ u, size = 24 }: { u?: FlxgUser | null; size?: number }) {
  const [bad, setBad] = useState(false);
  if (!u)
    return (
      <span className="flxg-av is-empty" style={{ width: size, height: size }}>
        <Ic n="user" s={Math.round(size * 0.6)} />
      </span>
    );
  return (
    <span className="flxg-av" title={u.name} style={{ width: size, height: size, background: u.color, fontSize: Math.round(size * 0.42) }}>
      {u.initials}
      {u.avatar && !bad && <img src={u.avatar} alt="" loading="lazy" onError={() => setBad(true)} />}
    </span>
  );
}
export function Avatars({ ids, size = 24, max = 3 }: { ids: number[]; size?: number; max?: number }) {
  const { e } = useYtg();
  return (
    <span className="flxg-avs">
      {ids.slice(0, max).map((id) => (
        <Avatar key={id} u={e.S.users[id]} size={size} />
      ))}
      {ids.length > max && (
        <span className="flxg-av is-more" style={{ width: size, height: size }}>
          +{ids.length - max}
        </span>
      )}
    </span>
  );
}
const cv = (c: string) => ({ '--c': c }) as CSSProperties;
export { cv };
export function StatusPill({ task, small }: { task: FlxgTask; small?: boolean }) {
  const { e } = useYtg();
  const st = e.status(task);
  return (
    <span className={'flxg-status' + (small ? ' is-sm' : '')} style={cv(st.color)}>
      {st.label}
    </span>
  );
}
export function DueChip({ task, k = 'due' }: { task: FlxgTask; k?: 'due' | 'start' }) {
  const { e } = useYtg();
  const v = task[k];
  if (!v) return null;
  const state = k === 'due' ? dueState(v, e.isDone(task)) : '';
  return (
    <span className={'flxg-due' + (state ? ' is-' + state : '')} title={fmtDue(v, { full: true })}>
      <Ic n={k === 'due' ? 'calendar' : 'clock'} s={13} />
      {fmtDue(v)}
    </span>
  );
}
export function PrioFlag({ n, label }: { n: number; label?: boolean }) {
  const { e } = useYtg();
  const p = e.priority(n);
  if (!p) return null;
  return (
    <span className="flxg-prio" style={cv(p.color)} title={p.label}>
      <Ic n="flag" s={13} />
      {label ? p.label : null}
    </span>
  );
}
export function TagChips({ ids, max }: { ids: number[]; max?: number }) {
  const { e } = useYtg();
  return (
    <span className="flxg-tags">
      {ids.slice(0, max ?? 99).map((id) => {
        const tg = e.tag(id);
        return tg ? (
          <span key={id} className="flxg-tag" style={cv(tg.color)}>
            {tg.name}
          </span>
        ) : null;
      })}
      {max && ids.length > max ? <span className="flxg-tag is-more">+{ids.length - max}</span> : null}
    </span>
  );
}

/* ---------------- Menü ---------------- */
export type MenuItem = { label?: string; icon?: string; swatch?: string; onClick?: () => void; danger?: boolean; checked?: boolean; disabled?: boolean; sep?: boolean; head?: string };
function Menu({ items, ui }: { items: MenuItem[]; ui: Ui }) {
  return (
    <div className="flxg-menu">
      {items.map((it, i) =>
        it.sep ? (
          <div key={i} className="flxg-menu-sep" />
        ) : it.head ? (
          <div key={i} className="flxg-menu-head">
            {it.head}
          </div>
        ) : (
          <button
            key={i}
            type="button"
            disabled={it.disabled}
            className={'flxg-menu-item' + (it.danger ? ' is-danger' : '') + (it.checked ? ' is-on' : '')}
            onClick={() => {
              ui.closePop();
              it.onClick?.();
            }}
          >
            {it.swatch ? <span className="flxg-swatch" style={{ background: it.swatch }} /> : it.icon ? <Ic n={it.icon} /> : null}
            <span>{it.label}</span>
            {it.checked && <Ic n="check" s={14} cls="flxg-menu-check" />}
          </button>
        ),
      )}
    </div>
  );
}

/* ---------------- Katman: açılır kutu, modal, balon, görsel önizleme ---------------- */
export function Layer({ theme }: { theme: string }) {
  const { ui, e } = useContext(FlxgCtx)!;
  useSyncExternalStore(ui.subscribe, ui.version, ui.version);
  const [host, setHost] = useState<HTMLElement | null>(null);
  return (
    <div className="flxg-layer" ref={setHost}>
      {host && ui.pop && <PopBox key={ui.pop.key} pop={ui.pop} ui={ui} theme={theme} />}
      {ui.modals.map((m) => (
        <ModalBox key={m.id} m={m} ui={ui} />
      ))}
      {ui.toasts.length > 0 &&
        createPortal(
          <div className="flxg-toasts" data-theme={theme}>
            {ui.toasts.map((t) => (
              <div key={t.id} className={'flxg-toast' + (t.error ? ' is-error' : '')}>
                <span>{t.msg}</span>
                {t.action && (
                  <button type="button" className="flxg-toast-btn" onClick={t.action.fn}>
                    {t.action.label}
                  </button>
                )}
              </div>
            ))}
          </div>,
          host ?? document.body,
        )}
      {ui.lightbox && <Lightbox e={e} ui={ui} />}
    </div>
  );
}
function PopBox({ pop, ui, theme }: { pop: Pop; ui: Ui; theme: string }) {
  const ref = useRef<HTMLDivElement>(null);
  const [pos, setPos] = useState<{ left: number; top: number } | null>(null);
  useLayoutEffect(() => {
    const el = ref.current!;
    const r = pop.anchor.getBoundingClientRect();
    const w = el.offsetWidth;
    const hh = el.offsetHeight;
    let left = pop.opts.alignRight ? r.right - w : r.left;
    left = Math.max(8, Math.min(left, window.innerWidth - w - 8));
    let top = r.bottom + 4;
    if (top + hh > window.innerHeight - 8) top = Math.max(8, r.top - hh - 4);
    setPos({ left, top });
    const first = el.querySelector<HTMLInputElement>('input:not([type=checkbox]):not([type=radio]):not([type=hidden]),textarea');
    if (first && !pop.opts.inline) setTimeout(() => first.focus(), 10);
  }, [pop]);
  useEffect(() => {
    const down = (ev: MouseEvent) => {
      const t = ev.target as Node;
      if (ref.current?.contains(t) || pop.anchor.contains(t)) return;
      ui.closePop();
    };
    const key = (ev: KeyboardEvent) => {
      if (ev.key === 'Escape') {
        ev.stopPropagation();
        ui.closePop();
      }
    };
    document.addEventListener('mousedown', down, true);
    document.addEventListener('keydown', key, true);
    return () => {
      document.removeEventListener('mousedown', down, true);
      document.removeEventListener('keydown', key, true);
    };
  }, [pop, ui]);
  return (
    <div ref={ref} role="dialog" data-theme={theme} className={'flxg-pop' + (pop.opts.cls ? ' ' + pop.opts.cls : '')} style={pos ? { left: pos.left, top: pos.top } : { left: -9999, top: 0 }}>
      {pop.content}
    </div>
  );
}
function ModalBox({ m, ui }: { m: ModalT; ui: Ui }) {
  const [busy, setBusy] = useState(false);
  useEffect(() => {
    const key = (ev: KeyboardEvent) => {
      if (ev.key === 'Escape' && !ui.pop) {
        ev.stopPropagation();
        ui.closeModal(m.id);
      }
    };
    document.addEventListener('keydown', key, true);
    return () => document.removeEventListener('keydown', key, true);
  }, [m, ui]);
  return (
    <div className="flxg-modal-shade" onMouseDown={(ev) => ev.target === ev.currentTarget && ui.closeModal(m.id)}>
      <div className={'flxg-modal' + (m.wide ? ' is-wide' : '')} role="dialog" aria-modal="true" aria-label={m.title}>
        <div className="flxg-modal-head">
          <h2>{m.title}</h2>
          <button type="button" className="flxg-icon-btn" aria-label={tt("Close")} onClick={() => ui.closeModal(m.id)}>
            <Ic n="x" s={18} />
          </button>
        </div>
        <div className="flxg-modal-body">{m.body}</div>
        {m.actions && (
          <div className="flxg-modal-foot">
            {m.actions.map((a, i) => (
              <button
                key={i}
                type="button"
                disabled={busy}
                className={'flxg-btn' + (a.primary ? ' is-primary' : '') + (a.danger ? ' is-danger' : '')}
                onClick={() => {
                  const r = a.onClick?.();
                  if (r instanceof Promise) {
                    setBusy(true);
                    r.then(
                      (ok) => (ok !== false ? ui.closeModal(m.id) : setBusy(false)),
                      (er) => {
                        setBusy(false);
                        ui.fail(er);
                      },
                    );
                    return;
                  }
                  if (r === false) return;
                  ui.closeModal(m.id);
                }}
              >
                {a.label}
              </button>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
function Lightbox({ e, ui }: { e: FlxgEngine; ui: Ui }) {
  const lb = ui.lightbox!;
  const [i, setI] = useState(lb.index);
  const f = lb.files[i]!;
  const src = useFileSrc(f.id);
  const close = () => {
    ui.lightbox = null;
    ui.bump();
  };
  useEffect(() => {
    const key = (ev: KeyboardEvent) => {
      if (ev.key === 'Escape') {
        ev.stopPropagation();
        close();
      }
      if (ev.key === 'ArrowRight') setI((x) => (x + 1) % lb.files.length);
      if (ev.key === 'ArrowLeft') setI((x) => (x - 1 + lb.files.length) % lb.files.length);
    };
    document.addEventListener('keydown', key, true);
    return () => document.removeEventListener('keydown', key, true);
  });
  return (
    <div className="flxg-lightbox" onClick={(ev) => ev.target === ev.currentTarget && close()}>
      <button type="button" className="flxg-lb-close" aria-label={tt("Close")} onClick={close}>
        <Ic n="x" s={22} />
      </button>
      {lb.files.length > 1 && (
        <>
          <button type="button" className="flxg-lb-nav is-prev" onClick={() => setI((i - 1 + lb.files.length) % lb.files.length)}>
            <Ic n="chevL" s={26} />
          </button>
          <button type="button" className="flxg-lb-nav is-next" onClick={() => setI((i + 1) % lb.files.length)}>
            <Ic n="chevR" s={26} />
          </button>
        </>
      )}
      {src && <img src={src} alt="" />}
      <div className="flxg-lb-cap">
        <span>
          {f.name} · {bytes(f.size)}
        </span>
        <button type="button" className="flxg-btn is-ghost" onClick={() => void downloadFile(e, f).catch(ui.fail)}>
          <Ic n="download" />
          {tt("Download")}
        </button>
      </div>
    </div>
  );
}

/* ---------------- Seçiciler ---------------- */
export function pickStatus(c: Ctx, anchor: HTMLElement, listId: number, current: string, onPick: (k: string) => void) {
  const l = c.e.list(listId);
  if (!l) return;
  c.ui.menu(anchor, [{ head: tt("Status") }, ...l.statuses.map((s) => ({ label: s.label, swatch: s.color, checked: s.key === current, onClick: () => onPick(s.key) }))]);
}
export function pickPriority(c: Ctx, anchor: HTMLElement, current: number, onPick: (n: number) => void) {
  c.ui.menu(anchor, [
    { head: tt("Priority") },
    ...c.e.S.config.priorities.map((p) => ({ label: p.label, swatch: p.color, checked: +current === p.id, onClick: () => onPick(p.id) })),
    { sep: true },
    { label: tt("Remove priority"), icon: 'x', onClick: () => onPick(0) },
  ]);
}
export function pickList(c: Ctx, anchor: HTMLElement, onPick: (id: number) => void, opts: { current?: number; need?: string } = {}) {
  const { e } = c;
  const items: MenuItem[] = [];
  for (const sp of e.S.spaces) {
    const ls = e.S.lists.filter((l) => l.space_id === sp.id && (({ view: 1, comment: 2, edit: 3, admin: 4 } as Record<string, number>)[l.role] ?? 0) >= (opts.need === 'view' ? 1 : 3));
    if (!ls.length) continue;
    items.push({ head: sp.name });
    for (const l of ls) {
      const f = l.folder_id ? e.folder(l.folder_id) : null;
      items.push({ label: (f ? f.name + ' / ' : '') + l.name, swatch: l.color, checked: opts.current === l.id, onClick: () => onPick(l.id) });
    }
  }
  if (!items.length) items.push({ head: tt("There are no lists you can add tasks to.") });
  c.ui.menu(anchor, items);
}
export function pickPeople(c: Ctx, anchor: HTMLElement, selected: number[], onChange: (ids: number[]) => void, opts: { single?: boolean } = {}) {
  c.ui.openPop(anchor, <PeoplePick c={c} selected={selected} onChange={onChange} single={opts.single} />);
}
function PeoplePick({ c, selected, onChange, single }: { c: Ctx; selected: number[]; onChange: (ids: number[]) => void; single?: boolean }) {
  const { e, ui } = c;
  const [q, setQ] = useState('');
  const [sel, setSel] = useState(selected);
  const me = e.S.me!.id;
  const nq = q.toLocaleLowerCase('tr');
  const users = e.S.userList
    .slice()
    .sort((a, b) => {
      let sa = sel.includes(a.id) ? 0 : 1;
      let sb = sel.includes(b.id) ? 0 : 1;
      if (a.id === me) sa -= 0.5;
      if (b.id === me) sb -= 0.5;
      return sa - sb || a.name.localeCompare(b.name, 'tr');
    })
    .filter((u) => !nq || u.name.toLocaleLowerCase('tr').includes(nq));
  return (
    <div className="flxg-pick">
      <input className="flxg-input is-sm" type="search" placeholder={tt("Search people…")} value={q} onChange={(ev) => setQ(ev.target.value)} />
      <div className="flxg-pick-list">
        {users.map((u) => {
          const on = sel.includes(u.id);
          return (
            <button
              key={u.id}
              type="button"
              className={'flxg-pick-item' + (on ? ' is-on' : '')}
              onClick={() => {
                if (single) {
                  const n = on ? [] : [u.id];
                  onChange(n);
                  ui.closePop();
                  return;
                }
                const n = on ? sel.filter((x) => x !== u.id) : [...sel, u.id];
                setSel(n);
                onChange(n);
              }}
            >
              <Avatar u={u} size={24} />
              <span>{u.name + (u.id === me ? ' (' + tt("me") + ')' : '')}</span>
              {on && <Ic n="check" s={14} cls="flxg-menu-check" />}
            </button>
          );
        })}
        {!users.length && <div className="flxg-empty-sm">{tt("No results")}</div>}
      </div>
    </div>
  );
}
export function pickDate(c: Ctx, anchor: HTMLElement, value: string, onPick: (v: string) => void, opts: { label?: string } = {}) {
  c.ui.openPop(anchor, <DatePick c={c} value={value} onPick={onPick} label={opts.label} />, { inline: true });
}
function DatePick({ c, value, onPick, label }: { c: Ctx; value: string; onPick: (v: string) => void; label?: string }) {
  const td = today();
  const [day, setDay] = useState(value ? value.slice(0, 10) : '');
  const [time, setTime] = useState(value && value.length > 10 ? value.slice(11, 16) : '');
  const emit = (d: string, tm?: string) => {
    onPick(d ? d + (tm ? 'T' + tm : '') : '');
    c.ui.closePop();
  };
  const d = new Date();
  d.setDate(d.getDate() + ((8 - d.getDay()) % 7 || 7));
  const quick: [string, string][] = [
    [tt("Today"), td],
    [tt("Tomorrow"), addDays(td, 1)],
    [tt("Next week"), dayKey(d)],
    [tt("In 2 weeks"), addDays(td, 14)],
  ];
  return (
    <div className="flxg-datepick">
      <div className="flxg-datepick-quick">
        {quick.map((q) => (
          <button key={q[0]} type="button" className="flxg-chip-btn" onClick={() => emit(q[1], time)}>
            {q[0]}
          </button>
        ))}
      </div>
      <label className="flxg-field">
        <span>{label ?? tt("Date")}</span>
        <input className="flxg-input is-sm" type="date" value={day} onChange={(ev) => setDay(ev.target.value)} onKeyDown={(ev) => ev.key === 'Enter' && emit(day, time)} />
      </label>
      <label className="flxg-field">
        <span>{tt("Time (optional)")}</span>
        <input className="flxg-input is-sm" type="time" value={time} onChange={(ev) => setTime(ev.target.value)} />
      </label>
      <div className="flxg-row-btns">
        {value && (
          <button type="button" className="flxg-btn is-ghost" onClick={() => emit('')}>
            {tt("Clear")}
          </button>
        )}
        <button type="button" className="flxg-btn is-primary" onClick={() => emit(day, time)}>
          {tt("Apply")}
        </button>
      </div>
    </div>
  );
}
export function pickTags(c: Ctx, anchor: HTMLElement, spaceId: number, selected: number[], onChange: (ids: number[]) => void) {
  c.ui.openPop(anchor, <TagPick c={c} spaceId={spaceId} selected={selected} onChange={onChange} />);
}
function TagPick({ c, spaceId, selected, onChange }: { c: Ctx; spaceId: number; selected: number[]; onChange: (ids: number[]) => void }) {
  const { e, ui } = c;
  const [q, setQ] = useState('');
  const [sel, setSel] = useState(selected);
  const [, force] = useState(0);
  const nq = q.trim().toLocaleLowerCase('tr');
  const tags = e.S.tags.filter((x) => x.space_id === spaceId && (!nq || x.name.toLocaleLowerCase('tr').includes(nq)));
  const canCreate = q.trim() && !e.S.tags.some((x) => x.space_id === spaceId && x.name.toLocaleLowerCase('tr') === nq);
  const create = () =>
    e.api.tag(spaceId, q.trim()).then((r) => {
      e.S.tags = r.tags;
      const n = [...sel, r.tag.id];
      setSel(n);
      onChange(n);
      setQ('');
      force((x) => x + 1);
      e.bump();
    }, ui.fail);
  return (
    <div className="flxg-pick">
      <input
        className="flxg-input is-sm"
        type="search"
        placeholder={tt("Search or create a tag…")}
        value={q}
        onChange={(ev) => setQ(ev.target.value)}
        onKeyDown={(ev) => ev.key === 'Enter' && canCreate && void create()}
      />
      <div className="flxg-pick-list">
        {tags.map((tg) => {
          const on = sel.includes(tg.id);
          return (
            <button
              key={tg.id}
              type="button"
              className={'flxg-pick-item' + (on ? ' is-on' : '')}
              onClick={() => {
                const n = on ? sel.filter((x) => x !== tg.id) : [...sel, tg.id];
                setSel(n);
                onChange(n);
              }}
            >
              <span className="flxg-tag" style={cv(tg.color)}>
                {tg.name}
              </span>
              {on && <Ic n="check" s={14} cls="flxg-menu-check" />}
            </button>
          );
        })}
        {canCreate && (
          <button type="button" className="flxg-pick-item is-create" onClick={() => void create()}>
            <Ic n="plus" />
            <span>{tt("Create: {n}", { n: q.trim() })}</span>
          </button>
        )}
        {!tags.length && !canCreate && <div className="flxg-empty-sm">{tt("No tags yet. Type to create one.")}</div>}
      </div>
    </div>
  );
}

/** Basit gecikmeli çağrı (seçicilerde art arda değişiklik tek kayıt olur). */
export function debounce<A extends unknown[]>(fn: (...a: A) => void, ms: number) {
  let tm: ReturnType<typeof setTimeout> | undefined;
  return (...a: A) => {
    clearTimeout(tm);
    tm = setTimeout(() => fn(...a), ms);
  };
}
