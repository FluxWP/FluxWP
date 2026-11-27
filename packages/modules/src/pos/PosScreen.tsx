import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { createGraphQLClient } from '@fluxwp/graphql';
import { FlxGraphQLProvider } from '@fluxwp/graphql/react';
import { Screen } from '@fluxwp/ui';
import { CreditCard, Landmark, Receipt, Settings } from 'lucide-react';
import posIcon from '../../../../plugins/flx-pos/assets/icon.svg?inline';
import { useHost } from '../host';
import { AccountsTab } from './AccountsTab';
import { InlineNotice } from './components';
import { translator } from './i18n';
import { MethodsTab } from './MethodsTab';
import { SettingsTab } from './SettingsTab';
import { TxnsTab } from './TxnsTab';
import type { Notice } from './types';
import './pos.css';

const TABS = ['methods', 'accounts', 'txns', 'settings'] as const;
type Tab = (typeof TABS)[number];
const icons = { methods: CreditCard, accounts: Landmark, txns: Receipt, settings: Settings };

export function PosScreen() {
  const host = useHost();
  const client = useMemo(() => createGraphQLClient({ api: host.client }), [host.client]);
  return (
    <FlxGraphQLProvider client={client} scope={host.scope}>
      <Pos />
    </FlxGraphQLProvider>
  );
}

function Pos() {
  const host = useHost();
  const { i18n } = useTranslation();
  const { t } = useMemo(() => translator(i18n.language), [i18n.language]);
  const start = TABS.find((id) => id === host.startPage) ?? 'methods';
  const [tab, setTab] = useState<Tab>(start);
  const [notice, setNotice] = useState<Notice | null>(null);
  const say = (n: Notice | null) => {
    setNotice(n);
    if (n) window.scrollTo?.({ top: 0, behavior: 'smooth' });
  };
  const titles: Record<Tab, string> = {
    methods: t('Payment methods'),
    accounts: t('POS accounts'),
    txns: t('Transactions'),
    settings: t('Settings'),
  };
  return (
    <Screen
      title={t('Virtual POS')}
      subtitle={t('Manage checkout methods, provider accounts and card transactions.')}
      icon={host.appIcon || posIcon}
      width="wide"
      className="flxpos-app"
      tabs={TABS.map((id) => {
        const Icon = icons[id];
        return {
          id,
          label: (
            <span className="flxpos-tab">
              <Icon size={16} aria-hidden="true" />
              {titles[id]}
            </span>
          ),
        };
      })}
      tab={tab}
      onTab={(id) => {
        setTab(id);
        setNotice(null);
      }}
      notice={
        notice && (
          <InlineNotice kind={notice.kind} close={{ label: t('Close'), action: () => setNotice(null) }}>
            {notice.text}
          </InlineNotice>
        )
      }
    >
      {tab === 'methods' && <MethodsTab t={t} say={say} />}
      {tab === 'accounts' && <AccountsTab t={t} say={say} />}
      {tab === 'txns' && <TxnsTab t={t} say={say} />}
      {tab === 'settings' && <SettingsTab t={t} say={say} />}
    </Screen>
  );
}
