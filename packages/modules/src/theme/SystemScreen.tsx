// FluxWP theme › System Check. Same screen in wp-admin (wp-embed) and the FluxWP app. It shows every
// plugin's checks (flx_system_checks) from the theme's GraphQL query: status filter, groups with the
// problems first, fix links and the log of fixes made through Flux AI / MCP.
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { createGraphQLClient } from '@fluxwp/graphql';
import { FlxGraphQLProvider, useFlxQuery } from '@fluxwp/graphql/react';
import { FlxThemeSystem } from '@fluxwp/graphql/operations';
import type { FlxThemeCheckStatus, FlxThemeSystemQuery } from '@fluxwp/graphql/types';
import { Loading, Screen } from '@fluxwp/ui';
import { useHost } from '../host';
import { translator } from './i18n';
import './theme.css';

type Check = FlxThemeSystemQuery['flxThemeSystem']['checks'][number];
const STATUSES: FlxThemeCheckStatus[] = ['ERROR', 'WARNING', 'OK', 'INFO'];

export function SystemScreen() {
  const host = useHost();
  const client = useMemo(() => createGraphQLClient({ api: host.client }), [host.client]);
  return (
    <FlxGraphQLProvider client={client} scope={host.scope}>
      <System />
    </FlxGraphQLProvider>
  );
}

function System() {
  const host = useHost();
  const { i18n } = useTranslation();
  const { t } = useMemo(() => translator(i18n.language), [i18n.language]);
  // The page re-checks every time it opens, and "Check again" runs it once more.
  const q = useFlxQuery(FlxThemeSystem, {}, { staleTime: 0, gcTime: 0, refetchOnWindowFocus: false });
  const [filter, setFilter] = useState<FlxThemeCheckStatus | ''>('');

  if (q.isLoading) return <Loading />;
  if (q.isError || !q.data) return <div className="flxt-app"><div className="flxt-notice is-error">{q.error?.message || t('The action could not be completed.')}</div></div>;

  const sys = q.data.flxThemeSystem;
  const names: Record<FlxThemeCheckStatus, string> = { ERROR: t('Error'), WARNING: t('Warning'), OK: t('OK'), INFO: t('Info') };
  const count = (st: FlxThemeCheckStatus) => sys.checks.filter((c) => c.status === st).length;
  const groups = new Map<string, Check[]>();
  for (const c of sys.checks) if (!filter || c.status === filter) groups.set(c.group, [...(groups.get(c.group) ?? []), c]);

  return (
    <Screen className="flxt-app" width="wide" icon={host.appIcon} title={t('System Check')} subtitle={t('Plugins, scheduled tasks and key settings. Red rows mean part of the site is not working; yellow rows are recommended fixes. The page re-checks every time it opens.')}>
      <div className="flxt-sum">
        {STATUSES.map((st) => (
          <button key={st} type="button" className="flxt-pill" aria-pressed={filter === st} disabled={count(st) === 0} title={t('Show only these (click again for all)')} onClick={() => setFilter(filter === st ? '' : st)}>
            <span className={'flxt-dot ' + st.toLowerCase()} />
            {count(st) + ' ' + names[st]}
          </button>
        ))}
        <button type="button" className="flxt-btn" disabled={q.isFetching} onClick={() => void q.refetch()}>
          {t('Check again')}
        </button>
        <span className="flxt-time">{t('Checked: %s', sys.checkedAt.label)}</span>
      </div>

      {[...groups].map(([group, list]) => {
        const bad = list.filter((c) => c.status === 'ERROR' || c.status === 'WARNING').length;
        return (
          <section key={group} className="flxt-sk">
            <h2>
              {group} <small>{bad ? t('%d issues', bad) : t('All good')}</small>
            </h2>
            {list.map((c) => (
              <div key={c.id} className={'flxt-row is-' + c.status.toLowerCase()}>
                <span className={'flxt-dot ' + c.status.toLowerCase()} title={names[c.status]} />
                <div>
                  <strong>{c.label}</strong>
                  {c.sourceLabel && <span className="flxt-src">{c.sourceLabel}</span>}
                </div>
                <div className="flxt-msg">{c.message}</div>
                <div className="flxt-act">
                  {c.actionUrl && (
                    <a className="flxt-btn is-small" href={c.actionUrl}>
                      {c.actionLabel || t('Open')}
                    </a>
                  )}
                </div>
              </div>
            ))}
          </section>
        );
      })}

      {sys.fixLogAvailable && (
        <section className="flxt-sk">
          <h2>{t('Recent fixes (AI / MCP)')}</h2>
          {sys.fixLog.length === 0 ? (
            <p className="flxt-empty">{t('No fixes have been made through MCP yet.')}</p>
          ) : (
            <table className="flxt-fixlog">
              <thead>
                <tr>
                  <th>{t('Time')}</th>
                  <th>{t('User')}</th>
                  <th>{t('Fix')}</th>
                  <th>{t('Before → After')}</th>
                  <th>{t('Result')}</th>
                </tr>
              </thead>
              <tbody>
                {sys.fixLog.map((r, i) => (
                  <tr key={i}>
                    <td>{r.time.label}</td>
                    <td>{r.user}</td>
                    <td>
                      {r.label}
                      {r.value && (
                        <>
                          {' '}
                          <code>{r.value}</code>
                        </>
                      )}
                    </td>
                    <td>{r.before + ' → ' + r.after}</td>
                    <td>{(r.success ? '✓ ' : '✗ ') + r.message}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </section>
      )}
    </Screen>
  );
}
