// Flux App › App access (every user): own devices, app sessions, API keys, desktop app download.
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { createGraphQLClient } from '@fluxwp/graphql';
import { FlxGraphQLProvider, useFlxQuery } from '@fluxwp/graphql/react';
import { FlxAppAccess } from '@fluxwp/graphql/operations';
import { useHost } from '../host';
import { ScreenProvider } from './common';
import { translator } from './i18n';
import { AccessTab } from './AccessTab';
import './app-settings.css';

export function AccessScreen() {
  const host = useHost();
  const client = useMemo(() => createGraphQLClient({ api: host.client }), [host.client]);
  const { i18n } = useTranslation();
  const { t } = useMemo(() => translator(i18n.language), [i18n.language]);
  return (
    <FlxGraphQLProvider client={client} scope={host.scope}>
      <ScreenProvider title={t('App access')} sub={host.mode === 'wp' ? <SettingsLink label={t('App settings')} /> : undefined}>
        <AccessTab all={false} />
      </ScreenProvider>
    </FlxGraphQLProvider>
  );
}

/** Classic: administrators get an "App settings" button under the title. */
function SettingsLink({ label }: { label: string }) {
  const host = useHost();
  const q = useFlxQuery(FlxAppAccess, { all: false });
  if (!q.data?.flxAppAccess.canManage) return null;
  return (
    <a className="flxas-btn flxas-headbtn" href={(host.adminUrl ?? '/wp-admin/') + 'admin.php?page=flxapp-settings'}>
      {label}
    </a>
  );
}
