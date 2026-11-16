// Shared pieces of the Application settings and App access screens: translator, notices, confirm dialog,
// switch, date formatting, and the "payload with errors" helper every mutation uses.
import { createContext, useContext, useMemo, useState, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Modal } from '@fluxwp/ui';
import { translator, type Translate } from './i18n';

export type Notice = { kind: 'success' | 'error'; text: string };
export type Confirm = { text: string; action: string; run: () => void };
type Tn = (single: string, plural: string, n: number, ...args: (string | number)[]) => string;

interface Screen {
  t: Translate;
  tn: Tn;
  lang: string;
  /** Show a result line at the top of the page. */
  told: (text: string) => void;
  /** Show an error line; an unknown error becomes the generic text. */
  fail: (e: unknown) => void;
  /** Ask before a destructive action. */
  ask: (c: Confirm) => void;
  /** Format a Unix time as date + time; 0 = em dash. */
  when: (ts: number) => string;
}

const Ctx = createContext<Screen | null>(null);

export function useScreen(): Screen {
  const c = useContext(Ctx);
  if (!c) throw new Error('ScreenProvider missing');
  return c;
}

export function ScreenProvider({ children, title, lead, classicUrl }: { children: ReactNode; title: string; lead?: string; classicUrl?: string }) {
  const { i18n } = useTranslation();
  const { t, tn } = useMemo(() => translator(i18n.language), [i18n.language]);
  const [notice, setNotice] = useState<Notice | null>(null);
  const [confirm, setConfirm] = useState<Confirm | null>(null);
  const value = useMemo<Screen>(
    () => ({
      t,
      tn,
      lang: i18n.language,
      told: (text) => {
        setNotice({ kind: 'success', text });
        window.scrollTo?.({ top: 0, behavior: 'smooth' });
      },
      fail: (e) => setNotice({ kind: 'error', text: e instanceof Error && e.message ? e.message : t('The action could not be completed.') }),
      ask: setConfirm,
      when: (ts) => (ts ? new Intl.DateTimeFormat(i18n.language, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(ts * 1000)) : '—'),
    }),
    [t, tn, i18n.language],
  );
  return (
    <Ctx.Provider value={value}>
      <div className="flxs-app">
        <div className="flxs-head">
          <h1>{title}</h1>
          {classicUrl && (
            <a className="flxs-classic" href={classicUrl}>
              {t('Classic view')}
            </a>
          )}
        </div>
        {notice && (
          <div className={'flxs-notice is-' + notice.kind} role={notice.kind === 'error' ? 'alert' : 'status'}>
            <span>{notice.text}</span>
            <button type="button" className="flxs-x" aria-label={t('Close')} onClick={() => setNotice(null)}>
              ×
            </button>
          </div>
        )}
        {lead && <p className="flxs-lead">{lead}</p>}
        {children}
        {confirm && (
          <Modal title={confirm.action} onClose={() => setConfirm(null)}>
            <p className="flxs-confirm">{confirm.text}</p>
            <div className="flxs-modal-actions">
              <button type="button" className="flxs-btn" onClick={() => setConfirm(null)}>
                {t('Cancel')}
              </button>
              <button
                type="button"
                className="flxs-btn is-danger"
                onClick={() => {
                  confirm.run();
                  setConfirm(null);
                }}
              >
                {confirm.action}
              </button>
            </div>
          </Modal>
        )}
      </div>
    </Ctx.Provider>
  );
}

/** Error messages of a mutation payload (empty = ok). */
export const payloadError = (errors: { message: string }[] | null | undefined): string => (errors ?? []).map((e) => e.message).join(' ');

export function Switch({ checked, onChange, label, disabled }: { checked: boolean; onChange: (on: boolean) => void; label: string; disabled?: boolean }) {
  return (
    <label className="flxs-switch">
      <input type="checkbox" checked={checked} disabled={disabled} onChange={(e) => onChange(e.currentTarget.checked)} />
      <span aria-hidden="true" />
      <span className="flxs-sr">{label}</span>
    </label>
  );
}

/** Checkbox with its text, as in the classic forms. */
export function Check({ checked, onChange, children, disabled }: { checked: boolean; onChange: (on: boolean) => void; children: ReactNode; disabled?: boolean }) {
  return (
    <label className="flxs-check">
      <input type="checkbox" checked={checked} disabled={disabled} onChange={(e) => onChange(e.currentTarget.checked)} /> <span>{children}</span>
    </label>
  );
}

export function Field({ label, htmlFor, children, desc }: { label: string; htmlFor?: string; children: ReactNode; desc?: string }) {
  return (
    <div className="flxs-field">
      <label htmlFor={htmlFor}>{label}</label>
      <div>
        {children}
        {desc && <p className="flxs-desc">{desc}</p>}
      </div>
    </div>
  );
}

export function SaveBar({ busy, dirty, onSave, children }: { busy: boolean; dirty: boolean; onSave?: () => void; children?: ReactNode }) {
  const { t } = useScreen();
  return (
    <div className="flxs-actions">
      <button type="submit" className="flxs-btn is-primary" disabled={busy} onClick={onSave}>
        {t('Save')}
      </button>
      {dirty && <span className="flxs-dirty">{t('Unsaved changes')}</span>}
      {children}
    </div>
  );
}

/** A secret shown once (API key, client secret). */
export function Secret({ title, value, onClose }: { title: string; value: string; onClose: () => void }) {
  const { t } = useScreen();
  return (
    <div className="flxs-notice is-secret" role="status">
      <div>
        <strong>{title}</strong>
        <textarea readOnly rows={Math.min(3, value.split('\n').length)} value={value} onFocus={(e) => e.currentTarget.select()} />
      </div>
      <button type="button" className="flxs-x" aria-label={t('Close')} onClick={onClose}>
        ×
      </button>
    </div>
  );
}
