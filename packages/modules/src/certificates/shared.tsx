// Pieces the certificate pages share: the header slot (title, subtitle and buttons sit in the screen header, as the
// classic flxs_admin_header printed them), the send-to-someone-else dialog and the small modal.
import { createContext, useContext, useEffect, useState, type ReactNode } from 'react';
import { createPortal } from 'react-dom';
import { useFlxMutation } from '@fluxwp/graphql/react';
import { confirmDialog } from '@fluxwp/ui';
import { FlxCertificateSendTo } from '@fluxwp/graphql/operations';
import type { FlxCertificateChannel } from '@fluxwp/graphql/types';
import type { Translate } from './i18n';

export type Notice = { kind: 'ok' | 'err'; text: string };
export interface Head {
  title?: string;
  sub?: string;
}
export type Provider = { channel: FlxCertificateChannel; id: number; name: string };

export const SlotCtx = createContext<HTMLElement | null>(null);
export const HeadCtx = createContext<(h: Head) => void>(() => undefined);

/** Buttons in the screen header (classic .flxs-head__actions). */
export function HeadActions({ children }: { children: ReactNode }) {
  const el = useContext(SlotCtx);
  return el ? createPortal(children, el) : null;
}

/** Sets the header title and subtitle while the page is open. */
export function useHead(title: string | undefined, sub?: string) {
  const set = useContext(HeadCtx);
  useEffect(() => {
    set({ title, sub });
  }, [set, title, sub]);
}

export function admin(base: string | undefined, query: string): string {
  const root = (base || '').replace(/\/?$/, '/');
  return root + 'admin.php?' + query;
}

/** Turkish numbers as the classic screen wrote them: +90 532 123 45 67. */
export function normPhone(v: string): string {
  const raw = String(v || '').trim();
  let d = raw.replace(/\D/g, '');
  if (!d) return '';
  const intl = raw[0] === '+' || d.indexOf('00') === 0;
  if (intl) d = d.replace(/^0+/, '');
  else if (d[0] === '0') d = '0' + d.replace(/^0+/, '');
  if (d.indexOf('900') === 0 && d.length === 13) d = '90' + d.slice(3);
  if (!intl) {
    if (d.length === 11 && d[0] === '0') d = '90' + d.slice(1);
    else if (d.length === 10 && d[0] !== '0') d = '90' + d;
  }
  d = d.replace(/^0+/, '');
  return d.length >= 10 && d.length <= 15 ? d : '';
}
export function fmtPhone(d: string): string {
  if (d.length === 12 && d.indexOf('90') === 0) return '+90 ' + d.slice(2, 5) + ' ' + d.slice(5, 8) + ' ' + d.slice(8, 10) + ' ' + d.slice(10);
  return '+' + d;
}
export const validEmail = (v: string) => /^[^@\s]+@[^@\s]+\.[^@\s.]{2,}$/.test(String(v || '').trim());

/** flx-brand's dialog (no native confirm in wp-admin); the browser's one only outside it. */
export const ask = confirmDialog;

export const CHANNEL_ICON: Record<string, string> = { email: 'dashicons-email-alt', sms: 'dashicons-smartphone', whatsapp: 'dashicons-whatsapp' };

export function Modal({ t, title, onClose, children }: { t: Translate; title: string; onClose: () => void; children: ReactNode }) {
  return (
    <div className="flxs-modal" role="dialog" aria-modal="true">
      <div>
        <strong>{title}</strong>
        {children}
        <button type="button" className="button" onClick={onClose}>
          {t('Close')}
        </button>
      </div>
    </div>
  );
}

export function Forward({ t, id, providers, onClose, onNotice }: { t: Translate; id: number; providers: Provider[]; onClose: () => void; onNotice: (n: Notice) => void }) {
  const sendTo = useFlxMutation(FlxCertificateSendTo);
  const [channel, setChannel] = useState<FlxCertificateChannel>('WHATSAPP');
  return (
    <Modal t={t} title={t('Send to someone else…')} onClose={onClose}>
      <form
        onSubmit={(e) => {
          e.preventDefault();
          const fd = new FormData(e.currentTarget);
          void sendTo
            .mutateAsync({
              input: {
                id,
                channel,
                to: String(fd.get('to') || ''),
                name: String(fd.get('name') || ''),
                subject: String(fd.get('subject') || '') || undefined,
                body: String(fd.get('body') || '') || undefined,
                provider: Number(fd.get('provider') || 0) || undefined,
                save: fd.get('save') === 'on',
              },
            })
            .then((res) => {
              const errs = res.flxCertificateSendTo?.errors ?? [];
              const bad = res.flxCertificateSendTo?.results.find((r) => !r.ok);
              if (errs.length || bad) onNotice({ kind: 'err', text: errs.map((er) => er.message).join(' ') || bad?.message || '' });
              else {
                onNotice({ kind: 'ok', text: t('Queued.') });
                onClose();
              }
            });
        }}
      >
        <select value={channel} onChange={(e) => setChannel(e.target.value as FlxCertificateChannel)}>
          <option value="WHATSAPP">WhatsApp</option>
          <option value="SMS">SMS</option>
          <option value="EMAIL">{t('E-mail')}</option>
        </select>
        <label className="flxs-field">
          {t('Recipient')}
          <input name="to" required />
        </label>
        <label className="flxs-field">
          {t('Full name')}
          <input name="name" />
        </label>
        {channel === 'EMAIL' && (
          <label className="flxs-field">
            {t('E-mail')}
            <input name="subject" />
          </label>
        )}
        <label className="flxs-field">
          {t('Note')}
          <textarea name="body" rows={3} />
        </label>
        <select name="provider">
          <option value="0">{t('Default')}</option>
          {providers
            .filter((p) => p.channel === channel && p.id)
            .map((p) => (
              <option key={p.channel + p.id} value={p.id}>
                {p.name}
              </option>
            ))}
        </select>
        <label>
          <input type="checkbox" name="save" defaultChecked /> {t('Remember in the directory')}
        </label>
        <button className="button button-primary" type="submit">
          {t('Send')}
        </button>
      </form>
    </Modal>
  );
}
