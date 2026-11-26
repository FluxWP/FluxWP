// Flux AI › Connected systems: AI on/off per plugin with its model and 30-day use. The per-plugin AI
// settings (declared with the Flux AI SDK) open from each row (?system=<plugin>, SystemSettings).
import { useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { FlxAiSystems, FlxAiSystemsUpdate } from '@fluxwp/graphql/operations';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { Loading } from '@fluxwp/ui';
import { useHost } from '../host';
import { Card, NoticeBar, Pill, errText, useAi, type Notice, Lead } from './common';
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
      <Lead text={t('Plugins that use Flux AI. A system switched off here cannot send AI requests; its own settings stay as they are.')} />
      <Card>
        <div className="flxai-tscroll">
          <table className="flxai-table flxai-systems">
            <thead>
              <tr>
                <th>{t('On')}</th>
                <th>{t('System')}</th>
                <th>{t('Status')}</th>
                <th>{t('Model')}</th>
                <th className="num">{t('Requests (30 days)')}</th>
                <th className="num">{t('Tokens')}</th>
                <th className="num">{t('Cost')}</th>
                <th className="num">{t('Errors')}</th>
                <th>{t('Last used')}</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {q.data.flxAiSystems.map((s) => (
                <tr key={s.source}>
                  <td>
                    <input type="checkbox" className="flxai-syscheck" checked={s.on} aria-label={s.label} disabled={update.isPending} onChange={(e) => update.mutate({ input: { systems: [{ source: s.source, on: e.target.checked }] } })} />
                  </td>
                  <td>
                    <strong>{s.label}</strong>
                    <br />
                    <code>{s.source}</code>
                  </td>
                  <td>
                    {!s.installed ? <Pill>{t('not installed')}</Pill> : !s.active ? <Pill>{t('plugin inactive')}</Pill> : !s.on ? <Pill kind="err">{t('AI off')}</Pill> : <Pill>{t('working')}</Pill>}
                  </td>
                  <td className="flxai-nowrap">{s.modelLabel || s.model || '—'}</td>
                  <td className="num">{num(s.calls) + (s.tools ? ' + ' + num(s.tools) : '')}</td>
                  <td className="num">{num(s.tokens)}</td>
                  <td className="num">{usd(s.cost)}</td>
                  <td className="num">{num(s.errors)}</td>
                  <td>{s.lastUsed?.label ?? '—'}</td>
                  <td className="flxai-syslinks">
                    <a href={adminUrl + 'admin.php?page=flx-ai-log&days=30&source=' + encodeURIComponent(s.source)}>{t('Logs')}</a>
                    {s.hasAiSettings && (
                      <>
                        {' · '}
                        <a href={adminUrl + 'admin.php?page=flx-ai-systems&system=' + encodeURIComponent(s.source)}>{t('AI settings')}</a>
                      </>
                    )}
                    {s.settingsPage && s.active && (
                      <>
                        {' · '}
                        <a href={adminUrl + s.settingsPage.replace(/^\//, '')}>{t('Its settings')}</a>
                      </>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        <p className="flxai-note">{t('The Model column is the model that system uses. Change it under Settings › Per-plugin model. Requests to a disabled system are rejected and show as "Disabled" in the log.')}</p>
      </Card>
    </>
  );
}
