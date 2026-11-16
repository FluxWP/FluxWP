// Small UI kit of the Studio screen: icons, toasts, menus, popovers, modals, confirm / prompt dialogs.
// Everything renders inside the screen's own root (.flxst) so the Studio look and theme apply.
import { createContext, useCallback, useContext, useEffect, useLayoutEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import type { Translate } from './i18n';

const IC = {
  image: 'M5 3h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2zM8.5 10a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3zM21 15l-5-5L5 21',
  video: 'm22 8-6 4 6 4V8zM4 6h10a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2z',
  grid: 'M3 3h7v7H3zM14 3h7v7h-7zM14 14h7v7h-7zM3 14h7v7H3z',
  star: 'm12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z',
  folder: 'M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2z',
  folderPlus: 'M12 10v6M9 13h6M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2z',
  user: 'M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z',
  users: 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75',
  search: 'M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16zm10 2-4.35-4.35',
  plus: 'M12 5v14M5 12h14',
  x: 'M18 6 6 18M6 6l12 12',
  check: 'M20 6 9 17l-5-5',
  sparkle: 'M9.94 15.5A2 2 0 0 0 8.5 14.06l-6.14-1.58a.5.5 0 0 1 0-.96L8.5 9.94A2 2 0 0 0 9.94 8.5l1.58-6.14a.5.5 0 0 1 .96 0L14.06 8.5A2 2 0 0 0 15.5 9.94l6.14 1.58a.5.5 0 0 1 0 .96L15.5 14.06a2 2 0 0 0-1.44 1.44l-1.58 6.14a.5.5 0 0 1-.96 0zM20 3v4M22 5h-4',
  bulb: 'M9 18h6M10 22h4M12 2a7 7 0 0 0-4 12.7V17h8v-2.3A7 7 0 0 0 12 2z',
  send: 'm22 2-7 20-4-9-9-4zM22 2 11 13',
  trash: 'M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2',
  download: 'M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3',
  upload: 'M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12',
  copy: 'M8 8h12v12H8zM16 8V4H4v12h4',
  edit: 'M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z',
  brush: 'M18.4 2.6a2 2 0 0 1 3 3L12 15l-3-3zM9 12c-2 0-4 1.5-4 4 0 1.5-1 3-3 3 1.5 1.5 3.5 2 5 2 3 0 5-2 5-5',
  square: 'M3 3h18v18H3z',
  eraser: 'm7 21-4.3-4.3a1 1 0 0 1 0-1.4l10-10a1 1 0 0 1 1.4 0l5.6 5.6a1 1 0 0 1 0 1.4L13 19M22 21H7M5 11l9 9',
  undo: 'M3 7v6h6M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13',
  compare: 'M12 3v18M5 3h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z',
  repeat: 'm17 2 4 4-4 4M3 11v-1a4 4 0 0 1 4-4h14M7 22l-4-4 4-4M21 13v1a4 4 0 0 1-4 4H3',
  library: 'M4 19.5A2.5 2.5 0 0 1 6.5 17H20M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z',
  move: 'M5 9l-3 3 3 3M9 5l3-3 3 3M15 19l-3 3-3-3M19 9l3 3-3 3M2 12h20M12 2v20',
  dots: 'M12 13a1 1 0 1 0 0-2 1 1 0 0 0 0 2zM19 13a1 1 0 1 0 0-2 1 1 0 0 0 0 2zM5 13a1 1 0 1 0 0-2 1 1 0 0 0 0 2z',
  chevD: 'm6 9 6 6 6-6',
  chevR: 'm9 18 6-6-6-6',
  chevL: 'm15 18-6-6 6-6',
  settings: 'M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2zM12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z',
  alert: 'M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z',
  back: 'M19 12H5M12 19l-7-7 7-7',
  menu: 'M4 6h16M4 12h16M4 18h16',
  sun: 'M12 16a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41',
  moon: 'M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9z',
  layers: 'm12 2 10 5-10 5L2 7zM2 17l10 5 10-5M2 12l10 5 10-5',
  template: 'M3 3h18v6H3zM3 13h8v8H3zM15 13h6v8h-6z',
  stop: 'M6 6h12v12H6z',
  chart: 'M3 3v18h18M7 14l4-4 3 3 6-6',
  home: 'M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z',
} as const;

export type IconName = keyof typeof IC;

export function Ic({ n, size = 18, cls }: { n: IconName; size?: number; cls?: string }) {
  return (
    <svg viewBox="0 0 24 24" width={size} height={size} fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" className={'flxst-i' + (cls ? ' ' + cls : '')}>
      <path d={IC[n]} />
    </svg>
  );
}

export function Btn({ icon, label, onClick, cls, title, disabled, busy }: { icon?: IconName; label?: string; onClick?: (e: React.MouseEvent<HTMLButtonElement>) => void; cls?: string; title?: string; disabled?: boolean; busy?: boolean }) {
  return (
    <button type="button" className={'flxst-btn ' + (cls ?? '') + (busy ? ' is-busy' : '')} onClick={onClick} title={title} aria-label={label ? undefined : title} disabled={disabled}>
      {icon && <Ic n={icon} size={16} />}
      {label && <span>{label}</span>}
    </button>
  );
}

export function IconBtn({ icon, title, onClick, cls }: { icon: IconName; title: string; onClick?: (e: React.MouseEvent<HTMLButtonElement>) => void; cls?: string }) {
  return (
    <button type="button" className={'flxst-ib ' + (cls ?? '')} title={title} aria-label={title} onClick={onClick}>
      <Ic n={icon} size={18} />
    </button>
  );
}

export interface MenuItem {
  icon?: IconName;
  label?: string;
  hint?: string;
  danger?: boolean;
  checked?: boolean;
  disabled?: boolean;
  sep?: boolean;
  head?: string;
  onClick?: () => void;
}

interface PopState {
  anchor: HTMLElement;
  render: (close: () => void) => ReactNode;
  cls?: string | undefined;
  alignRight?: boolean | undefined;
  key: number;
}
interface DialogState {
  kind: 'confirm' | 'prompt';
  title: string;
  text: string;
  value: string;
  ok: string;
  danger: boolean;
  resolve: (v: boolean | string | null) => void;
}
interface Toast {
  id: number;
  msg: string;
  kind?: 'error' | undefined;
  out: boolean;
}

export interface UiApi {
  toast: (msg: string, kind?: 'error') => void;
  menu: (anchor: HTMLElement, items: MenuItem[], opts?: { cls?: string; alignRight?: boolean }) => void;
  pop: (anchor: HTMLElement, render: (close: () => void) => ReactNode, opts?: { cls?: string; alignRight?: boolean }) => void;
  closePop: () => void;
  confirm: (title: string, text: string, ok?: string, danger?: boolean) => Promise<boolean>;
  prompt: (title: string, value?: string, ok?: string) => Promise<string | null>;
  fail: (e: unknown) => void;
}

const UiCtx = createContext<UiApi | null>(null);
export const useUi = (): UiApi => {
  const u = useContext(UiCtx);
  if (!u) throw new Error('UiProvider missing');
  return u;
};

let seq = 0;
const modalStack: number[] = [];

export function UiProvider({ t, children }: { t: Translate; children: ReactNode }) {
  const [toasts, setToasts] = useState<Toast[]>([]);
  const [pop, setPop] = useState<PopState | null>(null);
  const [dialog, setDialog] = useState<DialogState | null>(null);

  const toast = useCallback((msg: string, kind?: 'error') => {
    const id = ++seq;
    setToasts((l) => [...l, { id, msg, kind, out: false }]);
    window.setTimeout(() => setToasts((l) => l.map((x) => (x.id === id ? { ...x, out: true } : x))), kind === 'error' ? 6000 : 3000);
    window.setTimeout(() => setToasts((l) => l.filter((x) => x.id !== id)), kind === 'error' ? 6300 : 3300);
  }, []);
  const closePop = useCallback(() => setPop(null), []);
  const openPop = useCallback<UiApi['pop']>((anchor, render, opts) => setPop({ anchor, render, cls: opts?.cls, alignRight: opts?.alignRight, key: ++seq }), []);
  const menu = useCallback<UiApi['menu']>(
    (anchor, items, opts) =>
      openPop(
        anchor,
        (close) => (
          <div className="flxst-menu">
            {items.map((it, i) =>
              it.sep ? (
                <div key={i} className="flxst-menu-sep" />
              ) : it.head ? (
                <div key={i} className="flxst-menu-head">
                  {it.head}
                </div>
              ) : (
                <button
                  key={i}
                  type="button"
                  className={'flxst-menu-item' + (it.danger ? ' is-danger' : '') + (it.checked ? ' is-checked' : '')}
                  disabled={it.disabled}
                  onClick={() => {
                    close();
                    it.onClick?.();
                  }}
                >
                  {it.icon ? <Ic n={it.icon} size={16} /> : <span className="flxst-menu-sp" />}
                  <span>{it.label}</span>
                  {it.checked && <Ic n="check" size={14} cls="flxst-menu-ck" />}
                  {it.hint && <small>{it.hint}</small>}
                </button>
              ),
            )}
          </div>
        ),
        opts,
      ),
    [openPop],
  );
  const confirm = useCallback<UiApi['confirm']>(
    (title, text, ok, danger) => new Promise<boolean>((res) => setDialog({ kind: 'confirm', title, text, value: '', ok: ok ?? t('OK'), danger: !!danger, resolve: (v) => res(v === true) })),
    [t],
  );
  const prompt = useCallback<UiApi['prompt']>(
    (title, value, ok) => new Promise<string | null>((res) => setDialog({ kind: 'prompt', title, text: '', value: value ?? '', ok: ok ?? t('Save'), danger: false, resolve: (v) => res(typeof v === 'string' && v ? v : null) })),
    [t],
  );
  const fail = useCallback((e: unknown) => toast(e instanceof Error && e.message ? e.message : t('Something went wrong.'), 'error'), [toast, t]);
  const api = useMemo<UiApi>(() => ({ toast, menu, pop: openPop, closePop, confirm, prompt, fail }), [toast, menu, openPop, closePop, confirm, prompt, fail]);

  return (
    <UiCtx.Provider value={api}>
      {children}
      {pop && <Pop key={pop.key} state={pop} onClose={closePop} />}
      {dialog && <DialogView d={dialog} t={t} onDone={() => setDialog(null)} />}
      <div className="flxst-toasts" role="status" aria-live="polite">
        {toasts.map((x) => (
          <div key={x.id} className={'flxst-toast' + (x.kind ? ' is-' + x.kind : '') + (x.out ? ' is-out' : '')}>
            <Ic n={x.kind === 'error' ? 'alert' : 'check'} size={16} />
            <span>{x.msg}</span>
          </div>
        ))}
      </div>
    </UiCtx.Provider>
  );
}

function Pop({ state, onClose }: { state: PopState; onClose: () => void }) {
  const ref = useRef<HTMLDivElement>(null);
  const [pos, setPos] = useState<{ left: number; top: number }>({ left: -9999, top: 0 });
  useLayoutEffect(() => {
    const el = ref.current;
    if (!el) return;
    const r = state.anchor.getBoundingClientRect();
    const w = el.offsetWidth;
    const h = el.offsetHeight;
    let x = state.alignRight ? r.right - w : r.left;
    let y = r.bottom + 6;
    if (y + h > window.innerHeight - 8) y = Math.max(8, r.top - h - 6);
    x = Math.max(8, Math.min(x, window.innerWidth - w - 8));
    setPos((o) => (o.left === x && o.top === y ? o : { left: x, top: y }));
  });
  useEffect(() => {
    const out = (e: MouseEvent) => {
      const n = e.target as Node;
      if (!ref.current?.contains(n) && !state.anchor.contains(n)) onClose();
    };
    const key = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        e.stopPropagation();
        onClose();
      }
    };
    document.addEventListener('mousedown', out, true);
    document.addEventListener('keydown', key, true);
    return () => {
      document.removeEventListener('mousedown', out, true);
      document.removeEventListener('keydown', key, true);
    };
  }, [state.anchor, onClose]);
  return (
    <div ref={ref} className={'flxst-pop' + (state.cls ? ' ' + state.cls : '')} style={pos}>
      {state.render(onClose)}
    </div>
  );
}

export function Modal({ title, onClose, wide, full, cls, sticky, noFocus, foot, children }: { title?: string | undefined; onClose: () => void; wide?: boolean; full?: boolean; cls?: string; sticky?: boolean; noFocus?: boolean; foot?: ReactNode; children: ReactNode }) {
  const { t } = useModalT();
  const box = useRef<HTMLDivElement>(null);
  const id = useRef(++seq);
  const closeRef = useRef(onClose);
  closeRef.current = onClose;
  useEffect(() => {
    const me = id.current;
    modalStack.push(me);
    const key = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && !e.defaultPrevented && modalStack[modalStack.length - 1] === me && !document.querySelector('.flxst-pop')) {
        e.preventDefault();
        closeRef.current();
      }
    };
    document.addEventListener('keydown', key);
    const tm = window.setTimeout(() => {
      if (!noFocus) box.current?.querySelector<HTMLElement>('[autofocus], textarea, input:not([type=checkbox]):not([type=file]):not([type=range])')?.focus();
    }, 30);
    return () => {
      document.removeEventListener('keydown', key);
      window.clearTimeout(tm);
      const i = modalStack.indexOf(me);
      if (i >= 0) modalStack.splice(i, 1);
    };
  }, [noFocus]);
  return (
    <div
      className={'flxst-shade' + (full ? ' is-full' : '')}
      onMouseDown={(e) => {
        if (e.target === e.currentTarget && !sticky) onClose();
      }}
    >
      <div ref={box} className={'flxst-modal' + (wide ? ' is-wide' : '') + (cls ? ' ' + cls : '')} role="dialog" aria-modal="true" aria-label={title}>
        {title && (
          <div className="flxst-modal-head">
            <h2>{title}</h2>
            <IconBtn icon="x" title={t('Close')} onClick={onClose} />
          </div>
        )}
        <div className="flxst-modal-body">{children}</div>
        {foot && <div className="flxst-modal-foot">{foot}</div>}
      </div>
    </div>
  );
}

// Modal needs the translator for the close label without every caller passing it.
const TCtx = createContext<{ t: Translate } | null>(null);
export const TProvider = TCtx.Provider;
function useModalT(): { t: Translate } {
  return useContext(TCtx) ?? { t: (s) => s };
}
export const useT = (): Translate => useModalT().t;

function DialogView({ d, t, onDone }: { d: DialogState; t: Translate; onDone: () => void }) {
  const [val, setVal] = useState(d.value);
  const input = useRef<HTMLInputElement>(null);
  useEffect(() => {
    const tm = window.setTimeout(() => input.current?.select(), 40);
    return () => window.clearTimeout(tm);
  }, []);
  const finish = (v: boolean | string | null) => {
    d.resolve(v);
    onDone();
  };
  const ok = () => finish(d.kind === 'confirm' ? true : val.trim());
  return (
    <Modal
      title={d.title}
      onClose={() => finish(d.kind === 'confirm' ? false : null)}
      foot={
        <>
          <Btn label={t('Cancel')} onClick={() => finish(d.kind === 'confirm' ? false : null)} />
          <Btn label={d.ok} cls={d.danger ? 'is-danger' : 'is-primary'} onClick={ok} />
        </>
      }
    >
      {d.kind === 'confirm' ? (
        <p>{d.text}</p>
      ) : (
        <input
          ref={input}
          className="flxst-input"
          type="text"
          value={val}
          onChange={(e) => setVal(e.target.value)}
          onKeyDown={(e) => {
            if (e.key === 'Enter') {
              e.preventDefault();
              ok();
            }
          }}
        />
      )}
    </Modal>
  );
}

export function Select({ label, value, options, onChange, cls }: { label: string; value: string | number; options: Array<[string | number, string]>; onChange: (v: string) => void; cls?: string }) {
  return (
    <select className={'flxst-sel' + (cls ? ' ' + cls : '')} aria-label={label} title={label} value={String(value)} onChange={(e) => onChange(e.target.value)}>
      {options.map(([v, l]) => (
        <option key={v} value={String(v)}>
          {l}
        </option>
      ))}
    </select>
  );
}
