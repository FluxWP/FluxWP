// Flux AI › Connected systems: AI on/off per plugin with its model and 30-day use. The per-plugin AI
// settings (declared with the Flux AI SDK) open from each row (?system=<plugin>, SystemSettings).
import { useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { FlxAiSystems, FlxAiSystemsUpdate } from '@fluxwp/graphql/operations';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { Loading } from '@fluxwp/ui';
import { useHost } from '../host';
import { Card, NoticeBar, Pill, Switch, errText, useAi, type Notice } from './common';
import { SystemSettings } from './SystemSettings';

export function Systems() {
  const system = typeof window === 'undefined' ? null : new URLSearchParams(window.location.search).get('system');
  return system ? <SystemSettings source={system} /> : <SystemList />;
}

function SystemList() {
  const { t, num, usd } = useAi();
  const host = useHost();
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const [notice, setNotice] = useState<Notice | null>(null);
  const q = useFlxQuery(FlxAiSystems, {});
  const update = useFlxMutation(FlxAiSystemsUpdate, {
    onSuccess: (r) => {
      const p = r.flxAiSystemsUpdate;
      if (!p || p.errors.length) return setNotice({ kind: 'error', text: p?.errors.map((e) => e.message).join(' ') || t('The action could not be completed.') });
      qc.setQueryData(flxQueryKey(scope, FlxAiSystems, {}), { flxAiSystems: p.systems });
    },
    onError: (e) => setNotice({ kind: 'error', text: errText(e, t('The action could not be completed.')) }),
  });
  if (q.isLoading) return <Loading />;
  if (q.isError || !q.data) return <div className="flxai-notice is-error">{q.error?.message || t('The action could not be completed.')}</div>;
  const adminUrl = host.adminUrl ?? '';
  return (
    <>
      <NoticeBar notice={notice} onClose={() => setNotice(null)} t={t} />
      <p className="flxai-lead">{t('Plugins that use Flux AI. Turn AI off for a system and its requests are refused; the last 30 days of use are shown.')}</p>
      <Card>
        <div className="flxai-tscroll">
          <table className="flxai-table">
            <thead>
              <tr>
                <th>{t('AI')}</th>
                <th>{t('System')}</th>
                <th>{t('Model')}</th>
                <th className="num">{t('Requests')}</th>
                <th className="num">{t('Tool calls')}</th>
                <th className="num">{t('Tokens')}</th>
                <th className="num">{t('Cost')}</th>
                <th>{t('Last used')}</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {q.data.flxAiSystems.map((s) => (
                <tr key={s.source} className={s.active ? '' : 'is-off'}>
                  <td>
                    <Switch checked={s.on} label={t('%s on', s.label)} disabled={update.isPending} onChange={(on) => update.mutate({ input: { systems: [{ source: s.source, on }] } })} />
                  </td>
                  <td>
                    <strong>{s.label}</strong>
                    {!s.installed && <Pill>{t('not installed')}</Pill>}
                    {s.errors > 0 && <Pill kind="err">{t('%s errors', num(s.errors))}</Pill>}
                  </td>
                  <td>{s.modelLabel || s.model || '—'}</td>
                  <td className="num">{num(s.calls)}</td>
                  <td className="num">{num(s.tools)}</td>
                  <td className="num">{num(s.tokens)}</td>
                  <td className="num">{usd(s.cost)}</td>
                  <td>{s.lastUsed?.label ?? '—'}</td>
                  <td>
                    {s.hasAiSettings && (
                      <a className="flxai-btn is-small" href={adminUrl + s.settingsPage.replace(/^\//, '')}>
                        {t('AI settings')}
                      </a>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </Card>
    </>
  );
}
