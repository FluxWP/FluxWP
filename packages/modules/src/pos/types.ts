import type { FlxPosAccountsQuery, FlxPosMethodsQuery, FlxPosTxnsQuery } from '@fluxwp/graphql/types';
import type { Translate } from './i18n';

export type Methods = FlxPosMethodsQuery['flxPosMethods'];
export type Method = Methods['methods'][number];
export type AccountsData = FlxPosAccountsQuery;
export type Account = AccountsData['flxPosAccounts'][number];
export type Provider = AccountsData['flxPosProviders'][number];
export type Settings = AccountsData['flxPosSettings'];
export type Txn = FlxPosTxnsQuery['flxPosTxns']['nodes'][number];
export type Notice = { kind: 'ok' | 'warn' | 'bad'; text: string };

export type TabProps = { t: Translate; say: (notice: Notice | null) => void };
