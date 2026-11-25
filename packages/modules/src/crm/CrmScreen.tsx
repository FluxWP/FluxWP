// Flux CRM › the whole CRM (overview, contacts, contact card, segments, journeys, events, duplicates, settings).
// Same screen in wp-admin (wp-embed) and the FluxWP app; every read and write goes through the Flux CRM
// GraphQL operations (plugins/flx-crm/graphql/operations).
import { useCallback, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useQueryClient } from '@tanstack/react-query';
import { createGraphQLClient } from '@fluxwp/graphql';
import { FlxGraphQLProvider, flxQueryKey, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { FlxCrmShell } from '@fluxwp/graphql/operations';
import { Loading, Screen } from '@fluxwp/ui';
import { useHost } from '../host';
import { translator } from './i18n';
import { CrmCtx, Ic, useRoute, type Crm, type Notice } from './ui';
import { Overview } from './Overview';
import { Contacts } from './Contacts';
import { ContactCard, ContactNew } from './ContactCard';
import { Duplicates } from './Duplicates';
import { Segments } from './Segments';
import { Journeys } from './Journeys';
import { Settings } from './Settings';
import { Events } from './Events';
import './crm.css';

const START: Record<string, string> = { dashboard: 'overview', overview: 'overview', contacts: 'contacts', segments: 'segments', journeys: 'journeys', events: 'events', settings: 'settings' };

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
  const [head, setHead] = useState<{ title: string; lead?: string } | null>(null);
  const [actionsEl, setActionsEl] = useState<HTMLDivElement | null>(null);

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
    return { t, tn, lang: i18n.language, shell: q.data, can: q.data.flxCrmMeta.canWrite, tell, fail, reloadShell, short, num: (n) => nf.format(n), adminUrl: host.adminUrl, setHead, actionsEl };
  }, [q.data, t, tn, i18n.language, tell, fail, reloadShell, host.adminUrl, actionsEl]);

  if (q.isLoading) return <Loading />;
  if (q.isError || !crm) return <div className="flxcrm-app"><div className="flxcrm-notice is-bad">{q.error?.message || t('The action could not be completed.')}</div></div>;

  const tabs: Array<[string, string]> = [
    ['overview', t('Overview')],
    ['contacts', t('Contacts')],
    ['segments', t('Segments')],
    ['events', t('Events')],
    ['journeys', t('Journeys')],
    ['settings', t('Settings')],
  ];
  const active = route.name === 'contact' || route.name === 'duplicates' ? 'contacts' : route.name === 'event' ? 'events' : route.name;
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
    case 'events':
    case 'event':
      body = <Events route={route} go={go} />;
      break;
    case 'settings':
      body = <Settings />;
      break;
    default:
      body = <Overview go={go} />;
  }

  const box = notice ? (
    <div className={'flxcrm-notice is-' + notice.kind} role={notice.kind === 'bad' ? 'alert' : 'status'}>
      <Ic n={notice.kind === 'ok' ? 'check' : 'alert'} />
      <span style={{ flex: 1 }}>{notice.text}</span>
      <button type="button" className="button-link" aria-label={t('Close')} onClick={() => setNotice(null)}>
        ×
      </button>
    </div>
  ) : null;
  return (
    <CrmCtx.Provider value={crm}>
      <Screen<string>
        className={'flxcrm-app' + (route.name === 'contact' ? ' is-bare' : '')}
        width="wide"
        icon={host.appIcon}
        title={head?.title ?? t('CRM')}
        subtitle={head?.lead}
        actions={<div ref={setActionsEl} className="flxcrm-head__actions" />}
        notice={box}
        tabs={tabs.map(([id, label]) => ({ id, label }))}
        tab={active}
        onTab={(k) => go(k)}
      >
        {body}
      </Screen>
    </CrmCtx.Provider>
  );
}
