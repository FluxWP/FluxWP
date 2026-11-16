// Flux Files: dialogs (confirm / prompt / alert, no native ones), toasts, modal and context menu.
// Styles are the classic screen's (.flxfm-modal, .flxfm-menu, toast) so both screens look the same.
import { createContext, useCallback, useContext, useEffect, useLayoutEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { createPortal } from 'react-dom';
import { Ic } from './icons';
import type { Translate } from './i18n';

/** Dialogs, menus and toasts mount inside the FluxWP root so the prefixed bundle styles reach them. */
export const portalHost = (): HTMLElement => document.querySelector<HTMLElement>('.flux-root') ?? document.body;
const isDark = (): boolean => document.documentElement.classList.contains('flxa-dark');

/* ------------------------------------------------------------------ Modal */
export function Modal({ title, wide, full, onClose, children, foot, t }: { title: string; wide?: boolean; full?: boolean; onClose: () => void; children: ReactNode; foot?: ReactNode; t: Translate }) {
  const ref = useRef<HTMLDialogElement>(null);
  useEffect(() => {
    const d = ref.current;
    if (d && !d.open) d.showModal();
    return () => {
      if (d?.open) d.close();
    };
  }, []);
  return createPortal(
    <dialog
      ref={ref}
      className={'flxfm-modal' + (wide ? ' is-wide' : '') + (full ? ' is-full' : '') + (isDark() ? ' is-dark' : '')}
      onClick={(e) => {
        if (e.target === ref.current) onClose();
      }}
      onCancel={(e) => {
        e.preventDefault();
        onClose();
      }}
    >
      <div className="flxfm-mhead">
        <h2>{title}</h2>
        <button type="button" className="flxfm-ib" onClick={onClose} aria-label={t('close')}>
          <Ic n="x" />
        </button>
      </div>
      <div className="flxfm-mbody">{children}</div>
      {foot ? <div className="flxfm-mfoot">{foot}</div> : null}
    </dialog>,
    portalHost(),
  );
}

/* ---------------------------------------------------------------- dialogs */
interface Ask {
  kind: 'confirm' | 'prompt' | 'alert';
  title: string;
  message?: string;
  value?: string;
  placeholder?: string;
  ok?: string;
  cancel?: string;
  danger?: boolean;
  resolve: (v: unknown) => void;
}
type ConfirmOpts = { title: string; message?: string; ok?: string; cancel?: string; danger?: boolean };
type PromptOpts = { title: string; message?: string; value?: string; placeholder?: string; ok?: string };
interface Toast {
  id: number;
  text: string;
  type: 'ok' | 'bad' | 'info';
}
export interface Dialogs {
  confirm: (o: ConfirmOpts) => Promise<boolean>;
  prompt: (o: PromptOpts) => Promise<string | null>;
  alert: (o: { title: string; message?: string }) => Promise<void>;
  toast: (text: string, type?: Toast['type']) => void;
}
const DialogCtx = createContext<Dialogs | null>(null);
export const useDialogs = (): Dialogs => {
  const d = useContext(DialogCtx);
  if (!d) throw new Error('DialogProvider missing');
  return d;
};

export function DialogProvider({ t, children }: { t: Translate; children: ReactNode }) {
  const [asks, setAsks] = useState<Ask[]>([]);
  const [toasts, setToasts] = useState<Toast[]>([]);
  const seq = useRef(0);
  const push = useCallback((a: Omit<Ask, 'resolve'>) => new Promise<unknown>((resolve) => setAsks((s) => [...s, { ...a, resolve }])), []);
  const api = useMemo<Dialogs>(
    () => ({
      confirm: (o) => push({ kind: 'confirm', ...o }).then(Boolean),
      prompt: (o) => push({ kind: 'prompt', ...o }).then((v) => (typeof v === 'string' ? v : null)),
      alert: (o) => push({ kind: 'alert', ...o }).then(() => undefined),
      toast: (text, type = 'ok') => {
        const id = ++seq.current;
        setToasts((s) => [...s, { id, text, type }]);
        setTimeout(() => setToasts((s) => s.filter((x) => x.id !== id)), 4200);
      },
    }),
    [push],
  );
  const top = asks[0];
  const done = (v: unknown) => {
    if (!top) return;
    top.resolve(v);
    setAsks((s) => s.slice(1));
  };
  return (
    <DialogCtx.Provider value={api}>
      {children}
      {top ? <AskView key={asks.length + top.title} ask={top} done={done} t={t} /> : null}
      {createPortal(
        <div className="flxfm-toasts" role="status" aria-live="polite">
          {toasts.map((x) => (
            <div key={x.id} className={'flxfm-toast is-' + x.type}>
              {x.text}
            </div>
          ))}
        </div>,
        portalHost(),
      )}
    </DialogCtx.Provider>
  );
}

function AskView({ ask, done, t }: { ask: Ask; done: (v: unknown) => void; t: Translate }) {
  const [v, setV] = useState(ask.value ?? '');
  const input = useRef<HTMLInputElement>(null);
  useEffect(() => {
    const el = input.current;
    if (el) {
      el.focus();
      const dot = (ask.value ?? '').lastIndexOf('.');
      el.setSelectionRange(0, dot > 0 ? dot : el.value.length);
    }
  }, [ask.value]);
  const cancel = () => done(ask.kind === 'prompt' ? null : false);
  const ok = () => done(ask.kind === 'prompt' ? v : true);
  return (
    <Modal
      t={t}
      title={ask.title}
      onClose={cancel}
      foot={
        <>
          {ask.kind !== 'alert' ? (
            <button type="button" className="flxfm-btn" onClick={cancel}>
              {ask.cancel ?? t('cancel')}
            </button>
          ) : null}
          <button type="button" className={'flxfm-btn ' + (ask.danger ? 'is-danger' : 'flxfm-btn--pri')} onClick={ok}>
            {ask.ok ?? (ask.kind === 'alert' ? t('close') : t('save'))}
          </button>
        </>
      }
    >
      {ask.message ? <p className="flxfm-ask-msg">{ask.message}</p> : null}
      {ask.kind === 'prompt' ? (
        <input
          ref={input}
          className="flxfm-ask-in"
          type="text"
          value={v}
          placeholder={ask.placeholder}
          onChange={(e) => setV(e.target.value)}
          onKeyDown={(e) => {
            if (e.key === 'Enter') {
              e.preventDefault();
              ok();
            }
          }}
        />
      ) : null}
    </Modal>
  );
}

/* ------------------------------------------------------------------- menu */
export type MenuItem = '-' | null | { icon?: string; label: string; key?: string; disabled?: boolean; danger?: boolean; check?: boolean; run?: () => void; sub?: MenuItem[] };
export interface MenuAt {
  items: MenuItem[];
  x: number;
  y: number;
  right?: boolean;
}

export function Menu({ at, onClose }: { at: MenuAt; onClose: () => void }) {
  const ref = useRef<HTMLDivElement>(null);
  const [pos, setPos] = useState({ left: at.x, top: at.y });
  const [open, setOpen] = useState<number | null>(null);
  const mob = window.innerWidth <= 600;
  useLayoutEffect(() => {
    const el = ref.current;
    if (!el || mob) return;
    const w = el.offsetWidth;
    const h = el.offsetHeight;
    let left = at.right ? at.x - w : at.x;
    let top = at.y;
    if (left + w > window.innerWidth - 8) left = window.innerWidth - w - 8;
    if (left < 8) left = 8;
    if (top + h > window.innerHeight - 8) top = Math.max(8, at.y - h);
    setPos({ left, top });
  }, [at, mob]);
  useEffect(() => {
    const out = (e: MouseEvent) => {
      if (ref.current && !ref.current.contains(e.target as Node)) onClose();
    };
    const t = setTimeout(() => document.addEventListener('mousedown', out, true), 0);
    window.addEventListener('resize', onClose);
    ref.current?.querySelector<HTMLButtonElement>('button:not([disabled])')?.focus({ preventScroll: true });
    return () => {
      clearTimeout(t);
      document.removeEventListener('mousedown', out, true);
      window.removeEventListener('resize', onClose);
    };
  }, [onClose]);
  const key = (e: React.KeyboardEvent) => {
    const bs = Array.from(ref.current?.querySelectorAll<HTMLButtonElement>('button:not([disabled])') ?? []).filter((b) => b.offsetParent);
    const i = bs.indexOf(document.activeElement as HTMLButtonElement);
    if (e.key === 'Escape') onClose();
    else if (e.key === 'ArrowDown') {
      e.preventDefault();
      (bs[i + 1] ?? bs[0])?.focus();
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      (bs[i - 1] ?? bs[bs.length - 1])?.focus();
    }
  };
  const list = (items: MenuItem[], nested: boolean): ReactNode => {
    const clean = items.filter((x, i, a) => x !== null && !(x === '-' && (a[i - 1] === '-' || a[i - 1] === null || i === 0)));
    while (clean.length && clean[clean.length - 1] === '-') clean.pop();
    return (
      <div className="flxfm-mlist">
        {clean.map((it, i) => {
          if (it === '-') return <hr key={i} />;
          if (it === null) return null;
          const sub = it.sub;
          const on = !nested && open === i;
          return (
            <div
              key={i}
              className={'flxfm-mi' + (sub ? ' has-sub' : '') + (on ? ' is-open' : '')}
              onMouseEnter={() => !nested && !mob && setOpen(sub ? i : null)}
            >
              <button
                type="button"
                role="menuitem"
                className={it.danger ? 'is-danger' : undefined}
                disabled={it.disabled}
                onClick={() => {
                  if (sub) {
                    setOpen(on ? null : i);
                    return;
                  }
                  onClose();
                  it.run?.();
                }}
              >
                {it.check !== undefined ? <span className="flxfm-mcheck">{it.check ? <Ic n="check" /> : null}</span> : it.icon ? <Ic n={it.icon} /> : <span className="flxfm-mcheck" />}
                <span className="flxfm-ml">{it.label}</span>
                {it.key ? <kbd>{it.key}</kbd> : null}
                {sub ? <Ic n="right" cls="flxfm-i-sm" /> : null}
              </button>
              {sub ? <div className="flxfm-sub">{list(sub, true)}</div> : null}
            </div>
          );
        })}
      </div>
    );
  };
  return createPortal(
    <div ref={ref} className={'flxfm-menu' + (mob ? ' is-sheet' : '') + (isDark() ? ' is-dark' : '')} role="menu" style={mob ? undefined : { left: pos.left, top: pos.top }} onKeyDown={key}>
      {list(at.items, false)}
    </div>,
    portalHost(),
  );
}
