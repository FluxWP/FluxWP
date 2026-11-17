// Shared pieces of the Ad conversions screen: environment (translator, notices, confirm), mutation helper,
// KPI cards, tables and the report filter. Markup and class names follow the classic page
// (plugins/flx-ads/assets/admin.css) so both views look the same.
import { createContext, useContext, useState, type ReactNode } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import type { FlxOperation } from '@fluxwp/graphql';
import { flxQueryKey, useFlxMutation, useGraphQL } from '@fluxwp/graphql/react';
import type { FlxAdsContextFragment, FlxAdsFilterInput } from '@fluxwp/graphql/types';
import type { Translate } from './i18n';
import { DatePicker } from '@fluxwp/ui';

export type Tn = (single: string, plural: string, n: number, ...args: (string | number)[]) => string;

export interface Env {
  t: Translate;
  tn: Tn;
  locale: string;
  ctx: FlxAdsContextFragment;
  notify: (kind: 'success' | 'error' | 'info', text: string) => void;
  fail: (e: unknown) => void;
  ask: (c: { text: string; ok: string; danger?: boolean; run: () => void }) => void;
  go: (tab: string, params?: Record<string, string>) => void;
  params: Record<string, string>;
}

export const EnvContext = createContext<Env | null>(null);

export function useEnv(): Env {
  const e = useContext(EnvContext);
  if (!e) throw new Error('Ads environment missing');
  return e;
}

export type Money = { amount: number; currency: string; formatted: string };
export type Payload = { errors: ReadonlyArray<{ message: string }> } | null | undefined;

/** One write: payload errors become a notice, success a notice and a refresh of the listed queries. */
export function useAct<R, V>(
  op: FlxOperation<R, V>,
  pick: (r: R) => Payload,
  done: string | ((r: R) => string),
  refresh: ReadonlyArray<{ name: string }>,
  after?: (r: R) => void,
) {
  const env = useEnv();
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  return useFlxMutation(op, {
    onSuccess: (r) => {
      const p = pick(r);
      if (!p) return env.fail(null);
      if (p.errors.length) return env.notify('error', p.errors.map((e) => e.message).join(' '));
      env.notify('success', typeof done === 'function' ? done(r) : done);
      for (const o of refresh) void qc.invalidateQueries({ queryKey: flxQueryKey(scope, o) });
      after?.(r);
    },
    onError: env.fail,
  });
}

export function Kpi({ label, value, hint }: { label: string; value: string; hint?: string }) {
  return (
    <div className="flxads-kpi">
      <span>{label}</span>
      <strong>{value}</strong>
      {hint ? <small>{hint}</small> : null}
    </div>
  );
}

export const Dot = ({ ch }: { ch: string }) => <span className={'flxads-dot flxads-dot--' + (ch || 'none')} />;

export const Muted = ({ children }: { children: ReactNode }) => <span className="flxads-muted">{children}</span>;

/** "3,2x" */
export function roas(v: number | null | undefined, locale: string): string {
  if (v === null || v === undefined) return '—';
  return v.toLocaleString(locale, { maximumFractionDigits: v < 10 ? 1 : 0, minimumFractionDigits: v < 10 ? 1 : 0 }) + 'x';
}

export const Roas = ({ v, level }: { v: number | null | undefined; level: string }) => {
  const { locale } = useEnv();
  return <span className={'flxads-roas flxads-roas--' + level}>{roas(v, locale)}</span>;
};

export const num = (n: number, locale: string, digits = 0) => n.toLocaleString(locale, { maximumFractionDigits: digits, minimumFractionDigits: digits });

/** Tone of a pill: ok → sent, bad → failed, warn → pending (classic names). */
export const Pill = ({ tone, children }: { tone: string; children: ReactNode }) => (
  <span className={'flxads-pill flxads-pill--' + ({ ok: 'sent', bad: 'failed', warn: 'pending', off: '' } as Record<string, string>)[tone]}>{children}</span>
);

export function Card({ children, pad }: { children: ReactNode; pad?: boolean }) {
  return <section className={'flxads-card' + (pad ? ' flxads-pad' : '')}>{children}</section>;
}

export const Notice = ({ kind, children }: { kind: 'ok' | 'bad'; children: ReactNode }) => <p className={'flxads-test flxads-test--' + kind}>{children}</p>;

export function Btn({ children, onClick, primary, small, danger, disabled, type = 'button' }: { children: ReactNode; onClick?: () => void; primary?: boolean; small?: boolean; danger?: boolean; disabled?: boolean; type?: 'button' | 'submit' }) {
  return (
    <button type={type} className={'flxads-btn' + (primary ? ' is-primary' : '') + (small ? ' is-small' : '') + (danger ? ' is-danger' : '')} onClick={onClick} disabled={disabled}>
      {children}
    </button>
  );
}

/** Table cell with the column name for the stacked mobile layout. */
export const Td = ({ l, num: n, children, className }: { l?: string; num?: boolean; children?: ReactNode; className?: string }) => (
  <td data-label={l} className={(n ? 'num ' : '') + (className ?? '')}>
    {children}
  </td>
);

export const Empty = ({ cols, children }: { cols: number; children: ReactNode }) => (
  <tr>
    <td colSpan={cols} className="flxads-empty">
      {children}
    </td>
  </tr>
);

export const Field = ({ label, children, hint, className }: { label: string; children: ReactNode; hint?: string; className?: string }) => (
  <label className={'flxads-field ' + (className ?? '')}>
    <span>{label}</span>
    {children}
    {hint ? <small>{hint}</small> : null}
  </label>
);

/** Report filter state. `kind` and `by` are what the classic filter bar has. */
export interface Filter {
  range: string;
  from: string;
  to: string;
  kind: string;
  by: string;
  channel: string;
}

export const toVars = (f: Filter): FlxAdsFilterInput => ({
  range: f.range,
  from: f.range === 'custom' ? f.from : undefined,
  to: f.range === 'custom' ? f.to : undefined,
  kind: f.kind,
  by: f.by || undefined,
  channel: f.channel || undefined,
});

export function useFilter(init: Partial<Filter>) {
  const [draft, setDraft] = useState<Filter>({ range: '30', from: '', to: '', kind: 'all', by: 'channel', channel: '', ...init });
  const [applied, setApplied] = useState<Filter>(draft);
  return { draft, setDraft, applied, setApplied, patch: (p: Partial<Filter>) => setDraft((d) => ({ ...d, ...p })) };
}

/** Period / show / group-by bar of the report-like tabs. */
export function FilterBar({
  f,
  dims,
  ranges,
  periodLabel,
  from,
  to,
  showKind = true,
}: {
  f: ReturnType<typeof useFilter>;
  dims?: ReadonlyArray<{ key: string; label: string }>;
  ranges: ReadonlyArray<string>;
  periodLabel?: string;
  from: string;
  to: string;
  showKind?: boolean;
}) {
  const { t } = useEnv();
  const names: Record<string, string> = {
    '7': t('Last 7 days'),
    '30': t('Last 30 days'),
    '90': t('Last 90 days'),
    '365': t('Last 12 months'),
    custom: t('Custom'),
  };
  const d = f.draft;
  return (
    <form
      className="flxads-filters"
      onSubmit={(e) => {
        e.preventDefault();
        f.setApplied({ ...d });
      }}
    >
      <label>
        <span>{periodLabel ?? t('Period')}</span>
        <select value={d.range} onChange={(e) => f.patch({ range: e.target.value, from: d.from || from, to: d.to || to })}>
          {ranges.map((k) => (
            <option key={k} value={k}>
              {names[k]}
            </option>
          ))}
        </select>
      </label>
      {d.range === 'custom' && (
        <>
          <label>
            <span>{t('From')}</span>
            <DatePicker value={d.from || from} onChange={(v) => f.patch({ from: v })} />
          </label>
          <label>
            <span>{t('To')}</span>
            <DatePicker value={d.to || to} onChange={(v) => f.patch({ to: v })} />
          </label>
        </>
      )}
      {showKind && (
        <label>
          <span>{t('Show')}</span>
          <select value={d.kind} onChange={(e) => f.patch({ kind: e.target.value })}>
            <option value="all">{t('Donations and orders')}</option>
            <option value="donations">{t('Donations')}</option>
            <option value="orders">{t('Shop orders')}</option>
          </select>
        </label>
      )}
      {dims && (
        <label>
          <span>{t('Group by')}</span>
          <select value={d.by} onChange={(e) => f.patch({ by: e.target.value })}>
            {dims.map((o) => (
              <option key={o.key} value={o.key}>
                {o.label}
              </option>
            ))}
          </select>
        </label>
      )}
      <Btn type="submit">{t('Apply')}</Btn>
    </form>
  );
}
