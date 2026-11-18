// Flux App › Application settings (administrators): General, Appearance, Applications, Modules, Menu, OAuth clients,
// All access. Same screen in wp-admin (wp-embed) and the FluxWP app; every read and write goes through the Flux App
// GraphQL operations (plugins/flx-app/graphql/operations).
import { useMemo, useState } from 'react';
import { createGraphQLClient } from '@fluxwp/graphql';
import { FlxGraphQLProvider } from '@fluxwp/graphql/react';
import { useHost } from '../host';
import { ScreenProvider, useScreen } from './common';
import { translator } from './i18n';
import { GeneralTab } from './GeneralTab';
import { AppearanceTab } from './AppearanceTab';
import { MobileTab } from './MobileTab';
import { ModulesTab } from './ModulesTab';
import { MenuTab } from './MenuTab';
import { ClientsTab } from './ClientsTab';
import { AccessTab } from './AccessTab';
import { useTranslation } from 'react-i18next';
import './app-settings.css';

const TABS = ['settings', 'appearance', 'mobile', 'modules', 'menu', 'apps', 'all'] as const;
type Tab = (typeof TABS)[number];

const isTab = (v: string | null | undefined): v is Tab => !!v && (TABS as readonly string[]).includes(v);

export function AppSettingsScreen() {
  const host = useHost();
  const client = useMemo(() => createGraphQLClient({ api: host.client }), [host.client]);
  const { i18n } = useTranslation();
  const { t } = useMemo(() => translator(i18n.language), [i18n.language]);
  return (
    <FlxGraphQLProvider client={client} scope={host.scope}>
      <ScreenProvider title={t('Application')}>
        <Tabs />
      </ScreenProvider>
    </FlxGraphQLProvider>
  );
}

function Tabs() {
  const host = useHost();
  const { t } = useScreen();
  const fromUrl = typeof window !== 'undefined' ? new URLSearchParams(window.location.search).get('tab') : null;
  const [tab, setTab] = useState<Tab>(isTab(host.startPage) ? host.startPage : isTab(fromUrl) ? fromUrl : 'settings');
  const labels: Record<Tab, string> = {
    settings: t('General'),
    appearance: t('Appearance'),
    mobile: t('Applications'),
    modules: t('Modules'),
    menu: t('Menu'),
    apps: t('OAuth clients'),
    all: t('All access'),
  };
  const go = (k: Tab) => {
    setTab(k);
    if (host.mode === 'wp' && typeof window !== 'undefined') {
      const u = new URL(window.location.href);
      u.searchParams.set('tab', k);
      window.history.replaceState(null, '', u);
    }
  };
  return (
    <>
      <div className="flxas-tablist" role="tablist">
        {TABS.map((k) => (
          <button key={k} type="button" role="tab" aria-selected={k === tab} className={'flxas-tab' + (k === tab ? ' is-active' : '')} onClick={() => go(k)}>
            {labels[k]}
          </button>
        ))}
      </div>
      {tab === 'settings' && <GeneralTab />}
      {tab === 'appearance' && <AppearanceTab />}
      {tab === 'mobile' && <MobileTab />}
      {tab === 'modules' && <ModulesTab />}
      {tab === 'menu' && <MenuTab />}
      {tab === 'apps' && <ClientsTab />}
      {tab === 'all' && <AccessTab all />}
    </>
  );
}
