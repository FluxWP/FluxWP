'use client';
// One component per Flux Forms element. FluxField picks the right one from the type; each can be used alone too.
import { useEffect, useState, type ReactNode } from 'react';
import { sanitizeHtml } from '../sanitize';
import { fill } from './labels';
import { accept, dateLimit, fieldKind, inputType, isMulti, setting } from './logic';
import type { FluxFormField, FluxFormLabels, FluxFormValue } from './types';

export interface FluxFieldProps {
  field: FluxFormField;
  value: FluxFormValue | undefined;
  onChange: (value: FluxFormValue) => void;
  /** The field's error message ('' when none). */
  error?: string;
  labels: FluxFormLabels;
  /** Unique prefix for element ids (the form id). */
  idPrefix: string;
  disabled?: boolean;
  /** File fields: sends the file to the site and resolves its URL (POST /wp-json/Emsfb/v1/forms/file/upload). */
  onUpload?: (file: File, field: FluxFormField) => Promise<string>;
  /** Total element: the running sum and the form currency. */
  total?: number;
  currency?: string;
}

const str = (v: FluxFormValue | undefined) => (Array.isArray(v) ? (v[0] ?? '') : (v ?? ''));
const list = (v: FluxFormValue | undefined) => (Array.isArray(v) ? v : v ? [v] : []);

export function money(n: number, currency?: string): string {
  if (currency && /^[A-Z]{3}$/.test(currency)) {
    try {
      return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(n);
    } catch {
      // Unknown code: plain number below.
    }
  }
  return (Math.round(n * 100) / 100).toString() + (currency ? ' ' + currency : '');
}

/** Label, help and error around one control. */
export function FieldShell({ field, idPrefix, error, children, group }: { field: FluxFormField; idPrefix: string; error?: string; children: ReactNode; group?: boolean }) {
  const id = `${idPrefix}-${field.id}`;
  const label = field.label ? (
    <>
      {field.label}
      {field.required ? (
        <span className="flxff-req" aria-hidden="true">
          {' '}
          *
        </span>
      ) : null}
    </>
  ) : null;
  const help = field.help ? (
    <p className="flxff-help" id={id + '-help'}>
      {field.help}
    </p>
  ) : null;
  const err = error ? (
    <p className="flxff-error" id={id + '-error'} role="alert">
      {error}
    </p>
  ) : null;
  const cls = ['flxff-field', 'flxff-field--' + fieldKind(field.type), error ? 'is-invalid' : ''].filter(Boolean).join(' ');
  if (group) {
    return (
      <fieldset className={cls} aria-describedby={describedBy(field, idPrefix, error)}>
        {label ? <legend className="flxff-label">{label}</legend> : null}
        {children}
        {help}
        {err}
      </fieldset>
    );
  }
  return (
    <div className={cls}>
      {label ? (
        <label className="flxff-label" htmlFor={id}>
          {label}
        </label>
      ) : null}
      {children}
      {help}
      {err}
    </div>
  );
}

function describedBy(field: FluxFormField, idPrefix: string, error?: string): string | undefined {
  const id = `${idPrefix}-${field.id}`;
  const out = [field.help ? id + '-help' : '', error ? id + '-error' : ''].filter(Boolean).join(' ');
  return out || undefined;
}

function aria(p: FluxFieldProps) {
  return {
    id: `${p.idPrefix}-${p.field.id}`,
    name: p.field.id,
    required: p.field.required,
    disabled: p.disabled,
    'aria-invalid': p.error ? true : undefined,
    'aria-describedby': describedBy(p.field, p.idPrefix, p.error),
  } as const;
}

export function TextField(p: FluxFieldProps) {
  const type = inputType(p.field);
  const t = p.field.type.toLowerCase();
  const limits =
    type === 'number'
      ? { min: p.field.min || undefined, max: p.field.max || undefined, step: 'any' }
      : type === 'date'
        ? { min: dateLimit(p.field.min) || undefined, max: dateLimit(p.field.max) || undefined }
        : { maxLength: Number(p.field.max) > 0 ? Number(p.field.max) : undefined };
  const auto = t === 'email' ? 'email' : t === 'tel' || t === 'mobile' ? 'tel' : t === 'url' ? 'url' : undefined;
  return (
    <FieldShell field={p.field} idPrefix={p.idPrefix} error={p.error}>
      <input
        className="flxff-input"
        type={type}
        value={str(p.value)}
        placeholder={p.field.placeholder || undefined}
        autoComplete={auto}
        inputMode={t === 'prcfld' ? 'decimal' : undefined}
        onChange={(e) => p.onChange(e.target.value)}
        {...limits}
        {...aria(p)}
      />
    </FieldShell>
  );
}

export function TextareaField(p: FluxFieldProps) {
  return (
    <FieldShell field={p.field} idPrefix={p.idPrefix} error={p.error}>
      <textarea
        className="flxff-input flxff-textarea"
        rows={4}
        value={str(p.value)}
        placeholder={p.field.placeholder || undefined}
        maxLength={Number(p.field.max) > 0 ? Number(p.field.max) : undefined}
        onChange={(e) => p.onChange(e.target.value)}
        {...aria(p)}
      />
    </FieldShell>
  );
}

export function RangeField(p: FluxFieldProps) {
  const min = p.field.min || '0';
  const max = p.field.max || '100';
  const v = str(p.value) || min;
  return (
    <FieldShell field={p.field} idPrefix={p.idPrefix} error={p.error}>
      <div className="flxff-range">
        <input type="range" min={min} max={max} value={v} onChange={(e) => p.onChange(e.target.value)} {...aria(p)} />
        <output htmlFor={`${p.idPrefix}-${p.field.id}`}>{v}</output>
      </div>
    </FieldShell>
  );
}

export function ColorField(p: FluxFieldProps) {
  const v = /^#[0-9a-f]{6}$/i.test(str(p.value)) ? str(p.value) : '#000000';
  return (
    <FieldShell field={p.field} idPrefix={p.idPrefix} error={p.error}>
      <div className="flxff-color">
        <input type="color" value={v} onChange={(e) => p.onChange(e.target.value)} {...aria(p)} />
        <code>{str(p.value) || '—'}</code>
      </div>
    </FieldShell>
  );
}

function optionText(o: { label: string; price?: number | null }, currency?: string) {
  return o.price != null ? `${o.label} (${money(o.price, currency)})` : o.label;
}

export function SelectField(p: FluxFieldProps) {
  // Country, state and city lists without options (the site loads them from its own files): a plain text field.
  if (!p.field.options.length) return <TextField {...p} />;
  return (
    <FieldShell field={p.field} idPrefix={p.idPrefix} error={p.error}>
      <select className="flxff-input flxff-select" value={str(p.value)} onChange={(e) => p.onChange(e.target.value)} {...aria(p)}>
        <option value="">{p.field.placeholder || p.labels.choose}</option>
        {p.field.options.map((o) => (
          <option key={o.value} value={o.value}>
            {optionText(o, p.currency)}
          </option>
        ))}
      </select>
    </FieldShell>
  );
}

export function MultiSelectField(p: FluxFieldProps) {
  return (
    <FieldShell field={p.field} idPrefix={p.idPrefix} error={p.error}>
      <select
        className="flxff-input flxff-select flxff-multi"
        multiple
        value={list(p.value)}
        size={Math.min(6, Math.max(3, p.field.options.length))}
        onChange={(e) => p.onChange([...e.target.selectedOptions].map((o) => o.value))}
        {...aria(p)}
      >
        {p.field.options.map((o) => (
          <option key={o.value} value={o.value}>
            {optionText(o, p.currency)}
          </option>
        ))}
      </select>
    </FieldShell>
  );
}

export function ChoicesField(p: FluxFieldProps) {
  const multi = isMulti(p.field);
  const picked = list(p.value);
  const cols = Math.min(3, Math.max(1, Number(setting(p.field, 'op_style')) || 1));
  const opts = p.field.options.length ? p.field.options : [{ value: '1', label: p.field.label }];
  const toggle = (v: string, on: boolean) => {
    if (!multi) p.onChange(v);
    else p.onChange(on ? [...picked.filter((x) => x !== v), v] : picked.filter((x) => x !== v));
  };
  return (
    <FieldShell field={p.field} idPrefix={p.idPrefix} error={p.error} group>
      <div className="flxff-choices" style={{ ['--flxff-cols' as string]: cols }}>
        {opts.map((o, i) => (
          <label key={o.value} className="flxff-choice">
            <input
              type={multi ? 'checkbox' : 'radio'}
              name={p.field.id}
              value={o.value}
              checked={picked.includes(o.value)}
              disabled={p.disabled}
              required={p.field.required && !multi && i === 0}
              onChange={(e) => toggle(o.value, e.target.checked)}
            />
            <span>{optionText(o, p.currency)}</span>
          </label>
        ))}
      </div>
    </FieldShell>
  );
}

export function ImageChoiceField(p: FluxFieldProps) {
  const picked = str(p.value);
  return (
    <FieldShell field={p.field} idPrefix={p.idPrefix} error={p.error} group>
      <div className="flxff-images">
        {p.field.options.map((o) => (
          <label key={o.value} className={'flxff-image' + (picked === o.value ? ' is-on' : '')}>
            <input type="radio" name={p.field.id} value={o.value} checked={picked === o.value} disabled={p.disabled} onChange={() => p.onChange(o.value)} />
            {o.image ? <img src={o.image} alt="" loading="lazy" /> : <span className="flxff-image__blank" aria-hidden="true" />}
            <span>{optionText(o, p.currency)}</span>
          </label>
        ))}
      </div>
    </FieldShell>
  );
}

export function YesNoField(p: FluxFieldProps) {
  const yes = setting(p.field, 'button_1_text') || p.labels.yes;
  const no = setting(p.field, 'button_2_text') || p.labels.no;
  const v = str(p.value);
  return (
    <FieldShell field={p.field} idPrefix={p.idPrefix} error={p.error} group>
      <div className="flxff-yesno" role="radiogroup">
        {[yes, no].map((label) => (
          <button key={label} type="button" role="radio" aria-checked={v === label} className={'flxff-yesno__btn' + (v === label ? ' is-on' : '')} disabled={p.disabled} onClick={() => p.onChange(label)}>
            {label}
          </button>
        ))}
      </div>
    </FieldShell>
  );
}

export function SwitchField(p: FluxFieldProps) {
  const on = str(p.value) === '1';
  const text = on ? setting(p.field, 'on') || p.labels.on : setting(p.field, 'off') || p.labels.off;
  return (
    <FieldShell field={p.field} idPrefix={p.idPrefix} error={p.error}>
      <label className="flxff-switch">
        <input type="checkbox" role="switch" checked={on} onChange={(e) => p.onChange(e.target.checked ? '1' : '0')} {...aria(p)} />
        <span className="flxff-switch__track" aria-hidden="true" />
        <span>{text}</span>
      </label>
    </FieldShell>
  );
}

export function FileField(p: FluxFieldProps) {
  const [busy, setBusy] = useState(false);
  const [name, setName] = useState('');
  const [err, setErr] = useState('');
  const maxMb = Number(setting(p.field, 'max_fsize')) || 0;
  const pick = async (file: File | undefined) => {
    setErr('');
    if (!file || !p.onUpload) return;
    if (maxMb > 0 && file.size > maxMb * 1024 * 1024) {
      setErr(fill(p.labels.fileTooBig, { max: maxMb }));
      return;
    }
    setBusy(true);
    try {
      const url = await p.onUpload(file, p.field);
      setName(file.name);
      p.onChange(url);
    } catch {
      setErr(p.labels.uploadFailed);
      p.onChange('');
    } finally {
      setBusy(false);
    }
  };
  if (!p.onUpload) return <UnsupportedField {...p} />;
  return (
    <FieldShell field={p.field} idPrefix={p.idPrefix} error={err || p.error}>
      <label className={'flxff-file' + (p.field.type.toLowerCase() === 'dadfile' ? ' is-drop' : '')}>
        <input type="file" accept={accept(p.field)} onChange={(e) => void pick(e.target.files?.[0])} {...aria(p)} disabled={p.disabled || busy} />
        <span>{busy ? p.labels.uploading : name || p.field.placeholder || p.labels.file}</span>
      </label>
    </FieldShell>
  );
}

function Scale({ p, from, to, star }: { p: FluxFieldProps; from: number; to: number; star?: boolean }) {
  const v = Number(str(p.value)) || 0;
  const items: number[] = [];
  for (let i = from; i <= to; i++) items.push(i);
  return (
    <FieldShell field={p.field} idPrefix={p.idPrefix} error={p.error} group>
      <div className={star ? 'flxff-stars' : 'flxff-scale'} role="radiogroup">
        {items.map((n) => {
          const on = star ? str(p.value) !== '' && n <= v : str(p.value) === String(n);
          return (
            <button key={n} type="button" role="radio" aria-checked={str(p.value) === String(n)} aria-label={String(n)} className={on ? 'is-on' : ''} disabled={p.disabled} onClick={() => p.onChange(String(n))}>
              {star ? '★' : n}
            </button>
          );
        })}
      </div>
    </FieldShell>
  );
}

export const RatingField = (p: FluxFieldProps) => <Scale p={p} from={1} to={5} star />;
export const Score5Field = (p: FluxFieldProps) => <Scale p={p} from={1} to={5} />;
export const Score10Field = (p: FluxFieldProps) => <Scale p={p} from={0} to={10} />;

export function HeadingElement({ field }: FluxFieldProps) {
  return (
    <div className="flxff-heading">
      <h3>{field.label}</h3>
      {field.help ? <p className="flxff-help">{field.help}</p> : null}
    </div>
  );
}

export function LinkElement({ field }: FluxFieldProps) {
  const href = setting(field, 'href');
  if (!/^https?:\/\//i.test(href) && !href.startsWith('/')) return <p className="flxff-link">{field.label}</p>;
  return (
    <p className="flxff-link">
      <a href={href} target="_blank" rel="noopener noreferrer">
        {field.label || href}
      </a>
    </p>
  );
}

export function HtmlElement({ field }: FluxFieldProps) {
  // The site already cleans it (wp_kses_post); it is cleaned again in the browser, after mount so SSR and hydration match.
  const [html, setHtml] = useState('');
  useEffect(() => setHtml(sanitizeHtml(field.defaultValue ?? '')), [field.defaultValue]);
  return <div className="flxff-html" dangerouslySetInnerHTML={{ __html: html }} />;
}

export function TotalElement(p: FluxFieldProps) {
  return (
    <div className="flxff-field flxff-total">
      <span className="flxff-label">{p.field.label || p.labels.total}</span>
      <strong aria-live="polite">{money(p.total ?? 0, p.currency)}</strong>
    </div>
  );
}

export function UnsupportedField({ field, labels }: FluxFieldProps) {
  return (
    <div className="flxff-field flxff-field--unsupported">
      {field.label ? <span className="flxff-label">{field.label}</span> : null}
      <p className="flxff-help">{labels.unsupported}</p>
    </div>
  );
}

/** Every element component by kind. Pass `components` to FluxForm to replace any of them. */
export const FLUX_FIELD_COMPONENTS = {
  input: TextField,
  textarea: TextareaField,
  range: RangeField,
  color: ColorField,
  select: SelectField,
  multiselect: MultiSelectField,
  choices: ChoicesField,
  images: ImageChoiceField,
  yesno: YesNoField,
  switch: SwitchField,
  file: FileField,
  rating: RatingField,
  points5: Score5Field,
  points10: Score10Field,
  heading: HeadingElement,
  link: LinkElement,
  html: HtmlElement,
  total: TotalElement,
  unsupported: UnsupportedField,
} satisfies Record<ReturnType<typeof fieldKind>, (p: FluxFieldProps) => ReactNode>;

export type FluxFieldComponents = Partial<Record<keyof typeof FLUX_FIELD_COMPONENTS, (p: FluxFieldProps) => ReactNode>>;

/** Draws any Flux Forms element from its type. */
export function FluxField({ components, ...p }: FluxFieldProps & { components?: FluxFieldComponents }) {
  const kind = fieldKind(p.field.type);
  const C = components?.[kind] ?? FLUX_FIELD_COMPONENTS[kind];
  return <C {...p} />;
}
