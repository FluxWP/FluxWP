// Small building blocks of the Forms screen.
import { useEffect, useRef, type ReactNode } from 'react';
import { Icon } from '@fluxwp/ui';

export type Notice = { kind: 'success' | 'error'; text: string };

export function NoticeBar({ notice, onClose, label }: { notice: Notice | null; onClose: () => void; label: string }) {
  if (!notice) return null;
  return (
    <div className={'flxf-notice is-' + (notice.kind === 'success' ? 'ok' : 'bad')} role={notice.kind === 'error' ? 'alert' : 'status'}>
      <Icon name={notice.kind === 'success' ? 'check' : 'warning'} size={16} />
      <span>{notice.text}</span>
      <button type="button" className="flxf-x" aria-label={label} onClick={onClose}>
        ×
      </button>
    </div>
  );
}

export function Switch({ checked, onChange, label, disabled = false }: { checked: boolean; onChange: (v: boolean) => void; label: string; disabled?: boolean }) {
  return (
    <label className="flxf-switch">
      <input type="checkbox" checked={checked} disabled={disabled} onChange={(e) => onChange(e.currentTarget.checked)} />
      <span className="flxf-switch__ui" aria-hidden="true" />
      <span className="flxf-sr">{label}</span>
    </label>
  );
}

export function Check({ checked, onChange, label, help }: { checked: boolean; onChange: (v: boolean) => void; label: string; help?: string }) {
  return (
    <label className="flxf-check">
      <input type="checkbox" checked={checked} onChange={(e) => onChange(e.currentTarget.checked)} />
      <span>
        <strong>{label}</strong>
        {help && <small>{help}</small>}
      </span>
    </label>
  );
}

export function Field({ label, help, error, children }: { label: string; help?: string; error?: string; children: ReactNode }) {
  return (
    <label className={'flxf-field' + (error ? ' has-error' : '')}>
      <span>{label}</span>
      {children}
      {help && <small>{help}</small>}
      {error && (
        <small className="flxf-field__error" role="alert">
          {error}
        </small>
      )}
    </label>
  );
}

/** Confirm dialog (a real button flow, not window.confirm: wp-admin pages block it in embedded views). */
export function Confirm({ open, title, body, confirm, cancel, danger = false, busy = false, onConfirm, onCancel }: { open: boolean; title: string; body: string; confirm: string; cancel: string; danger?: boolean; busy?: boolean; onConfirm: () => void; onCancel: () => void }) {
  const ok = useRef<HTMLButtonElement | null>(null);
  useEffect(() => {
    if (open) ok.current?.focus();
  }, [open]);
  useEffect(() => {
    if (!open) return;
    const key = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onCancel();
    };
    window.addEventListener('keydown', key);
    return () => window.removeEventListener('keydown', key);
  }, [open, onCancel]);
  if (!open) return null;
  return (
    <div className="flxf-dialog" role="presentation" onClick={onCancel}>
      <div className="flxf-dialog__box" role="alertdialog" aria-modal="true" aria-labelledby="flxf-dialog-title" onClick={(e) => e.stopPropagation()}>
        <h3 id="flxf-dialog-title">{title}</h3>
        <p>{body}</p>
        <div className="flxf-dialog__actions">
          <button type="button" className="flxf-btn" onClick={onCancel}>
            {cancel}
          </button>
          <button type="button" ref={ok} className={'flxf-btn ' + (danger ? 'is-danger' : 'is-primary')} disabled={busy} onClick={onConfirm}>
            {confirm}
          </button>
        </div>
      </div>
    </div>
  );
}

export function copyText(text: string): void {
  try {
    void navigator.clipboard?.writeText(text);
  } catch {
    /* clipboard is not available (insecure context); nothing to do */
  }
}

export const errorText = (e: unknown, fallback: string): string => (e instanceof Error && e.message ? e.message : fallback);
