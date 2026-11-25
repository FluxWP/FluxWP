// Pure form logic (no React): which component draws a type, starting values, checks, the submit payload.
import { fill } from './labels';
import type { FluxFormAnswer, FluxFormData, FluxFormError, FluxFormField, FluxFormLabels, FluxFormValue, FluxFormValues } from './types';

export type FluxFieldKind =
  | 'input'
  | 'textarea'
  | 'range'
  | 'color'
  | 'select'
  | 'multiselect'
  | 'choices'
  | 'images'
  | 'yesno'
  | 'switch'
  | 'file'
  | 'rating'
  | 'points5'
  | 'points10'
  | 'heading'
  | 'link'
  | 'html'
  | 'total'
  | 'unsupported';

const KINDS: Record<string, FluxFieldKind> = {
  text: 'input',
  email: 'input',
  tel: 'input',
  mobile: 'input',
  url: 'input',
  number: 'input',
  date: 'input',
  prcfld: 'input',
  textarea: 'textarea',
  range: 'range',
  color: 'color',
  select: 'select',
  payselect: 'select',
  conturylist: 'select',
  stateprovince: 'select',
  citylist: 'select',
  multiselect: 'multiselect',
  paymultiselect: 'multiselect',
  radio: 'choices',
  checkbox: 'choices',
  chlradio: 'choices',
  chlcheckbox: 'choices',
  trmcheckbox: 'choices',
  payradio: 'choices',
  paycheckbox: 'choices',
  imgradio: 'images',
  yesno: 'yesno',
  switch: 'switch',
  file: 'file',
  dadfile: 'file',
  audio_recorder: 'file',
  video_recorder: 'file',
  screen_recorder: 'file',
  rating: 'rating',
  pointr5: 'points5',
  pointr10: 'points10',
  heading: 'heading',
  link: 'link',
  html: 'html',
  ttlprc: 'total',
};

/** Which component draws the type. Types flxFormSubmit cannot take (signature, map, password, card payment, ...) are 'unsupported'. */
export function fieldKind(type: string): FluxFieldKind {
  return KINDS[type.toLowerCase()] ?? 'unsupported';
}

/** The field is answered (it is sent with the form). */
export function isAnswer(field: FluxFormField): boolean {
  const k = fieldKind(field.type);
  return !['heading', 'link', 'html', 'total', 'unsupported'].includes(k);
}

/** The field takes several values. */
export function isMulti(field: FluxFormField): boolean {
  const t = field.type.toLowerCase();
  return t === 'multiselect' || t === 'paymultiselect' || t.includes('checkbox');
}

/** The input type of an 'input' field. */
export function inputType(field: FluxFormField): string {
  const t = field.type.toLowerCase();
  if (t === 'mobile') return 'tel';
  if (t === 'prcfld') return 'number';
  return ['email', 'tel', 'url', 'number', 'date'].includes(t) ? t : 'text';
}

/** One setting of the field as text ('' when it is not set). */
export function setting(field: FluxFormField, key: string): string {
  const s = field.settings;
  if (!s || typeof s !== 'object' || Array.isArray(s)) return '';
  const v = (s as Record<string, unknown>)[key];
  return typeof v === 'string' || typeof v === 'number' || typeof v === 'boolean' ? String(v) : '';
}

/** Date limit: '1' is today. */
export function dateLimit(raw: string | undefined, today = new Date()): string {
  const v = (raw ?? '').trim();
  if (v === '1') {
    const p = (n: number) => String(n).padStart(2, '0');
    return `${today.getFullYear()}-${p(today.getMonth() + 1)}-${p(today.getDate())}`;
  }
  return /^\d{4}-\d{2}-\d{2}$/.test(v) ? v : '';
}

function num(raw: string | undefined): number | null {
  if (raw == null || raw.trim() === '') return null;
  const n = Number(raw);
  return Number.isFinite(n) ? n : null;
}

/** The step list of the form (one step when it has none). */
export function formSteps(form: FluxFormData): number[] {
  const set = new Set<number>();
  for (const f of form.fields) set.add(Math.max(1, f.step ?? 1));
  for (const s of form.stepList ?? []) if (form.fields.some((f) => (f.step ?? 1) === s.number)) set.add(s.number);
  const list = [...set].sort((a, b) => a - b);
  return list.length ? list : [1];
}

/** Values the form opens with. */
export function initialValues(form: FluxFormData): FluxFormValues {
  const out: FluxFormValues = {};
  for (const f of form.fields) {
    if (!isAnswer(f)) continue;
    const d = f.defaultValue ?? '';
    if (isMulti(f)) {
      out[f.id] = d ? d.split(/\s*(?:,|@efb!)\s*/).filter((v) => f.options.some((o) => o.value === v)) : [];
    } else if (fieldKind(f.type) === 'switch') {
      out[f.id] = ['1', 'true', 'on', 'yes'].includes(d.toLowerCase()) ? '1' : '0';
    } else if (fieldKind(f.type) === 'range' && d === '') {
      out[f.id] = String(num(f.min) ?? 0);
    } else {
      out[f.id] = d;
    }
  }
  return out;
}

function empty(v: FluxFormValue | undefined): boolean {
  return Array.isArray(v) ? v.length === 0 : (v ?? '').trim() === '';
}

const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const URL_RE = /^(https?:\/\/)?[^\s/$.?#][^\s.]*\.[^\s]{2,}$/i;
const TEL = /^\+?[\d\s()\-.]{6,20}$/;

/** The field's error message, or '' when its value is fine. */
export function validateField(field: FluxFormField, value: FluxFormValue | undefined, labels: FluxFormLabels): string {
  if (!isAnswer(field)) return '';
  const kind = fieldKind(field.type);
  const blank = empty(value) || (kind === 'switch' && field.required && value === '0');
  if (blank) return field.required ? labels.required : '';
  if (Array.isArray(value)) {
    const lo = num(setting(field, 'minSelect'));
    const hi = num(setting(field, 'maxSelect'));
    if (lo != null && lo > 0 && value.length < lo) return fill(labels.minSelect, { min: lo });
    if (hi != null && hi > 0 && value.length > hi) return fill(labels.maxSelect, { max: hi });
    return '';
  }
  const v = (value ?? '').trim();
  const type = field.type.toLowerCase();
  if (type === 'email' && !EMAIL.test(v)) return labels.email;
  if (type === 'url' && !URL_RE.test(v)) return labels.url;
  if ((type === 'tel' || type === 'mobile') && !TEL.test(v)) return labels.tel;
  if (type === 'number' || type === 'range' || type === 'prcfld') {
    const n = Number(v);
    if (!Number.isFinite(n)) return labels.number;
    const lo = num(field.min);
    const hi = num(field.max);
    if (lo != null && n < lo) return fill(labels.min, { min: lo });
    if (hi != null && n > hi) return fill(labels.max, { max: hi });
    return '';
  }
  if (type === 'date') {
    const lo = dateLimit(field.min);
    const hi = dateLimit(field.max);
    if (lo && v < lo) return fill(labels.minDate, { min: lo });
    if (hi && v > hi) return fill(labels.maxDate, { max: hi });
    return '';
  }
  if (kind === 'input' || kind === 'textarea') {
    const lo = num(field.min);
    const hi = num(field.max);
    const len = [...v].length;
    if (lo != null && lo > 0 && len < lo) return fill(labels.minLength, { min: lo });
    if (hi != null && hi > 0 && len > hi) return fill(labels.maxLength, { max: hi });
  }
  return '';
}

/** Errors of the fields on one step (or every field when step is null), by field id. */
export function validateStep(form: FluxFormData, values: FluxFormValues, labels: FluxFormLabels, step: number | null): Record<string, string> {
  const out: Record<string, string> = {};
  for (const f of form.fields) {
    if (step != null && Math.max(1, f.step ?? 1) !== step) continue;
    const e = validateField(f, values[f.id], labels);
    if (e) out[f.id] = e;
  }
  return out;
}

/** The flxFormSubmit answers: empty fields are left out, several values are joined the way Flux Forms stores them. */
export function toAnswers(form: FluxFormData, values: FluxFormValues): FluxFormAnswer[] {
  const out: FluxFormAnswer[] = [];
  for (const f of form.fields) {
    if (!isAnswer(f)) continue;
    const v = values[f.id];
    if (empty(v)) continue;
    out.push({ fieldId: f.id, value: Array.isArray(v) ? v.join('@efb!') : v!.trim() });
  }
  return out;
}

/** flxFormSubmit errors split into field errors ("fields.<id>") and form errors. */
export function splitErrors(errors: FluxFormError[] | null | undefined): { fields: Record<string, string>; form: string[] } {
  const fields: Record<string, string> = {};
  const form: string[] = [];
  for (const e of errors ?? []) {
    const m = /^fields\.(.+)$/.exec(e.field ?? '');
    if (m && !fields[m[1]!]) fields[m[1]!] = e.message;
    else if (!m) form.push(e.message);
  }
  return { fields, form };
}

/** Sum of the prices of the chosen payment options. */
export function total(form: FluxFormData, values: FluxFormValues): number {
  let sum = 0;
  for (const f of form.fields) {
    const v = values[f.id];
    const picked = Array.isArray(v) ? v : v ? [v] : [];
    for (const o of f.options) if (o.price != null && picked.includes(o.value)) sum += o.price;
    if (f.type.toLowerCase() === 'prcfld' && typeof v === 'string' && Number.isFinite(Number(v))) sum += Number(v);
  }
  return sum;
}

/** Accept list of a file field. */
export function accept(field: FluxFormField): string | undefined {
  const t = field.type.toLowerCase();
  if (t === 'audio_recorder') return 'audio/*';
  if (t === 'video_recorder' || t === 'screen_recorder') return 'video/*';
  switch (setting(field, 'file')) {
    case 'image':
      return 'image/*';
    case 'document':
      return '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.odt,.ods';
    case 'media':
      return 'audio/*,video/*';
    case 'zip':
      return '.zip,.rar';
    case 'customize': {
      const list = setting(field, 'file_ctype')
        .split(',')
        .map((x) => x.trim().replace(/^\./, ''))
        .filter(Boolean)
        .map((x) => '.' + x);
      return list.length ? list.join(',') : undefined;
    }
    default:
      return undefined;
  }
}
