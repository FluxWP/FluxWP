// Flux CRM › the whole CRM (overview, contacts, contact card, segments, journeys, duplicates, settings).
// Same screen in wp-admin (wp-embed) and the FluxWP app; every read and write goes through the Flux CRM
// GraphQL operations (plugins/flx-crm/graphql/operations). Events and the extension sections other plugins
// add to the contact card stay on the classic pages (linked).
import { useCallback, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useQueryClient } from '@tanstack/react-query';
import { createGraphQLClient } from '@fluxwp/graphql';
import { FlxGraphQLProvider, flxQueryKey, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { FlxCrmShell } from '@fluxwp/graphql/operations';
import { Loading } from '@fluxwp/ui';
import { useHost } from '../host';
import { translator } from './i18n';
import { CrmCtx, Ic, href, useRoute, type Crm, type Notice } from './ui';
import { Overview } from './Overview';
import { Contacts } from './Contacts';
import { ContactCard, ContactNew } from './ContactCard';
import { Duplicates } from './Duplicates';
import { Segments } from './Segments';
import { Journeys } from './Journeys';
import { Settings } from './Settings';
import './crm.css';

const START: Record<string, string> = { dashboard: 'overview', overview: 'overview', contacts: 'contacts', segments: 'segments', journeys: 'journeys', settings: 'settings' };

export function CrmScreen() {
  const host = useHost();
  const client = useMemo(() => createGraphQLClient({ api: host.client }), [host.client]);
  return (
    <FlxGraphQLProvider client={client} scope={host.scope}>
      <Crm />
    </FlxGraphQLProvider>
  );
}

function Crm() {
  const host = useHost();
  const { i18n } = useTranslation();
  const { t, tn } = useMemo(() => translator(i18n.language), [i18n.language]);
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const q = useFlxQuery(FlxCrmShell, {}, { staleTime: 60_000 });
  const [route, go] = useRoute(START[host.startPage ?? ''] ?? (host.startPage || 'overview'));
  const [notice, setNotice] = useState<Notice>(null);

  const tell = useCallback((kind: 'ok' | 'bad', text: string) => {
    setNotice({ kind, text });
    window.scrollTo?.({ top: 0, behavior: 'smooth' });
  }, []);
  const fail = useCallback((e: unknown) => tell('bad', e instanceof Error && e.message ? e.message : t('The action could not be completed.')), [tell, t]);
  const reloadShell = useCallback(() => void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxCrmShell) }), [qc, scope]);

  const crm = useMemo<Crm | null>(() => {
    if (!q.data) return null;
    const cur = q.data.flxCrmMeta.currency;
    const nf = new Intl.NumberFormat(i18n.language);
    const short = (n: number) => {
      try {
        return new Intl.NumberFormat(i18n.language, { style: 'currency', currency: cur, notation: 'compact', maximumFractionDigits: 1 }).format(n);
      } catch {
        return nf.format(n);
      }
    };
    return { t, tn, lang: i18n.language, shell: q.data, can: q.data.flxCrmMeta.canWrite, tell, fail, reloadShell, short, num: (n) => nf.format(n), classicUrl: host.classicUrl, adminUrl: host.adminUrl };
  }, [q.data, t, tn, i18n.language, tell, fail, reloadShell, host.classicUrl, host.adminUrl]);

  if (q.isLoading) return <Loading />;
  if (q.isError || !crm) return <div className="flxcrm-app"><div className="flxcrm-notice is-bad">{q.error?.message || t('The action could not be completed.')}</div></div>;

  const tabs: Array<[string, string]> = [
    ['overview', t('Overview')],
    ['contacts', t('Contacts')],
    ['segments', t('Segments')],
    ['journeys', t('Journeys')],
    ['settings', t('Settings')],
  ];
  const active = route.name === 'contact' || route.name === 'duplicates' ? 'contacts' : route.name;
  let body;
  switch (route.name) {
    case 'contacts':
      body = route.id === 'new' ? <ContactNew go={go} /> : <Contacts route={route} go={go} />;
      break;
    case 'contact':
      body = <ContactCard key={route.id} id={route.id} go={go} />;
      break;
    case 'duplicates':
      body = <Duplicates go={go} />;
      break;
    case 'segments':
      body = <Segments route={route} go={go} />;
      break;
    case 'journeys':
      body = <Journeys route={route} go={go} />;
      break;
    case 'settings':
      body = <Settings />;
      break;
    default:
      body = <Overview go={go} />;
  }

  return (
    <CrmCtx.Provider value={crm}>
      <div className="flxcrm-app">
        <nav className="flxcrm-tabs" aria-label={t('CRM')}>
          {tabs.map(([k, label]) => (
            <a key={k} className={'flxcrm-tab' + (k === active ? ' is-on' : '')} href={href(k)} aria-current={k === active ? 'page' : undefined}>
              {label}
            </a>
          ))}
          {host.adminUrl && (
            <a className="flxcrm-tab" href={host.adminUrl + 'admin.php?page=flx-crm-events'} target="_blank" rel="noopener">
              {t('Events')}
            </a>
          )}
          {host.classicUrl && (
            <a className="flxcrm-tab" href={host.classicUrl} style={{ marginLeft: 'auto' }}>
              {t('Classic view')}
            </a>
          )}
        </nav>
        {notice && (
          <div className={'flxcrm-notice is-' + notice.kind} role={notice.kind === 'bad' ? 'alert' : 'status'}>
            <Ic n={notice.kind === 'ok' ? 'check' : 'alert'} />
            <span style={{ flex: 1 }}>{notice.text}</span>
            <button type="button" className="button-link" aria-label={t('Close')} onClick={() => setNotice(null)}>
              ×
            </button>
          </div>
        )}
        {body}
      </div>
    </CrmCtx.Provider>
  );
}
