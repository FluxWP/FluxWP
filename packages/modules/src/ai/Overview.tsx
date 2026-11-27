// Flux AI › Overview: spending against the limits, usage report, charts and breakdowns.
import { useState } from 'react';
import { FlxAiOverview } from '@fluxwp/graphql/operations';
import type { FlxAiReportFieldsFragment } from '@fluxwp/graphql/types';
import { useFlxQuery } from '@fluxwp/graphql/react';
import { Loading } from '@fluxwp/ui';
import { Chart } from '@fluxwp/ui/chart';
import { useHost } from '../host';
import { Card, Pill, dayLabel, useAi, type AiT, Kpi } from './common';
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
  const days1 = rep.daily;
  const noKey = !d.flxAiSettingsOptions.providersReady && !d.flxAiSecrets.some((s) => s.configured);
  const settingsUrl = (host.adminUrl ?? '') + 'admin.php?page=flx-ai-settings';
  return (
    <>
      {noKey && (
        <div className="flxai-notice is-warn">
          {t('No API key entered yet.')} <a href={settingsUrl}>{t('Settings')}</a>
        </div>
      )}
      <Meters budget={d.flxAiBudget} ai={ai} />

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

      <div className="flxai-kpis">
        <Kpi label={t('Cost')} value={usd(tt.cost)} sub={t('Cache savings %s', usd(tt.saved))} />
        <Kpi label={t('Model requests')} value={num(tt.calls)} sub={t('%s tool calls', num(tt.tools))} />
        <Kpi label={t('Tokens')} value={num(tt.inTokens + tt.outTokens)} sub={t('Input %1$s · output %2$s · from cache %3$s', num(tt.inTokens), num(tt.outTokens), num(tt.cacheRead))} />
        <Kpi label={t('Total time')} value={ms(tt.modelMs + tt.toolMs)} sub={t('Model/thinking %1$s%% · tools %2$s%%', num((tt.modelMs / totMs) * 100), num((tt.toolMs / totMs) * 100))} />
        <Kpi label={t('Errors')} value={num(tt.errors)} sub={tt.calls ? t('%s%% of requests', num((tt.errors / Math.max(1, tt.calls + tt.tools)) * 100, 1)) : ''} />
      </div>

      <div className="flxai-grid">
        <Card title={t('Daily cost (USD)')} wide>
          {!days1.some((r) => r.cost > 0) ? (
            <p className="flxai-chart-empty">{t('No data in this range.')}</p>
          ) : (
            <Chart
              kind="bar"
              labels={days1.map((r) => r.date)}
              formatLabel={dayLabel}
              series={[
                { label: t('Cost'), data: days1.map((r) => r.cost), color: 'var(--flux-primary)' },
                { label: t('Model requests'), data: days1.map((r) => r.calls), kind: 'line', axis: 'y2', color: 'var(--flux-warning)' },
              ]}
              format={(v) => usd(v)}
              format2={(v) => num(v)}
              label={t('Daily cost (USD)')}
            />
          )}
        </Card>
        <Card title={t('Where time goes: model/thinking vs tools')} wide>
          {!days1.some((r) => r.modelMs > 0 || r.toolMs > 0) ? (
            <p className="flxai-chart-empty">{t('No data in this range.')}</p>
          ) : (
            <Chart
              kind="bar"
              stacked
              labels={days1.map((r) => r.date)}
              formatLabel={dayLabel}
              series={[
                { label: t('Model/thinking'), data: days1.map((r) => r.modelMs), color: 'var(--flux-primary)' },
                { label: t('Tools'), data: days1.map((r) => r.toolMs), color: 'var(--flux-warning)' },
              ]}
              format={(v) => ms(v)}
            />
          )}
        </Card>
        <Card title={t('Daily tokens')}>
          {!days1.some((r) => r.inTokens > 0 || r.outTokens > 0) ? (
            <p className="flxai-chart-empty">{t('No data in this range.')}</p>
          ) : (
            <Chart
              kind="bar"
              stacked
              labels={days1.map((r) => r.date)}
              formatLabel={dayLabel}
              series={[
                { label: t('Input'), data: days1.map((r) => r.inTokens), color: 'var(--flux-primary)' },
                { label: t('Output'), data: days1.map((r) => r.outTokens), color: 'var(--flux-info, #1D5FA8)' },
                { label: t('From cache'), data: days1.map((r) => r.cacheRead), color: 'var(--flux-success)', hidden: true },
              ]}
              format={(v) => num(v)}
            />
          )}
        </Card>
        <Card title={t('Average time per tool')}>
          {rep.byTool.length === 0 ? (
            <p className="flxai-empty">{t('No records in this range.')}</p>
          ) : (
            <Chart
              kind="bar"
              horizontal
              height={Math.max(160, Math.min(10, rep.byTool.length) * 28 + 30)}
              labels={rep.byTool.slice(0, 10).map((r) => r.label || r.key)}
              series={[{ label: t('Average'), data: rep.byTool.slice(0, 10).map((r) => r.avgMs), color: 'var(--flux-warning)' }]}
              format={(v) => ms(v)}
            />
          )}
        </Card>
        <Card title={t('Cost by plugin')}>
          <Share rows={rep.bySource} ai={ai} />
        </Card>
        <Card title={t('Cost by model')}>
          <Share rows={rep.byModel} ai={ai} />
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

/** Cost share as a doughnut; the long tail folds into "Other" so slices stay readable. */
function Share({ rows, ai }: { rows: Breakdown; ai: AiT }) {
  const { t, usd } = ai;
  const list = rows.filter((r) => r.cost > 0).sort((a, b) => b.cost - a.cost);
  if (list.length === 0) return <p className="flxai-empty">{t('No records in this range.')}</p>;
  const top = list.slice(0, 6);
  const rest = list.slice(6).reduce((a, r) => a + r.cost, 0);
  const labels = top.map((r) => r.label || r.key);
  const data = top.map((r) => r.cost);
  if (rest > 0) {
    labels.push(t('Other'));
    data.push(rest);
  }
  return <Chart kind="doughnut" height={240} labels={labels} series={[{ label: t('Cost'), data }]} format={(v) => usd(v)} />;
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
