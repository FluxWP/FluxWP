// Flux Donate › Dashboard and Donations. Same screen in wp-admin (wp-embed) and the FluxWP app; every read
// and write goes through the Flux Donate GraphQL operations (plugins/flx-donate/graphql/operations).
// The donation page (detail?id=N), donors (donors, donor?id=N) and recurring donations (subscriptions) are
// (and the donation types list and editor, types / type) here too; the other pages are still the plugin's own pages.
import { useCallback, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { createGraphQLClient } from '@fluxwp/graphql';
import { FlxGraphQLProvider } from '@fluxwp/graphql/react';
import { useHost } from '../host';
import { translator } from './i18n';
import { DonateCtx, type Ctx, type Notice, type Page } from './shared';
import { Dashboard } from './Dashboard';
import { Donations } from './Donations';
import { Detail } from './Detail';
import { Donors } from './Donors';
import { Donor } from './Donor';
import { Subscriptions } from './Subscriptions';
import { Types } from './Types';
import { TypeForm } from './TypeForm';
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

/**
 * host.startPage: 'dashboard', 'list[?<classic list arguments>]', 'detail?id=<donation id>',
 * 'donors[?s,kind,orderby,paged]', 'donor?id=<donor id>', 'subscriptions[?s,status,paged]' or
 * 'types[?view=archived&notice=<ok|error>:<text>]' or 'type?id=<type id; 0 = new>[&notice=…]'.
 */
function parseStart(s?: string): { page: Page; query: string } {
  const [head = '', query = ''] = (s ?? '').split('?', 2);
  const pages: Page[] = ['list', 'detail', 'donors', 'donor', 'subscriptions', 'types', 'type'];
  return (pages as string[]).includes(head) ? { page: head as Page, query } : { page: 'dashboard', query: '' };
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
      {start.page === 'detail' ? (
        <Detail id={Number(new URLSearchParams(start.query).get('id')) || 0} notice={box} />
      ) : start.page === 'donor' ? (
        <Donor id={Number(new URLSearchParams(start.query).get('id')) || 0} notice={box} />
      ) : start.page === 'donors' ? (
        <Donors query={start.query} notice={box} />
      ) : start.page === 'subscriptions' ? (
        <Subscriptions query={start.query} notice={box} />
      ) : start.page === 'type' ? (
        <TypeForm id={Number(new URLSearchParams(start.query).get('id')) || 0} query={start.query} notice={box} />
      ) : start.page === 'types' ? (
        <Types query={start.query} notice={box} />
      ) : start.page === 'list' ? (
        <Donations query={start.query} notice={box} />
      ) : (
        <Dashboard notice={box} />
      )}
    </DonateCtx.Provider>
  );
}
