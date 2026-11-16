// Form controls of the Chatbot settings screen. Same markup and class names as the classic page.
import type { ReactNode } from 'react';

export type Tr = (source: string, ...args: (string | number)[]) => string;

export function Switch({ label, help, on, onChange, disabled }: { label: string; help?: string; on: boolean; onChange: (v: boolean) => void; disabled?: boolean }) {
  return (
    <label className={'flxcb-toggle' + (disabled ? ' is-off' : '')}>
      <span className="flxcb-toggle__t">
        <strong>{label}</strong>
        {help && <small>{help}</small>}
      </span>
      <span className="flxcb-switch">
        <input type="checkbox" checked={on} disabled={disabled} onChange={(e) => onChange(e.target.checked)} />
        <span className="flxcb-switch__ui" aria-hidden="true" />
      </span>
    </label>
  );
}

export function Text({ label, help, value, onChange, placeholder, type = 'text', min, max }: { label: string; help?: string; value: string | number | null; onChange: (v: string) => void; placeholder?: string; type?: 'text' | 'email' | 'number'; min?: number; max?: number }) {
  return (
    <label className="flxcb-field">
      <span>{label}</span>
      <input type={type} value={value ?? ''} placeholder={placeholder} min={min} max={max} onChange={(e) => onChange(e.target.value)} />
      {help && <small>{help}</small>}
    </label>
  );
}

export function Area({ label, help, value, onChange, rows = 3, placeholder }: { label: string; help?: string; value: string | null; onChange: (v: string) => void; rows?: number; placeholder?: string }) {
  return (
    <label className="flxcb-field">
      <span>{label}</span>
      <textarea rows={rows} value={value ?? ''} placeholder={placeholder} onChange={(e) => onChange(e.target.value)} />
      {help && <small>{help}</small>}
    </label>
  );
}

export function Select<V extends string>({ label, help, value, options, onChange }: { label: string; help?: string; value: V | null; options: Array<[V, string]>; onChange: (v: V) => void }) {
  return (
    <label className="flxcb-field">
      <span>{label}</span>
      <select value={value ?? options[0]?.[0] ?? ''} onChange={(e) => onChange(e.target.value as V)}>
        {options.map(([v, l]) => (
          <option key={v} value={v}>
            {l}
          </option>
        ))}
      </select>
      {help && <small>{help}</small>}
    </label>
  );
}

export function Range({ label, value, min, max, unit = 'px', onChange }: { label: string; value: number | null; min: number; max: number; unit?: string; onChange: (v: number) => void }) {
  const v = value ?? min;
  return (
    <label className="flxcb-field">
      <span>
        {label} <output>{v + unit}</output>
      </span>
      <input type="range" min={min} max={max} value={v} onChange={(e) => onChange(Number(e.target.value))} />
    </label>
  );
}

export function Choices<V extends string>({ value, options, onChange, cols = 3 }: { value: V | null; options: Array<{ value: V; title: string; text?: string; icon?: ReactNode }>; onChange: (v: V) => void; cols?: number }) {
  return (
    <div className="flxcb-choices" style={{ ['--cols' as string]: cols }}>
      {options.map((o) => (
        <label key={o.value} className="flxcb-choice">
          <input type="radio" checked={value === o.value} onChange={() => onChange(o.value)} />
          {o.icon && <span className="flxcb-choice__i">{o.icon}</span>}
          <span>
            <strong>{o.title}</strong>
            {o.text && <small>{o.text}</small>}
          </span>
        </label>
      ))}
    </div>
  );
}

export function IconPick<V extends string>({ value, options, onChange }: { value: V | null; options: Array<{ value: V; title: string; icon: ReactNode }>; onChange: (v: V) => void }) {
  return (
    <div className="flxcb-icons">
      {options.map((o) => (
        <label key={o.value} className="flxcb-iconpick" title={o.title}>
          <input type="radio" checked={value === o.value} onChange={() => onChange(o.value)} />
          <span>{o.icon}</span>
          <small>{o.title}</small>
        </label>
      ))}
    </div>
  );
}

export function Check({ label, on, onChange }: { label: string; on: boolean; onChange: (v: boolean) => void }) {
  return (
    <label className="flxcb-check">
      <input type="checkbox" checked={on} onChange={(e) => onChange(e.target.checked)} /> {label}
    </label>
  );
}

export function Card({ id, icon, title, desc, children }: { id: string; icon: ReactNode; title: string; desc?: string; children: ReactNode }) {
  return (
    <section className="flxcb-card" id={id}>
      <div className="flxcb-card__head">
        <h2>
          {icon}
          {title}
        </h2>
      </div>
      {desc && <p className="flxcb-muted">{desc}</p>}
      {children}
    </section>
  );
}

export function Kpi({ icon, label, value, hint, ok }: { icon: ReactNode; label: string; value: string; hint?: string; ok?: boolean }) {
  return (
    <div className={'flxcb-kpi' + (ok ? ' is-ok' : '')}>
      <span className="flxcb-kpi__icon">{icon}</span>
      <span className="flxcb-kpi__body">
        <span className="flxcb-kpi__label">{label}</span>
        <span className="flxcb-kpi__value">{value}</span>
        {hint && <span className="flxcb-kpi__hint">{hint}</span>}
      </span>
    </div>
  );
}
