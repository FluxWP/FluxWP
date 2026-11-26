// Spending against the day / week / month limits.
import type { FlxAiBudgetRowFragment } from '@fluxwp/graphql/types';
import type { AiT } from './common';

export function Meters({ budget, ai }: { budget: ReadonlyArray<FlxAiBudgetRowFragment>; ai: Pick<AiT, 't' | 'usd'> }) {
  const { t, usd } = ai;
  return (
    <div className="flxai-meters">
      {budget.map((b) => (
        <div key={b.key} className={'flxai-card flxai-meter' + (b.over ? ' is-over' : b.pct >= 80 ? ' is-warn' : '')}>
          <div className="flxai-kpi-l">{b.label}</div>
          <div className="flxai-big">{usd(b.spent)}</div>
          {b.limit > 0 && (
            <div className="flxai-track">
              <div style={{ width: `${Math.min(100, b.pct)}%` }} />
            </div>
          )}
          <div className="flxai-muted">{b.limit > 0 ? t('Limit %1$s · resets %2$s', usd(b.limit, 2), b.resets.label) : t('No limit')}</div>
        </div>
      ))}
    </div>
  );
}
