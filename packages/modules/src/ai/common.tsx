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

/** Short day label for chart axes and tooltips: "2026-11-24" → "24 Kas" (page locale). */
export function dayLabel(d: string): string {
  const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(d);
  if (!m) return d;
  const dt = new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]));
  try {
    return dt.toLocaleDateString(document.documentElement.lang || undefined, { day: 'numeric', month: 'short' });
  } catch {
    return d.slice(5);
  }
}
