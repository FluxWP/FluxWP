// Spending against the day / week / month limits.
import type { FlxAiBudgetRowFragment } from '@fluxwp/graphql/types';
import type { AiT } from './common';

export function Meters({ budget, ai }: { budget: ReadonlyArray<FlxAiBudgetRowFragment>; ai: Pick<AiT, 't' | 'usd'> }) {
  const { t, usd } = ai;
  return (
    <div className="flxai-meters">
      {budget.map((b) => (
        <div key={b.key} className={'flxai-card flxai-meter' + (b.over ? ' is-over' : b.pct >= 80 ? ' is-warn' : '')}>
          <h3>{b.label}</h3>
          <div className="flxai-big">{usd(b.spent, 2)}</div>
          <div className="flxai-track">
            <div style={{ width: `${Math.min(100, b.pct)}%` }} />
          </div>
          <div className="flxai-muted">{b.limit > 0 ? t('Limit %1$s · resets %2$s', usd(b.limit, 2), b.resets.label) : t('No limit')}</div>
        </div>
      ))}
    </div>
  );
}
