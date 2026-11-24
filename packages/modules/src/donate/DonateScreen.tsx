// Flux Donate › Dashboard and Donations. Same screen in wp-admin (wp-embed) and the FluxWP app; every read
// and write goes through the Flux Donate GraphQL operations (plugins/flx-donate/graphql/operations).
// The donation detail and the other pages are still the plugin's own pages: links to them load as usual.
import { useCallback, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { createGraphQLClient } from '@fluxwp/graphql';
import { FlxGraphQLProvider } from '@fluxwp/graphql/react';
import { useHost } from '../host';
import { translator } from './i18n';
import { DonateCtx, type Ctx, type Notice, type Page } from './shared';
import { Dashboard } from './Dashboard';
import { Donations } from './Donations';
import './donate.css';

export function DonateScreen() {
  const host = useHost();
  const client = useMemo(() => createGraphQLClient({ api: host.client }), [host.client]);
  return (
    <FlxGraphQLProvider client={client} scope={host.scope}>
      <Donate />
    </FlxGraphQLProvider>
  );
}

/** host.startPage: 'dashboard', 'list' or 'list?<classic list arguments>'. */
function parseStart(s?: string): { page: Page; query: string } {
  if (s && s.startsWith('list')) return { page: 'list', query: s.slice(5) };
  return { page: 'dashboard', query: '' };
}

function Donate() {
  const host = useHost();
  const { i18n } = useTranslation();
  const { t, tn } = useMemo(() => translator(i18n.language), [i18n.language]);
  const [start] = useState(() => parseStart(host.startPage));
  const [notice, setNotice] = useState<Notice | null>(null);
  const told = useCallback((n: Notice | null) => {
    setNotice(n);
    if (n) window.scrollTo?.({ top: 0, behavior: 'smooth' });
  }, []);
  const ctx = useMemo<Ctx>(() => ({ t, tn, lang: i18n.language, told, open: host.openUrl }), [t, tn, i18n.language, told, host.openUrl]);
  const box = notice ? (
    <div className={`flxdn-notice flxdn-notice--${notice.kind}`} role={notice.kind === 'ok' ? 'status' : 'alert'}>
      <p>{notice.text}</p>
      <button type="button" className="flxdn-notice__x" aria-label={t('Dismiss')} onClick={() => setNotice(null)}>
        ×
      </button>
    </div>
  ) : null;
  return (
    <DonateCtx.Provider value={ctx}>
      {start.page === 'list' ? <Donations query={start.query} notice={box} /> : <Dashboard notice={box} />}
    </DonateCtx.Provider>
  );
}
