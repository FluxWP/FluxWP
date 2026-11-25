// Klasik Flux Chat görünümünün ortak parçaları: dil, avatar, açılır menü, pencere, ışık kutusu.
// İşaretleme ve sınıflar eski uygulamayla (flx-chat/assets/js/app.js) aynıdır; stil flxc.css (eski app.css).
import { createContext, useCallback, useContext, useEffect, useLayoutEffect, useRef, useState, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { EN } from './strings';
import { I, type IconName } from './icons';

/* ---------- dil: kaynak Türkçe (eski uygulamadaki metinler), diğer dillerde İngilizce ---------- */

export type L = (s: string, vars?: Record<string, string | number>) => string;

export function useL(): { L: L; lang: string } {
  const { i18n } = useTranslation();
  const lang = i18n.language || 'tr';
  const tr = /^tr\b/i.test(lang);
  const L = useCallback<L>(
    (s, vars) => {
      let out = tr ? s : (EN[s] ?? s);
      if (vars) for (const k of Object.keys(vars)) out = out.split('{' + k + '}').join(String(vars[k]));
      return out;
    },
    [tr],
  );
  return { L, lang: tr ? 'tr-TR' : lang };
}

export function store(k: string, v?: string): string | null {
  try {
    if (v === undefined) return localStorage.getItem('flxc_' + k);
    localStorage.setItem('flxc_' + k, v);
  } catch {
    /* depolama kapalı */
  }
  return null;
}

/* ---------- avatar ---------- */

const AV_COLORS = ['#25a18e', '#5b8def', '#e57373', '#f0a04b', '#9c6ade', '#26a69a', '#ec7aa3', '#7cb342', '#ef8354', '#4fa3d1'];
function hash(s: string) {
  let x = 0;
  for (let i = 0; i < s.length; i++) x = ((x << 5) - x + s.charCodeAt(i)) | 0;
  return x;
}
export function initials(name: string, loc = 'tr-TR') {
  const parts = String(name || '?').replace(/[^\p{L}\p{N}\s]/gu, '').trim().split(/\s+/);
  const s = (parts[0] || '?').charAt(0) + (parts.length > 1 ? parts[parts.length - 1]!.charAt(0) : '');
  return s.toLocaleUpperCase(loc) || '#';
}

export interface AvChat {
  id?: number | string;
  title?: string;
  avatar?: string;
  unknownName?: boolean;
  pushName?: string;
}

export function Av({ c, size }: { c: AvChat; size?: 'sm' | 'xl' }) {
  const [broken, setBroken] = useState(false);
  const name = c.title || '';
  const col = AV_COLORS[Math.abs(hash(String(c.id || name))) % AV_COLORS.length];
  const img = c.avatar && !broken;
  return (
    <span className={'flxc-av' + (size ? ' flxc-av-' + size : '')} style={img ? undefined : { background: col }}>
      {img ? (
        <img src={c.avatar} alt="" loading="lazy" referrerPolicy="no-referrer" onError={() => setBroken(true)} />
      ) : c.unknownName && !c.pushName ? (
        <I n="person" />
      ) : (
        <span>{initials(name)}</span>
      )}
    </span>
  );
}

/* ---------- açılır menü (kök kutuya göre konumlanır) ---------- */

export interface MenuItem {
  icon?: IconName;
  label?: string;
  fn?: () => void;
  danger?: boolean;
  on?: boolean;
  sep?: boolean;
  node?: ReactNode;
}
export interface MenuOpts {
  cls?: string;
  at?: { x: number; y: number } | null;
  alignLeft?: boolean;
}
interface MenuState {
  anchor: HTMLElement;
  items: (MenuItem | null | false | undefined)[];
  opts: MenuOpts;
}

interface Ui {
  root: HTMLDivElement | null;
  menu: (anchor: HTMLElement, items: MenuState['items'], opts?: MenuOpts) => void;
  closeMenu: () => void;
  toast: (msg: string, kind?: 'err') => void;
}
export const UiCtx = createContext<Ui>({ root: null, menu: () => undefined, closeMenu: () => undefined, toast: () => undefined });
export const useUi = () => useContext(UiCtx);

export function useMenuHost(root: HTMLDivElement | null) {
  const [m, setM] = useState<MenuState | null>(null);
  const menu = useCallback((anchor: HTMLElement, items: MenuState['items'], opts: MenuOpts = {}) => setM({ anchor, items, opts }), []);
  const closeMenu = useCallback(() => setM(null), []);
  const node = m && root ? <MenuBox st={m} root={root} close={closeMenu} /> : null;
  return { menu, closeMenu, node, open: !!m };
}

function MenuBox({ st, root, close }: { st: MenuState; root: HTMLDivElement; close: () => void }) {
  const ref = useRef<HTMLDivElement>(null);
  useLayoutEffect(() => {
    const el = ref.current;
    if (!el) return;
    const rr = root.getBoundingClientRect();
    const ra = st.anchor.getBoundingClientRect();
    const at = st.opts.at;
    let r = at ? { left: at.x, right: at.x, top: at.y, bottom: at.y } : { left: ra.left, right: ra.right, top: ra.top, bottom: ra.bottom };
    r = { left: r.left - rr.left, right: r.right - rr.left, top: r.top - rr.top, bottom: r.bottom - rr.top };
    const mw = el.offsetWidth;
    const mh = el.offsetHeight;
    let left = at ? r.left : r.right - mw;
    if (st.opts.alignLeft) left = r.left;
    if (at && left + mw > rr.width - 8) left = r.left - mw;
    left = Math.min(Math.max(8, left), rr.width - mw - 8);
    let top = r.bottom + (at ? 2 : 4);
    if (top + mh > rr.height - 8) top = r.top - mh - (at ? 2 : 4);
    top = Math.min(Math.max(8, top), Math.max(8, rr.height - mh - 8));
    el.style.left = left + 'px';
    el.style.top = top + 'px';
  }, [st, root]);
  useEffect(() => {
    const click = (e: MouseEvent) => {
      const t = e.target as Node;
      if (ref.current?.contains(t) || st.anchor.contains(t)) return;
      close();
    };
    const key = (e: KeyboardEvent) => e.key === 'Escape' && close();
    document.addEventListener('click', click);
    document.addEventListener('keydown', key);
    return () => {
      document.removeEventListener('click', click);
      document.removeEventListener('keydown', key);
    };
  }, [st, close]);
  return (
    <div ref={ref} className={'flxc-menu' + (st.opts.cls ? ' ' + st.opts.cls : '')} role="menu">
      {st.items.map((it, i) => {
        if (!it) return null;
        if (it.sep) return <hr key={i} />;
        if (it.node) return <div key={i} style={{ display: 'contents' }}>{it.node}</div>;
        return (
          <button
            key={i}
            type="button"
            role="menuitem"
            className={'flxc-menu-i' + (it.danger ? ' is-danger' : '') + (it.on ? ' is-on' : '')}
            onClick={(e) => {
              e.stopPropagation();
              close();
              it.fn?.();
            }}
          >
            {it.icon && <I n={it.icon} />}
            <span>{it.label}</span>
            {it.on && <I n="check" cls="flxc-menu-check" />}
          </button>
        );
      })}
    </div>
  );
}

/* ---------- düğme ---------- */

export function Ib({ n, title, onClick, cls, children }: { n: IconName; title: string; onClick?: (e: React.MouseEvent<HTMLButtonElement>) => void; cls?: string; children?: ReactNode }) {
  return (
    <button type="button" className={'flxc-ib' + (cls ? ' ' + cls : '')} title={title} aria-label={title} onClick={onClick}>
      <I n={n} />
      {children}
    </button>
  );
}

/* ---------- pencere ---------- */

export function Modal({ title, cls, onClose, children, foot, head }: { title: string; cls?: string; onClose: () => void; children: ReactNode; foot?: ReactNode; head?: ReactNode }) {
  const { L } = useL();
  useEffect(() => {
    const k = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        e.stopPropagation();
        onClose();
      }
    };
    document.addEventListener('keydown', k, true);
    return () => document.removeEventListener('keydown', k, true);
  }, [onClose]);
  return (
    <div className="flxc-modal-back" onMouseDown={(e) => e.target === e.currentTarget && onClose()}>
      <div className={'flxc-modal ' + (cls || '')} role="dialog" aria-modal="true" aria-label={title}>
        <header className="flxc-modal-head">
          <Ib n="close" title={L('Kapat')} onClick={onClose} />
          <strong>{title}</strong>
          {head}
        </header>
        <div className="flxc-modal-body">{children}</div>
        {foot && <footer className="flxc-modal-foot">{foot}</footer>}
      </div>
    </div>
  );
}

/* ---------- WhatsApp biçimi (eski waFormat ile aynı kurallar) ---------- */

const esc = (s: string) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
export function waFormat(text: string): string {
  let s = esc(text);
  const codes: string[] = [];
  s = s.replace(/```([\s\S]+?)```/g, (_m, c: string) => (codes.push(c), '\u0000' + (codes.length - 1) + '\u0000'));
  s = s.replace(/(https?:\/\/[^\s<]+[^\s<.,:;"')\]!?*_~])/g, '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>');
  s = s.replace(/(^|[\s(>])\*(?!\s)([^*\n]+?)\*(?=$|[\s).,!?:;<])/g, '$1<strong>$2</strong>');
  s = s.replace(/(^|[\s(>])_(?!\s)([^_\n]+?)_(?=$|[\s).,!?:;<])/g, '$1<em>$2</em>');
  s = s.replace(/(^|[\s(>])~(?!\s)([^~\n]+?)~(?=$|[\s).,!?:;<])/g, '$1<del>$2</del>');
  s = s.replace(/\u0000(\d+)\u0000/g, (_m, i: string) => '<code>' + codes[+i] + '</code>');
  return s.replace(/\n/g, '<br>');
}

/* ---------- tarih ---------- */

const dayStart = (ts: number) => {
  const d = new Date(ts);
  d.setHours(0, 0, 0, 0);
  return d.getTime();
};
export const daysAgo = (ts: number) => Math.round((dayStart(Date.now()) - dayStart(ts)) / 86400000);
export const sameDayTs = (a: number, b: number) => dayStart(a) === dayStart(b);
export function fmt(loc: string) {
  return {
    time: (ts: number) => new Intl.DateTimeFormat(loc, { hour: '2-digit', minute: '2-digit' }).format(ts),
    date: (ts: number) => new Intl.DateTimeFormat(loc, { day: '2-digit', month: '2-digit', year: 'numeric' }).format(ts),
    long: (ts: number) => new Intl.DateTimeFormat(loc, { day: 'numeric', month: 'long', year: 'numeric' }).format(ts),
    weekday: (ts: number) => new Intl.DateTimeFormat(loc, { weekday: 'long' }).format(ts),
  };
}
export function listTimeL(ts: number, L: L, loc: string) {
  if (!ts) return '';
  const f = fmt(loc);
  const d = daysAgo(ts);
  if (d === 0) return f.time(ts);
  if (d === 1) return L('Dün');
  if (d < 7) return f.weekday(ts);
  return f.date(ts);
}
export function dayLabelL(ts: number, L: L, loc: string) {
  const f = fmt(loc);
  const d = daysAgo(ts);
  if (d === 0) return L('Bugün');
  if (d === 1) return L('Dün');
  if (d < 7) return f.weekday(ts);
  return f.long(ts);
}
export function fmtSize(n: number, loc: string) {
  if (!n) return '';
  const u = ['B', 'KB', 'MB', 'GB'];
  let i = 0;
  while (n >= 1024 && i < 3) {
    n /= 1024;
    i++;
  }
  return (i ? n.toLocaleString(loc, { maximumFractionDigits: 1 }) : n) + ' ' + u[i];
}
export function fmtSecs(s: number) {
  s = Math.max(0, Math.round(s || 0));
  return Math.floor(s / 60) + ':' + ('0' + (s % 60)).slice(-2);
}
