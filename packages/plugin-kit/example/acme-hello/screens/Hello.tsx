import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { createGraphQLClient } from '@fluxwp/graphql';
import { FlxGraphQLProvider, useFlxQuery } from '@fluxwp/graphql/react';
import { FlxAcmeHelloViewer } from './generated/operations';
import { useHost } from '@fluxwp/host';
import './hello.css';

export function Hello() {
  const host = useHost();
  const client = useMemo(() => createGraphQLClient({ api: host.client }), [host.client]);
  return (
    <FlxGraphQLProvider client={client} scope={host.scope}>
      <Greeting />
    </FlxGraphQLProvider>
  );
}

function Greeting() {
  const { t } = useTranslation('acme');
  const viewer = useFlxQuery(FlxAcmeHelloViewer, {}).data?.flxViewer;
  return (
    <div className="acme-hello">
      <h1>{t('title')}</h1>
      <p>{viewer ? (viewer.loggedIn ? t('who', { name: viewer.name }) : t('guest')) : '…'}</p>
    </div>
  );
}
