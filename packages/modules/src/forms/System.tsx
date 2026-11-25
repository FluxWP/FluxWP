// Forms › System check: the shared flx_system_checks rows of every FluxWP plugin (flxFormsSystem) on the shared
// classic SystemChecks screen. Shown only when Flux Donate or Flux Speed does not host the screen.
import { useTranslation } from 'react-i18next';
import { useFlxQuery } from '@fluxwp/graphql/react';
import { FlxFormsSystem } from '@fluxwp/graphql/operations';
import { Loading, SystemChecks } from '@fluxwp/ui';
import { useHost } from '../host';
import { errorText, useForms } from './common';

export function SystemPage() {
  const { t } = useForms();
  const { i18n } = useTranslation();
  const host = useHost();
  const q = useFlxQuery(FlxFormsSystem, {});
  const d = q.data?.flxFormsSystem;
  if (q.isError) return <p className="flxfrm-bad">{errorText(q.error, t('Something went wrong.'))}</p>;
  if (!d) return <Loading />;
  const at = new Date(q.dataUpdatedAt || Date.now());
  const hm = String(at.getHours()).padStart(2, '0') + ':' + String(at.getMinutes()).padStart(2, '0');
  return <SystemChecks checks={d.checks} lang={i18n.language} busy={q.isFetching} checkedAt={hm} onRecheck={() => void q.refetch()} onAction={(url) => host.openUrl(url)} />;
}
