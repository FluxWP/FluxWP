// Shared pieces of the Flux Chat settings and reports screens: GraphQL provider, translator, notices,
// and small form controls that look like the other FluxWP admin screens.
import { useMemo, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { createGraphQLClient } from '@fluxwp/graphql';
import { FlxGraphQLProvider } from '@fluxwp/graphql/react';
import { useHost } from '../host';
import { adminTranslator, type Translate } from './admin-i18n';
import './chat-admin.css';

export type Notice = { kind: 'ok' | 'warn' | 'bad'; text: string };
export type UserError = { field?: string | null; message: string };

export function AdminRoot({ children }: { children: ReactNode }) {
  const host = useHost();
  const client = useMemo(() => createGraphQLClient({ api: host.client }), [host.client]);
  return (
    <FlxGraphQLProvider client={client} scope={host.scope}>
      {children}
    </FlxGraphQLProvider>
  );
}

export function useAdminT(): Translate {
  const { i18n } = useTranslation();
  return useMemo(() => adminTranslator(i18n.language).t, [i18n.language]);
}

export function errorText(e: unknown, t: Translate): string {
  return e instanceof Error && e.message ? e.message : t('The action could not be completed.');
}

export function NoticeBar({ notice, onClose, t }: { notice: Notice | null; onClose: () => void; t: Translate }) {
  if (!notice) return null;
  return (
    <div className={'flxca-notice is-' + notice.kind} role={notice.kind === 'bad' ? 'alert' : 'status'}>
      <span>{notice.text}</span>
      <button type="button" className="flxca-x" aria-label={t('Close')} onClick={onClose}>
        ×
      </button>
    </div>
  );
}

/** A labelled control with an optional help line, like the classic `.flxcs-field`. */
export function Field({ label, note, className, children }: { label?: string; note?: ReactNode; className?: string; children: ReactNode }) {
  return (
    <div className={'flxca-field' + (className ? ' ' + className : '')}>
      {label && <span className="flxca-field__label">{label}</span>}
      {children}
      {note && <small>{note}</small>}
    </div>
  );
}

export function Card({ title, lead, children, id }: { title?: string; lead?: ReactNode; children: ReactNode; id?: string }) {
  return (
    <section className="flxca-card" id={id}>
      {title && <h2>{title}</h2>}
      {lead && <p className="flxca-lead">{lead}</p>}
      {children}
    </section>
  );
}
