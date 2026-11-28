// Communication › System check: this package's flx_system_checks rows (flxi.*) on the shared classic
// SystemChecks screen, inside the card as the classic page printed it (heading off).
import { useTranslation } from 'react-i18next';
import { useFlxQuery } from '@fluxwp/graphql/react';
import { FlxMailSystemChecks } from '@fluxwp/graphql/operations';
import { Loading, SystemChecks } from '@fluxwp/ui';
import { useHost } from '../host';
import { errorText, useMail } from './common';

export function SystemPage() {
  const { t } = useMail();
  const { i18n } = useTranslation();
  const host = useHost();
  const q = useFlxQuery(FlxMailSystemChecks, {});
  if (q.isError) return <p className="flxmail-bad">{errorText(q.error, t('Something went wrong.'))}</p>;
  const rows = q.data?.flxMailSystemChecks;
  if (!rows) return <Loading />;
  const at = new Date(q.dataUpdatedAt || Date.now());
  const hm = String(at.getHours()).padStart(2, '0') + ':' + String(at.getMinutes()).padStart(2, '0');
  return (
    <div className="flxmail-card">
      <SystemChecks checks={rows} lang={i18n.language} busy={q.isFetching} checkedAt={hm} onRecheck={() => void q.refetch()} onAction={(url) => host.openUrl(url)} />
    </div>
  );
}
