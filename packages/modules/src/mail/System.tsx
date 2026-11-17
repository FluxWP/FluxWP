import { useQueryClient } from '@tanstack/react-query';
import { flxQueryKey, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { FlxMailSystemChecks } from '@fluxwp/graphql/operations';
import { Loading } from '@fluxwp/ui';
import { useHost } from '../host';
import { errorText, useMail } from './common';

export function SystemPage() {
  const { t } = useMail();
  const host = useHost();
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const q = useFlxQuery(FlxMailSystemChecks, {});
  if (q.isLoading) return <Loading />;
  if (q.isError) return <p className="flxmail-bad">{errorText(q.error, t('Something went wrong.'))}</p>;
  const rows = q.data?.flxMailSystemChecks ?? [];
  return (
    <div className="flxmail-card">
      <p className="flxmail-muted">{t('Rows related to email, WhatsApp and SMS. Checks for all plugins are on the system screen.')}</p>
      <button type="button" className="flxmail-btn" onClick={() => void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxMailSystemChecks, {}) })}>
        {t('Recheck')}
      </button>
      {rows.length ? (
        <ul className="flxmail-list">
          {rows.map((row) => (
            <li key={row.id} className={'flxmail-row is-' + row.status}>
              <span className={'flxmail-badge is-' + row.status}>{row.status}</span>
              <div>
                <strong>{row.label}</strong>
                <div className="flxmail-muted">{row.group}</div>
                <div>{row.message}</div>
              </div>
              {row.actionUrl ? (
                <button type="button" className="flxmail-btn" onClick={() => host.openUrl(row.actionUrl)}>
                  {row.actionLabel || t('Open')}
                </button>
              ) : null}
            </li>
          ))}
        </ul>
      ) : (
        <p>{t('No communication checks on this screen.')}</p>
      )}
    </div>
  );
}
