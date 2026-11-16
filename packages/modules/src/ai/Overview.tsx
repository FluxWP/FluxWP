// Flux AI › Overview: spending against the limits, usage report, charts and breakdowns.
import { useState } from 'react';
import { FlxAiOverview } from '@fluxwp/graphql/operations';
import type { FlxAiReportFieldsFragment } from '@fluxwp/graphql/types';
import { useFlxQuery } from '@fluxwp/graphql/react';
import { Loading } from '@fluxwp/ui';
import { useHost } from '../host';
import { Bars, Card, Pill, useAi, type AiT } from './common';
import { Meters } from './Meters';

const DAYS = [1, 7, 30, 90];

type Breakdown = FlxAiReportFieldsFragment['bySource'];

export function Overview() {
  const ai = useAi();
  const { t, num, usd, ms } = ai;
  const host = useHost();
  const [days, setDays] = useState(30);
  const [source, setSource] = useState('');
  const q = useFlxQuery(FlxAiOverview, { days, source: source || null }, { placeholderData: (p) => p });
  if (q.isLoading) return <Loading />;
  if (q.isError || !q.data) return <div className="flxai-notice is-error">{q.error?.message || t('The action could not be completed.')}</div>;
  const d = q.data;
  const rep = d.flxAiReport;
  const tt = rep.totals;
  const totMs = Math.max(1, tt.modelMs + tt.toolMs);
  const noKey = !d.flxAiSecrets.some((s) => s.configured);
  const settingsUrl = (host.adminUrl ?? '') + 'admin.php?page=flx-ai-settings';
  return (
    <>
      {noKey && (
        <div className="flxai-notice is-warn">
          {t('No API key entered yet.')} <a href={settingsUrl}>{t('Settings')}</a>
        </div>
      )}
      <div className="flxai-filters">
        <div className="flxai-seg" role="group">
          {DAYS.map((x) => (
            <button key={x} type="button" className={x === days ? 'is-on' : ''} onClick={() => setDays(x)}>
              {x === 1 ? t('24 hours') : t('%d days', x)}
            </button>
          ))}
        </div>
        <label>
          <span className="flxai-sr">{t('Source')}</span>
          <select value={source} onChange={(e) => setSource(e.target.value)}>
            <option value="">{t('All plugins')}</option>
            {d.flxAiLogSources.map((s) => (
              <option key={s.id} value={s.id}>
                {s.label}
              </option>
            ))}
          </select>
        </label>
      </div>

      <Meters budget={d.flxAiBudget} ai={ai} />

      <div className="flxai-kpis">
        <Kpi label={t('Cost')} value={usd(tt.cost)} sub={t('Cache savings %s', usd(tt.saved))} />
        <Kpi label={t('Model requests')} value={num(tt.calls)} sub={t('%s tool calls', num(tt.tools))} />
        <Kpi label={t('Tokens')} value={num(tt.inTokens + tt.outTokens)} sub={t('Input %1$s · output %2$s · from cache %3$s', num(tt.inTokens), num(tt.outTokens), num(tt.cacheRead))} />
        <Kpi label={t('Total time')} value={ms(tt.modelMs + tt.toolMs)} sub={t('Model/thinking %1$s%% · tools %2$s%%', num((tt.modelMs / totMs) * 100), num((tt.toolMs / totMs) * 100))} />
        <Kpi label={t('Errors')} value={num(tt.errors)} sub={tt.calls ? t('%s%% of requests', num((tt.errors / Math.max(1, tt.calls + tt.tools)) * 100, 1)) : ''} />
      </div>

      <div className="flxai-grid">
        <Card title={t('Daily cost (USD)')} wide>
          <Bars rows={rep.daily.map((r) => ({ label: r.date, values: [r.cost] }))} colors={['var(--flux-primary)']} format={(v) => usd(v)} />
        </Card>
        <Card title={t('Where time goes: model/thinking vs tools')} wide>
          <Bars rows={rep.daily.map((r) => ({ label: r.date, values: [r.modelMs, r.toolMs] }))} colors={['var(--flux-primary)', 'var(--flux-warning)']} format={ms} legend={[t('Model/thinking'), t('Tools')]} />
        </Card>
        <Card title={t('Daily tokens')}>
          <Bars rows={rep.daily.map((r) => ({ label: r.date, values: [r.inTokens, r.outTokens] }))} colors={['var(--flux-primary)', 'var(--flux-info, #3b82f6)']} format={(v) => num(v)} legend={[t('Input'), t('Output')]} />
        </Card>
        <Card title={t('Average time per tool')}>
          <Bars horizontal rows={rep.byTool.slice(0, 10).map((r) => ({ label: r.label || r.key, values: [r.avgMs] }))} colors={['var(--flux-warning)']} format={ms} />
        </Card>
      </div>

      <BreakdownTable ai={ai} title={t('By plugin')} rows={rep.bySource} />
      <BreakdownTable ai={ai} title={t('By model')} rows={rep.byModel} />
      <BreakdownTable ai={ai} title={t('By purpose')} rows={rep.byPurpose} />

      <Card title={t('Tools')}>
        {rep.byTool.length === 0 ? (
          <p className="flxai-empty">{t('No records in this range.')}</p>
        ) : (
          <div className="flxai-tscroll">
            <table className="flxai-table">
              <thead>
                <tr>
                  <th>{t('Name')}</th>
                  <th>{t('Plugin')}</th>
                  <th className="num">{t('Calls')}</th>
                  <th className="num">{t('Average')}</th>
                  <th className="num">{t('Longest')}</th>
                  <th className="num">{t('Total time')}</th>
                </tr>
              </thead>
              <tbody>
                {rep.byTool.map((r) => (
                  <tr key={r.key}>
                    <td>
                      {r.label || r.key} {r.errors > 0 && <Pill kind="err">{t('%s errors', num(r.errors))}</Pill>}
                    </td>
                    <td>{r.source}</td>
                    <td className="num">{num(r.calls)}</td>
                    <td className="num">{ms(r.avgMs)}</td>
                    <td className="num">{ms(r.maxMs)}</td>
                    <td className="num">{ms(r.totalMs)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Card>
    </>
  );
}

function Kpi({ label, value, sub }: { label: string; value: string; sub: string }) {
  return (
    <div className="flxai-card flxai-kpi">
      <div className="flxai-muted">{label}</div>
      <div className="flxai-big">{value}</div>
      <div className="flxai-muted">{sub}</div>
    </div>
  );
}

export function BreakdownTable({ ai, title, rows }: { ai: AiT; title: string; rows: Breakdown }) {
  const { t, num, usd, ms } = ai;
  return (
    <Card title={title}>
      {rows.length === 0 ? (
        <p className="flxai-empty">{t('No records in this range.')}</p>
      ) : (
        <div className="flxai-tscroll">
          <table className="flxai-table">
            <thead>
              <tr>
                <th>{t('Name')}</th>
                <th className="num">{t('Requests')}</th>
                <th className="num">{t('Tokens')}</th>
                <th className="num">{t('Model time')}</th>
                <th className="num">{t('Tool time')}</th>
                <th className="num">{t('Cost')}</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((r) => (
                <tr key={r.key}>
                  <td>
                    {r.label} {r.errors > 0 && <Pill kind="err">{t('%s errors', num(r.errors))}</Pill>}
                  </td>
                  <td className="num">{num(r.calls)}</td>
                  <td className="num">{num(r.inTokens + r.outTokens)}</td>
                  <td className="num">{ms(r.modelMs)}</td>
                  <td className="num">{ms(r.toolMs)}</td>
                  <td className="num">
                    {usd(r.cost)}
                    {r.partial ? ' ~' : ''}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </Card>
  );
}
