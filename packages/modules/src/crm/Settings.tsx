// CRM › Settings: sync, sources, roles, scoring, AI & MCP, recent AI changes.
import { useEffect, useState, type FormEvent } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { FlxCrmScoreRun, FlxCrmSettings, FlxCrmSettingsSave, FlxCrmSyncStep } from '@fluxwp/graphql/operations';
import type { FlxCrmSettingsQuery } from '@fluxwp/graphql/types';
import { Loading } from '@fluxwp/ui';
import { Card, Head, errText, useCrm } from './ui';
import { runSync } from './sync';

type S = FlxCrmSettingsQuery['flxCrmSettings'];
interface Draft {
  chatEvents: boolean;
  aiTools: boolean;
  aiWrite: boolean;
  aiDestructive: boolean;
  riskThreshold: string;
  activeDays: string;
  baseCurrency: string;
  sources: Record<string, { enabled: boolean; role: string }>;
  roles: Record<string, { label: string; color: string; del: boolean }>;
  newLabel: string;
  newColor: string;
}
const draftOf = (s: S): Draft => ({
  chatEvents: s.chatEvents,
  aiTools: s.aiTools,
  aiWrite: s.aiWrite,
  aiDestructive: s.aiDestructive,
  riskThreshold: String(s.riskThreshold),
  activeDays: String(s.activeDays),
  baseCurrency: s.baseCurrency ?? '',
  sources: Object.fromEntries(s.sources.map((x) => [x.key, { enabled: x.enabled, role: x.role ?? '' }])),
  roles: Object.fromEntries(s.roles.map((r) => [r.key, { label: r.label, color: r.color, del: false }])),
  newLabel: '',
  newColor: '#64748b',
});

export function Settings() {
  const crm = useCrm();
  const { t, num, can } = crm;
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const q = useFlxQuery(FlxCrmSettings, {});
  const save = useFlxMutation(FlxCrmSettingsSave);
  const sync = useFlxMutation(FlxCrmSyncStep);
  const score = useFlxMutation(FlxCrmScoreRun);
  const [d, setD] = useState<Draft | null>(null);
  const [progress, setProgress] = useState('');
  useEffect(() => {
    if (q.data) setD(draftOf(q.data.flxCrmSettings));
  }, [q.data]);

  if (!can) return <div className="flxcrm-notice is-bad">{t('You are not allowed to do this.')}</div>;
  if (q.isLoading || !d) return <Loading />;
  if (!q.data) return <div className="flxcrm-notice is-bad">{q.error?.message}</div>;
  const s = q.data.flxCrmSettings;
  const ai = q.data.flxCrmAi;
  const busy = progress !== '' || save.isPending;
  const reload = () => {
    void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxCrmSettings) });
    void qc.invalidateQueries({ queryKey: [scope, 'gql'] });
  };

  const submit = async (e: FormEvent) => {
    e.preventDefault();
    try {
      const r = (await save.mutateAsync({
        input: {
          chatEvents: d.chatEvents,
          aiTools: d.aiTools,
          aiWrite: d.aiWrite,
          aiDestructive: d.aiDestructive,
          riskThreshold: Number.parseInt(d.riskThreshold, 10) || 70,
          activeDays: Number.parseInt(d.activeDays, 10) || 90,
          baseCurrency: d.baseCurrency,
          sources: Object.entries(d.sources).map(([key, v]) => ({ key, enabled: v.enabled, role: v.role })),
          roles: Object.entries(d.roles).map(([key, v]) => ({ key, label: v.label, color: v.color, delete: v.del })),
          newRole: d.newLabel.trim() ? { label: d.newLabel.trim(), color: d.newColor } : null,
        },
      })).flxCrmSettingsSave;
      if (!r?.settings) throw new Error(errText(r?.errors));
      crm.tell('ok', t('Settings saved.'));
      crm.reloadShell();
      reload();
    } catch (err) {
      crm.fail(err);
    }
  };
  const syncAll = async () => {
    try {
      await runSync((step, page) => sync.mutateAsync({ input: { step, page } }).then((r) => r.flxCrmSyncStep), setProgress, t);
      crm.tell('ok', t('Past data synced.'));
    } catch (e) {
      crm.fail(e);
    } finally {
      setProgress('');
      reload();
    }
  };
  const recalc = async () => {
    try {
      let done = false;
      for (let i = 0; !done && i < 200; i++) {
        const r = (await score.mutateAsync({ input: {} })).flxCrmScoreRun;
        if (!r || r.errors.length) throw new Error(errText(r?.errors));
        done = r.result?.done ?? true;
        setProgress(t('Scoring: %s of %s', num(r.result?.scored ?? 0), num(r.result?.total ?? 0)));
      }
      crm.tell('ok', t('Scores recalculated.'));
    } catch (e) {
      crm.fail(e);
    } finally {
      setProgress('');
      reload();
    }
  };
  const set = (p: Partial<Draft>) => setD({ ...d, ...p });

  return (
    <>
      <Head title={t('CRM settings')} lead={t('Where contacts come from, which role they get and how scoring works.')} />
      <Card title={t('Sync')} icon="refresh" aside={<span className="flxcrm-muted">{s.syncedAt ? t('Last full sync: %s', s.syncedAt.label) : t('Not synced yet')}</span>}>
        <p>{t('New donations, orders, forms and chats are linked to contacts straight away. Sync everything to import past records, or to relink after switching a source on or off. Existing contacts and notes are kept, and nothing is added twice.')}</p>
        <p>
          <button type="button" className="button button-primary" disabled={busy} onClick={() => void syncAll()}>
            {t('Sync everything')}
          </button>{' '}
          <button type="button" className="button" disabled={busy} onClick={() => void recalc()}>
            {t('Recalculate scores')}
          </button>
        </p>
        {progress && (
          <p className="flxcrm-progress" role="status">
            {progress}
          </p>
        )}
      </Card>

      <form onSubmit={(e) => void submit(e)}>
        <Card title={t('Sources')} icon="link">
          <table className="flxcrm-table flxcrm-table--settings">
            <thead>
              <tr>
                <th>{t('Source')}</th>
                <th>{t('On')}</th>
                <th>{t('Role for new contacts')}</th>
              </tr>
            </thead>
            <tbody>
              {s.sources.map((x) => (
                <tr key={x.key}>
                  <td data-label={t('Source')}>
                    <strong>{x.label}</strong> {!x.installed && <span className="flxcrm-badge">{t('not installed')}</span>}
                    <br />
                    <small className="flxcrm-muted">{x.description}</small>
                  </td>
                  <td data-label={t('On')}>
                    <label className="flxcrm-switch">
                      <input type="checkbox" aria-label={x.label} checked={d.sources[x.key]?.enabled ?? false} onChange={(e) => set({ sources: { ...d.sources, [x.key]: { enabled: e.currentTarget.checked, role: d.sources[x.key]?.role ?? '' } } })} />
                    </label>
                  </td>
                  <td data-label={t('Role')}>
                    {x.hasRole ? (
                      <select value={d.sources[x.key]?.role ?? ''} onChange={(e) => set({ sources: { ...d.sources, [x.key]: { enabled: d.sources[x.key]?.enabled ?? false, role: e.currentTarget.value } } })}>
                        <option value="">{t('— no role —')}</option>
                        {s.roles.map((r) => (
                          <option key={r.key} value={r.key}>
                            {r.label}
                          </option>
                        ))}
                      </select>
                    ) : (
                      <span className="flxcrm-muted">—</span>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          <label className="flxcrm-check">
            <input type="checkbox" checked={d.chatEvents} onChange={(e) => set({ chatEvents: e.currentTarget.checked })} /> {t('Write WhatsApp messages to the timeline (one line per message)')}
          </label>
          <p className="flxcrm-muted">{t('The role is given once a payment completes (donor, customer). The "Lead" role drops off by itself when the contact gets another role.')}</p>
        </Card>

        <Card title={t('Roles')} icon="badge">
          <p className="flxcrm-muted">{t("The contact's relationship with you. A contact can have several roles, e.g. donor and volunteer.")}</p>
          <div className="flxcrm-roles-edit">
            {s.roles.map((r) => {
              const v = d.roles[r.key];
              if (!v) return null;
              return (
                <div key={r.key} className="flxcrm-roles-edit__row">
                  <input type="color" value={v.color} aria-label={t('Colour')} onChange={(e) => set({ roles: { ...d.roles, [r.key]: { ...v, color: e.currentTarget.value } } })} />
                  <input type="text" value={v.label} maxLength={40} aria-label={t('Role')} onChange={(e) => set({ roles: { ...d.roles, [r.key]: { ...v, label: e.currentTarget.value } } })} />
                  <code>{r.key}</code>
                  {!r.builtin && (
                    <label className="flxcrm-check">
                      <input type="checkbox" checked={v.del} onChange={(e) => set({ roles: { ...d.roles, [r.key]: { ...v, del: e.currentTarget.checked } } })} /> {t('Delete')}
                    </label>
                  )}
                </div>
              );
            })}
            <div className="flxcrm-roles-edit__row is-new">
              <input type="color" value={d.newColor} aria-label={t('Colour')} onChange={(e) => set({ newColor: e.currentTarget.value })} />
              <input type="text" value={d.newLabel} maxLength={40} placeholder={t('New role: Sponsor, Philanthropist, Dealer…')} aria-label={t('New role')} onChange={(e) => set({ newLabel: e.currentTarget.value })} />
            </div>
          </div>
        </Card>

        <Card title={t('Scoring')} icon="chart">
          <div className="flxcrm-form flxcrm-form--grid">
            <label>
              <span>{t('Churn risk threshold (%)')}</span>
              <input type="number" min={10} max={100} value={d.riskThreshold} onChange={(e) => set({ riskThreshold: e.currentTarget.value })} />
              <small>{t('Contacts above this value go to the "Reach out" list. Risk follows each person\'s own rhythm: a monthly donor is at risk after 45 days, a yearly one is not.')}</small>
            </label>
            <label>
              <span>{t('Active window (days)')}</span>
              <input type="number" min={7} max={730} value={d.activeDays} onChange={(e) => set({ activeDays: e.currentTarget.value })} />
              <small>{t('A contact with any activity within this many days counts as active.')}</small>
            </label>
            <label>
              <span>{t('Reporting currency')}</span>
              <input type="text" maxLength={3} value={d.baseCurrency} placeholder={s.currency} onChange={(e) => set({ baseCurrency: e.currentTarget.value })} />
              <small>{t("Empty means the store currency. Payments in other currencies don't count towards value unless a rate is given.")}</small>
            </label>
          </div>
        </Card>

        <Card title={t('AI & MCP')} icon="sparkles">
          <p className="flxcrm-muted">{t("Everything in the CRM (contacts, fields, roles, tags, consents, timeline, segments, settings) can be read and edited from the Flux AI chat and over MCP (Claude, ChatGPT, Cursor…). Tools run with the user's own CRM permissions.")}</p>
          <ul className="flxcrm-ai__status">
            <li>
              <strong>Flux AI &amp; MCP</strong> {ai.aiInstalled ? <span className="flxcrm-badge is-ok">{t('connected')}</span> : <span className="flxcrm-badge">{t('not installed')}</span>}
              <small className="flxcrm-muted">{t('CRM tools in the admin chat. Every tool that changes something waits on an approval card.')}</small>
            </li>
            <li>
              <strong>MCP</strong>{' '}
              {ai.mcpEnabled ? (
                <>
                  <span className="flxcrm-badge is-ok">{t('on')}</span>
                  <code className="flxcrm-ai__url">{ai.mcpUrl}</code>
                </>
              ) : ai.mcpInstalled ? (
                <span className="flxcrm-badge is-warn">{t('off')}</span>
              ) : (
                <span className="flxcrm-badge">{t('Flux AI & MCP is not installed')}</span>
              )}
              <small className="flxcrm-muted">
                {t('Delete, merge, bulk actions and sending return only a preview on the first call; they run when called again with the approval token.')}{' '}
                {ai.mcpAdminUrl && <a href={ai.mcpAdminUrl}>{t('MCP keys')}</a>}
              </small>
            </li>
          </ul>
          <div className="flxcrm-switches flxcrm-ai__perms">
            <label className="flxcrm-check">
              <input type="checkbox" checked={d.aiTools} onChange={(e) => set({ aiTools: e.currentTarget.checked })} /> {t('AI and MCP tools on')}
            </label>
            <label className="flxcrm-check">
              <input type="checkbox" checked={d.aiWrite} onChange={(e) => set({ aiWrite: e.currentTarget.checked })} /> {t('Editing: contacts, tags, segments, notes, settings')}
            </label>
            <label className="flxcrm-check">
              <input type="checkbox" checked={d.aiDestructive} onChange={(e) => set({ aiDestructive: e.currentTarget.checked })} /> {t('Approval actions: delete, merge, bulk actions, sending messages')}
            </label>
          </div>
          <details className="flxcrm-ai__tools">
            <summary>{t('Tools: %1$d / %2$d on', ai.toolsOn, ai.tools.length)}</summary>
            <table className="flxcrm-table flxcrm-table--settings">
              <thead>
                <tr>
                  <th>{t('Tool')}</th>
                  <th>{t('Type')}</th>
                </tr>
              </thead>
              <tbody>
                {ai.tools.map((x) => (
                  <tr key={x.name} className={x.enabled ? undefined : 'is-off'}>
                    <td data-label={t('Tool')}>
                      <strong>{x.title}</strong>
                      <br />
                      <code>{x.name}</code>
                    </td>
                    <td data-label={t('Type')}>
                      <span className={'flxcrm-badge ' + (x.kind === 'read' ? 'is-info' : x.kind === 'write' ? 'is-ok' : 'is-warn')}>{x.kindLabel}</span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </details>
        </Card>

        <p className="flxcrm-actions">
          <button className="button button-primary" disabled={busy}>
            {t('Save settings')}
          </button>
        </p>
      </form>

      <Card title={t('Recent changes made with AI')} icon="clock">
        {q.data.flxCrmAiLog.length === 0 ? (
          <p className="flxcrm-muted">{t('No changes yet. Every change made with Flux AI or MCP shows up here.')}</p>
        ) : (
          <table className="flxcrm-table flxcrm-table--settings">
            <thead>
              <tr>
                <th>{t('When')}</th>
                <th>{t('Who')}</th>
                <th>{t('Action')}</th>
                <th>{t('Result')}</th>
              </tr>
            </thead>
            <tbody>
              {q.data.flxCrmAiLog.map((r, i) => (
                <tr key={i}>
                  <td data-label={t('When')}>{r.at.relative}</td>
                  <td data-label={t('Who')}>
                    {r.who ?? '—'} <span className="flxcrm-badge">{r.channelLabel}</span>
                  </td>
                  <td data-label={t('Action')}>
                    {r.action} {r.target && <small className="flxcrm-muted">{r.target}</small>}
                  </td>
                  <td data-label={t('Result')}>{r.ok ? <span className="flxcrm-badge is-ok">{t('done')}</span> : <span className="flxcrm-badge is-bad" title={r.error ?? ''}>{t('error')}</span>}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </Card>
    </>
  );
}
