// Shared bits of the Flux AI screens: formatters, the switch, notices and the small SVG charts.
import { useMemo, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Check } from '@fluxwp/ui';
import { translator } from './i18n';

export interface Notice {
  kind: 'success' | 'error';
  text: string;
}

export function useAi() {
  const { i18n } = useTranslation();
  const lang = i18n.language;
  const { t, tn } = useMemo(() => translator(lang), [lang]);
  const num = (v: number, dec = 0) => new Intl.NumberFormat(lang, { minimumFractionDigits: dec, maximumFractionDigits: dec }).format(v);
  /** "$0.0123"; 4 decimals, 2 from $100. */
  const usd = (v: number, dec = 4) => '$' + num(v, v >= 100 ? 2 : dec);
  const ms = (v: number) => (v < 1000 ? t('%s ms', num(v)) : t('%s s', num(v / 1000, 1)));
  return { t, tn, lang, num, usd, ms };
}

export type AiT = ReturnType<typeof useAi>;

export function errText(e: unknown, fallback: string): string {
  return e instanceof Error && e.message ? e.message : fallback;
}

export function NoticeBar({ notice, onClose, t }: { notice: Notice | null; onClose: () => void; t: AiT['t'] }) {
  if (!notice) return null;
  return (
    <div className={'flxai-notice is-' + notice.kind} role={notice.kind === 'error' ? 'alert' : 'status'}>
      <span>{notice.text}</span>
      <button type="button" className="flxai-x" aria-label={t('Close')} onClick={onClose}>
        ×
      </button>
    </div>
  );
}

export function Switch({ checked, onChange, label, disabled }: { checked: boolean; onChange: (on: boolean) => void; label: string; disabled?: boolean }) {
  return <Check switch checked={checked} disabled={disabled} aria-label={label} onChange={onChange} />;
}

export function Card({ title, children, wide }: { title?: ReactNode; children: ReactNode; wide?: boolean }) {
  return (
    <section className={'flxai-card' + (wide ? ' is-wide' : '')}>
      {title && <h2>{title}</h2>}
      {children}
    </section>
  );
}

export function Pill({ children, kind }: { children: ReactNode; kind?: 'err' | 'ok' | 'warn' }) {
  return <span className={'flxai-pill' + (kind ? ' is-' + kind : '')}>{children}</span>;
}

export interface BarRow {
  label: string;
  values: number[];
}

/** Vertical (days) or horizontal (names) bars; several values per row stack on top of each other. */
export function Bars({ rows, colors, format, horizontal, legend }: { rows: BarRow[]; colors: string[]; format: (v: number) => string; horizontal?: boolean; legend?: string[] }) {
  const total = (r: BarRow) => r.values.reduce((a, b) => a + b, 0);
  const max = Math.max(...rows.map(total), 0) || 1;
  return (
    <div className={'flxai-chart' + (horizontal ? ' is-h' : '')}>
      {legend && (
        <div className="flxai-legend">
          {legend.map((l, i) => (
            <span key={l}>
              <i style={{ background: colors[i] }} /> {l}
            </span>
          ))}
        </div>
      )}
      <div className="flxai-bars">
        {rows.map((r, idx) => (
          <div key={r.label} className="flxai-bar" title={`${r.label}: ${format(total(r))}`}>
            {horizontal && <span className="flxai-bar-label">{r.label}</span>}
            <div className="flxai-bar-track">
              {r.values.map((v, i) => (
                <div key={i} className="flxai-bar-seg" style={{ background: colors[i], [horizontal ? 'width' : 'height']: `${(v / max) * 100}%` }} />
              ))}
            </div>
            {horizontal ? <span className="flxai-bar-val">{format(total(r))}</span> : <span className="flxai-bar-label">{idx % Math.ceil(rows.length / 8) === 0 ? r.label.slice(5) : '\u00a0'}</span>}
          </div>
        ))}
      </div>
    </div>
  );
}
