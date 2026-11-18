// Pieces both FluxWP Panel screens (Packages, Settings) use: the GraphQL provider, translator,
// notices, the on/off switch.
import { useMemo, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { useQueryClient } from '@tanstack/react-query';
import { createGraphQLClient, type GraphQLClient } from '@fluxwp/graphql';
import { FlxGraphQLProvider, useGraphQL } from '@fluxwp/graphql/react';
import { useHost } from '../host';
import { translator, type Translate } from './i18n';
import './setup.css';

export type Notice = { kind: 'success' | 'error' | 'warning'; text: string };

export function SetupProvider({ children }: { children: ReactNode }) {
  const host = useHost();
  const client = useMemo(() => createGraphQLClient({ api: host.client }), [host.client]);
  return (
    <FlxGraphQLProvider client={client} scope={host.scope}>
      {children}
    </FlxGraphQLProvider>
  );
}

/** Translator, GraphQL client and a "refetch every FluxWP Panel query" helper. */
export function useSetup(): { t: Translate; tn: ReturnType<typeof translator>['tn']; client: GraphQLClient; scope: string; locale: string; refresh: () => Promise<void> } {
  const { i18n } = useTranslation();
  const { t, tn } = useMemo(() => translator(i18n.language), [i18n.language]);
  const { client, scope } = useGraphQL();
  const qc = useQueryClient();
  // A refetch already running is kept (not restarted): the table does not go back to "Checking…".
  const refresh = () => qc.invalidateQueries({ queryKey: [scope, 'gql'], predicate: (q) => String(q.queryKey[2] ?? '').startsWith('FlxSetup') }, { cancelRefetch: false });
  return { t, tn, client, scope, locale: i18n.language, refresh };
}

export function errorText(e: unknown, fallback: string): string {
  return e instanceof Error && e.message ? e.message : fallback;
}

export function NoticeBar({ notice, onClose, t }: { notice: Notice | null; onClose: () => void; t: Translate }) {
  if (!notice) return null;
  return (
    <div className={'flxk-notice is-' + notice.kind} role={notice.kind === 'error' ? 'alert' : 'status'}>
      <span>{notice.text}</span>
      <button type="button" className="flxk-x" aria-label={t('Dismiss')} onClick={onClose}>
        ×
      </button>
    </div>
  );
}

export function Switch({ checked, onChange, label, disabled }: { checked: boolean; onChange: (on: boolean) => void; label: string; disabled?: boolean }) {
  return (
    <label className="flxk-switch">
      <input type="checkbox" role="switch" checked={checked} disabled={disabled} onChange={(e) => onChange(e.target.checked)} aria-label={label} />
      <span aria-hidden="true" />
    </label>
  );
}

/** A translated sentence with one <a href="%s">…</a> link (React escapes everything else). */
export function Linked({ text, href }: { text: string; href: string }) {
  const m = /^(.*)<a href="%s">(.*)<\/a>(.*)$/s.exec(text);
  if (!m) return <>{text.split('<a href="%s">').join('').split('</a>').join('')}</>;
  return (
    <>
      {m[1]}
      <a href={href}>{m[2]}</a>
      {m[3]}
    </>
  );
}
