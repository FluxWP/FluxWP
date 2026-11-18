// Shared pieces of the Performance screen: translator hook, formats, cards, tables, switches.
import { createContext, useContext, useMemo, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import type { FlxSpeedConfigQuery, FlxSpeedRecordRowFragment } from '@fluxwp/graphql/types';
import { translator, type Translate } from './i18n';

export type Config = FlxSpeedConfigQuery['flxSpeedConfig'];
export type RecordRow = FlxSpeedRecordRowFragment;
export type Tone = 'NONE' | 'WARNING' | 'ERROR';

export interface RecordFilter {
  hours: string;
  flag: string;
  type: string;
  q: string;
  slow: boolean;
}
export const emptyFilter: RecordFilter = { hours: '', flag: '', type: '', q: '', slow: false };

export interface SpeedCtx {
  config: Config;
  /** Opens a tab (and optionally a record or a record filter). */
  go: (tab: string, extra?: { id?: number; filter?: Partial<RecordFilter>; hours?: number }) => void;
  notify: (kind: 'success' | 'error', text: string) => void;
  /** Summary period in hours, shared between the Summary links and the Records filter. */
  hours: number;
  setHours: (h: number) => void;
}

const Ctx = createContext<SpeedCtx | null>(null);
export const SpeedProvider = Ctx.Provider;

export function useSpeed(): SpeedCtx {
  const c = useContext(Ctx);
  if (!c) throw new Error('SpeedProvider missing');
  return c;
}

export function useT() {
  const { i18n } = useTranslation();
  return useMemo(() => ({ ...translator(i18n.language), lang: i18n.language }), [i18n.language]);
}

export type Tr = Translate;

export const num = (n: number, lang: string, digits = 0): string => n.toLocaleString(lang, { minimumFractionDigits: digits, maximumFractionDigits: digits });
export const fmtMs = (ms: number, lang: string): string => (ms >= 1000 ? num(ms / 1000, lang, 2) + ' s' : num(Math.round(ms), lang) + ' ms');
export const fmtKb = (kb: number, lang: string): string => (kb >= 1024 ? num(kb / 1024, lang, 1) + ' MB' : num(kb, lang) + ' KB');
export const fmtBytes = (b: number, lang: string): string => {
  if (!b || b <= 0) return '—';
  const u = ['B', 'KB', 'MB', 'GB', 'TB'];
  let i = 0;
  let v = b;
  while (v >= 1024 && i < u.length - 1) {
    v /= 1024;
    i++;
  }
  return num(v, lang, i ? 1 : 0) + ' ' + u[i];
};

export function toneClass(t: Tone | string): string {
  return t === 'ERROR' ? 'is-err' : t === 'WARNING' ? 'is-warn' : '';
}

export function Cards({ children }: { children: ReactNode }) {
  return <div className="flxpf-cards">{children}</div>;
}

export function Card({ label, value, tone = 'NONE', onClick, extra }: { label: string; value: string; tone?: Tone | string; onClick?: () => void; extra?: ReactNode }) {
  const inner = (
    <>
      <span>{label}</span>
      <b>{value}</b>
      {extra}
    </>
  );
  return onClick ? (
    <button type="button" className={'flxpf-card is-link ' + toneClass(tone)} onClick={onClick}>
      {inner}
    </button>
  ) : (
    <div className={'flxpf-card ' + toneClass(tone)}>{inner}</div>
  );
}

export function Table({ head, rows, empty, numeric = [] }: { head: string[]; rows: ReactNode[][]; empty: string; numeric?: number[] }) {
  return (
    <div className="flxpf-tablewrap">
      <table className="flxpf-list">
        <thead>
          <tr>
            {head.map((h, i) => (
              <th key={i} className={numeric.includes(i) ? 'n' : undefined}>
                {h}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.length === 0 && (
            <tr>
              <td colSpan={head.length} className="flxpf-empty">
                {empty}
              </td>
            </tr>
          )}
          {rows.map((r, i) => (
            <tr key={i}>
              {r.map((c, j) => (
                <td key={j} data-l={head[j]} className={numeric.includes(j) ? 'n' : undefined}>
                  {c}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

/** A titled box of label and value rows (server, database, resources). */
export function Kv({ title, rows }: { title: string; rows: { label: string; value: string }[] }) {
  return (
    <section className="flxpf-box">
      <h2>{title}</h2>
      <table className="flxpf-kv">
        <tbody>
          {rows.map((r, i) => (
            <tr key={i}>
              <th>{r.label}</th>
              <td>{r.value}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </section>
  );
}

export function Switch({ checked, onChange, label, disabled }: { checked: boolean; onChange: (v: boolean) => void; label: string; disabled?: boolean }) {
  return (
    <label className="flxpf-switch">
      <input type="checkbox" checked={checked} disabled={disabled} onChange={(e) => onChange(e.currentTarget.checked)} />
      <span aria-hidden="true" />
      <span className="flxpf-sr">{label}</span>
    </label>
  );
}

export function Check({ checked, onChange, children, disabled }: { checked: boolean; onChange: (v: boolean) => void; children: ReactNode; disabled?: boolean }) {
  return (
    <label className="flxpf-check">
      <input type="checkbox" checked={checked} disabled={disabled} onChange={(e) => onChange(e.currentTarget.checked)} /> {children}
    </label>
  );
}

export function Pill({ on, yes, no }: { on: boolean; yes: string; no: string }) {
  return <span className={'flxpf-pill ' + (on ? 'is-on' : 'is-off')}>{on ? yes : no}</span>;
}

export function Linked({ text, onClick }: { text: string; onClick: () => void }) {
  return (
    <button type="button" className="flxpf-linkbtn" onClick={onClick}>
      {text}
    </button>
  );
}

export function ErrorBox({ error, fallback }: { error: unknown; fallback: string }) {
  const msg = error instanceof Error && error.message ? error.message : fallback;
  return (
    <div className="flxpf-notice is-error" role="alert">
      <span>{msg}</span>
    </div>
  );
}

export function errorText(e: unknown, fallback: string): string {
  return e instanceof Error && e.message ? e.message : fallback;
}
