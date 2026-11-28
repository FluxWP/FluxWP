// Flux AI › Billing: months, spending limits, provider split and the OpenRouter credit.
import { useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { FlxAiBilling, FlxAiCreditsRefresh } from '@fluxwp/graphql/operations';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { Loading } from '@fluxwp/ui';
import { Chart } from '@fluxwp/ui/chart';
import { BreakdownTable } from './Overview';
import { Card, dayLabel, Pill, errText, useAi, type Notice, NoticeBar, Kpi } from './common';
import { Meters } from './Meters';

export function Billing() {
  const ai = useAi();
  const { t, num, usd } = ai;
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const [month, setMonth] = useState<string | null>(null);
  const [notice, setNotice] = useState<Notice | null>(null);
  const q = useFlxQuery(FlxAiBilling, { month }, { placeholderData: (p) => p });
  const refresh = useFlxMutation(FlxAiCreditsRefresh, {
    onSuccess: (r) => {
      const p = r.flxAiCreditsRefresh;
      if (p?.errors.length) return setNotice({ kind: 'error', text: p.errors.map((e) => e.message).join(' ') });
      void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxAiBilling) });
    },
    onError: (e) => setNotice({ kind: 'error', text: errText(e, t('The action could not be completed.')) }),
  });
  if (q.isLoading) return <Loading />;
  if (q.isError || !q.data) return <div className="flxai-notice is-error">{q.error?.message || t('The action could not be completed.')}</div>;
  const b = q.data.flxAiBilling;
  const tt = b.report.totals;
  const cr = b.credits;
  const cur = b.months.find((m) => m.month === b.month);
  return (
    <>
      <NoticeBar notice={notice} onClose={() => setNotice(null)} t={t} />
      <Meters budget={q.data.flxAiBudget} ai={ai} />
      <div className="flxai-grid flxai-bill">
        <Card title={t('Months')}>
          <ul className="flxai-months">
            {b.months.map((m) => (
              <li key={m.month}>
                <button type="button" className={m.month === b.month ? 'is-on' : ''} onClick={() => setMonth(m.month)}>
                  <span>{m.label}</span>
                  <strong>{usd(m.cost, m.cost > 0 && m.cost < 1 ? 4 : 2)}</strong>
                </button>
              </li>
            ))}
          </ul>
        </Card>
        <Card
          title={
            <span className="flxai-bill-head">
              {cur?.label ?? b.month}
              <a className="flxai-btn" href={b.csvUrl}>
                {t('Download CSV')}
              </a>
            </span>
          }
        >
          <div className="flxai-kpis">
            <Kpi label={t('Total')} value={usd(tt.cost)} sub={t('%s without cache', usd(tt.fullCost))} />
            <Kpi label={t('Cache savings')} value={usd(tt.saved)} sub={tt.fullCost > 0 ? t('%s%% less', num((tt.saved / tt.fullCost) * 100, 1)) : ''} />
            <Kpi label={t('Model requests')} value={num(tt.calls)} sub="" />
          </div>
          <Chart
            kind="area"
            labels={b.report.daily.map((r) => r.date)}
            formatLabel={dayLabel}
            series={[{ label: t('Cost'), data: b.report.daily.map((r) => r.cost), color: 'var(--flux-primary)' }]}
            format={(v) => usd(v)}
          />
          <h3>{t('By provider')}</h3>
          <div className="flxai-tscroll">
            <table className="flxai-table">
              <thead>
                <tr>
                  <th>{t('Provider')}</th>
                  <th className="num">{t('Requests')}</th>
                  <th className="num">{t('Input')}</th>
                  <th className="num">{t('Output')}</th>
                  <th className="num">{t('Without cache')}</th>
                  <th className="num">{t('Cost')}</th>
                </tr>
              </thead>
              <tbody>
                {b.report.byProvider.map((r) => (
                  <tr key={r.key}>
                    <td>
                      {r.label} {r.partial && <Pill>{t('partly estimated')}</Pill>}
                    </td>
                    <td className="num">{num(r.calls)}</td>
                    <td className="num">{num(r.inTokens)}</td>
                    <td className="num">{num(r.outTokens)}</td>
                    <td className="num">{usd(r.fullCost)}</td>
                    <td className="num">
                      <strong>{usd(r.cost)}</strong>
                    </td>
                  </tr>
                ))}
                {b.report.byProvider.length === 0 && (
                  <tr>
                    <td colSpan={6} className="flxai-empty">
                      {t('No records this month.')}
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </Card>
      </div>
      <div className="flxai-grid">
        <BreakdownTable ai={ai} title={t('By plugin')} rows={b.report.bySource} />
        <BreakdownTable ai={ai} title={t('By model')} rows={b.report.byModel} />
      </div>
      <Card
        title={
          <span className="flxai-bill-head">
            {t('OpenRouter account')}
            <button type="button" className="flxai-btn" disabled={refresh.isPending} onClick={() => refresh.mutate({})}>
              {t('Refresh')}
            </button>
          </span>
        }
      >
        {cr ? (
          <dl className="flxai-dl">
            {cr.credits != null && (
              <>
                <dt>{t('Remaining credit')}</dt>
                <dd>{usd(cr.credits, 2)}</dd>
              </>
            )}
            {cr.usage != null && (
              <>
                <dt>{t('Total usage of this key')}</dt>
                <dd>{usd(cr.usage, 2)}</dd>
              </>
            )}
            {cr.limit != null && (
              <>
                <dt>{t('Key limit / remaining')}</dt>
                <dd>{usd(cr.limit, 2) + ' / ' + usd(cr.remaining ?? 0, 2)}</dd>
              </>
            )}
            <dt>{t('Checked')}</dt>
            <dd>{cr.checked.label}</dd>
          </dl>
        ) : (
          <p className="flxai-empty">{t('Press Refresh to see remaining credit. (Anthropic has no balance API; see console.anthropic.com › Billing.)')}</p>
        )}
      </Card>
    </>
  );
}
