'use client';
// A Flux Forms form in React: the flxForm data in, the flxFormSubmit input out. Steps, checks, widths, totals and
// server errors are handled here; the caller only sends the request (any GraphQL client, or fetch).
import { useMemo, useRef, useState, type FormEvent, type ReactNode } from 'react';
import { FluxField, type FluxFieldComponents } from './fields';
import { FLUX_FORM_LABELS, fill } from './labels';
import { formSteps, initialValues, isAnswer, splitErrors, toAnswers, total, validateStep } from './logic';
import type { FluxFormData, FluxFormField, FluxFormLabels, FluxFormSubmitInput, FluxFormSubmitResult, FluxFormValue, FluxFormValues } from './types';

export interface FluxFormProps {
  form: FluxFormData;
  /** Sends the input to flxFormSubmit and returns its payload. A thrown error shows the `failed` message. */
  onSubmit: (input: FluxFormSubmitInput) => Promise<FluxFormSubmitResult>;
  /** File fields: sends the file and resolves its URL. Without it, file fields say they can be filled on the website. */
  onUpload?: (file: File, field: FluxFormField) => Promise<string>;
  /** reCAPTCHA (when Flux reCAPTCHA protects the form): a token for this submission. */
  captchaToken?: () => Promise<string | null>;
  /** 'en' (default) or 'tr', or your own texts on top of them. */
  lang?: 'en' | 'tr';
  labels?: Partial<FluxFormLabels>;
  /** Replace the component of any element kind. */
  components?: FluxFieldComponents;
  /** Called after a successful submission. */
  onDone?: (result: FluxFormSubmitResult) => void;
  /** What to show after a successful submission (default: the form's success title and message). */
  renderDone?: (result: FluxFormSubmitResult) => ReactNode;
  className?: string;
}

/** Flex basis of a field: its percent minus its share of the gap, so 50 + 50 or 33 × 3 fit on one line. */
function cellWidth(w: number | undefined): string {
  const n = w && w > 0 && w < 100 ? w : 100;
  return n === 100 ? '100%' : `calc(${n}% - var(--flxff-gap) * ${(100 - n) / 100})`;
}

/** Draws one Flux Forms form and sends it. */
export function FluxForm({ form, onSubmit, onUpload, captchaToken, lang = 'en', labels: own, components, onDone, renderDone, className }: FluxFormProps) {
  const labels = useMemo(() => ({ ...FLUX_FORM_LABELS[lang], ...own }), [lang, own]);
  const steps = useMemo(() => formSteps(form), [form]);
  const [values, setValues] = useState<FluxFormValues>(() => initialValues(form));
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [formErrors, setFormErrors] = useState<string[]>([]);
  const [at, setAt] = useState(0);
  const [busy, setBusy] = useState(false);
  const [done, setDone] = useState<FluxFormSubmitResult | null>(null);
  const [touched, setTouched] = useState(false);
  const trap = useRef<HTMLInputElement>(null);
  const root = useRef<HTMLFormElement>(null);
  const prefix = 'flxff-' + String(form.id).replace(/[^\w-]/g, '');
  const step = steps[at] ?? 1;
  const last = at >= steps.length - 1;
  const sum = useMemo(() => total(form, values), [form, values]);
  const stepInfo = (form.stepList ?? []).find((s) => s.number === step);

  const focusFirst = (bad: Record<string, string>) => {
    const id = form.fields.find((f) => bad[f.id])?.id;
    if (!id) return;
    const el = root.current?.querySelector<HTMLElement>(`[name="${CSS.escape(id)}"], #${CSS.escape(prefix + '-' + id)}`);
    el?.focus();
  };

  const set = (id: string, v: FluxFormValue) => {
    setValues((prev) => ({ ...prev, [id]: v }));
    if (errors[id]) setErrors((prev) => ({ ...prev, [id]: '' }));
  };

  const submit = async (e: FormEvent) => {
    e.preventDefault();
    if (busy) return;
    setTouched(true);
    const bad = validateStep(form, values, labels, steps.length > 1 ? step : null);
    if (Object.keys(bad).length) {
      setErrors(bad);
      focusFirst(bad);
      return;
    }
    if (!last) {
      setErrors({});
      setAt(at + 1);
      root.current?.scrollIntoView?.({ block: 'start', behavior: 'smooth' });
      return;
    }
    setBusy(true);
    setFormErrors([]);
    try {
      const token = captchaToken ? await captchaToken() : null;
      const r = await onSubmit({ formId: form.id, fields: toAnswers(form, values), honeypot: trap.current?.value ?? '', captchaToken: token });
      const split = splitErrors(r.errors);
      if (r.submission && !(r.errors ?? []).length) {
        setDone(r);
        onDone?.(r);
        return;
      }
      setErrors(split.fields);
      setFormErrors(split.form.length || Object.keys(split.fields).length ? split.form : [labels.failed]);
      // A server error on an earlier step: go back to it.
      const first = form.fields.find((f) => split.fields[f.id]);
      if (first) {
        const i = steps.indexOf(Math.max(1, first.step ?? 1));
        if (i >= 0 && i !== at) setAt(i);
        focusFirst(split.fields);
      }
    } catch {
      setFormErrors([labels.failed]);
    } finally {
      setBusy(false);
    }
  };

  if (done) {
    if (renderDone) return <>{renderDone(done)}</>;
    return (
      <div className={['flxff', 'flxff-done', className ?? ''].filter(Boolean).join(' ')} role="status">
        <h3>{form.successTitle || labels.thanks}</h3>
        {form.successMessage ? <p>{form.successMessage}</p> : null}
        {done.submission?.trackCode ? (
          <p className="flxff-help">
            {labels.trackCode}: <code>{done.submission.trackCode}</code>
          </p>
        ) : null}
      </div>
    );
  }

  const shown = form.fields.filter((f) => Math.max(1, f.step ?? 1) === step || steps.length === 1);

  return (
    <form ref={root} className={['flxff', className ?? ''].filter(Boolean).join(' ')} noValidate onSubmit={submit} data-touched={touched || undefined} aria-busy={busy || undefined}>
      {steps.length > 1 ? (
        <div className="flxff-steps">
          <div className="flxff-steps__bar" aria-hidden="true">
            <span style={{ width: `${((at + 1) / steps.length) * 100}%` }} />
          </div>
          <p className="flxff-steps__count">{fill(labels.stepOf, { n: at + 1, max: steps.length })}</p>
          {stepInfo?.title ? <h3 className="flxff-steps__title">{stepInfo.title}</h3> : null}
          {stepInfo?.help ? <p className="flxff-help">{stepInfo.help}</p> : null}
        </div>
      ) : null}
      <div className="flxff-grid">
        {shown.map((f) => (
          <div key={f.id} className="flxff-cell" style={{ ['--flxff-w' as string]: cellWidth(f.width) }}>
            <FluxField
              field={f}
              value={values[f.id]}
              onChange={(v) => set(f.id, v)}
              error={isAnswer(f) ? errors[f.id] : ''}
              labels={labels}
              idPrefix={prefix}
              disabled={busy}
              onUpload={onUpload}
              total={sum}
              currency={form.currency}
              components={components}
            />
          </div>
        ))}
      </div>
      {/* Bots fill every field; people never see this one. */}
      <input ref={trap} className="flxff-trap" type="text" name="flxff_hp" tabIndex={-1} autoComplete="off" aria-hidden="true" defaultValue="" />
      {formErrors.length ? (
        <div className="flxff-alert" role="alert">
          {formErrors.map((m) => (
            <p key={m}>{m}</p>
          ))}
        </div>
      ) : null}
      <div className="flxff-actions">
        {at > 0 ? (
          <button type="button" className="flxff-btn flxff-btn--ghost" disabled={busy} onClick={() => (setErrors({}), setAt(at - 1))}>
            {labels.back}
          </button>
        ) : null}
        <button type="submit" className="flxff-btn" disabled={busy}>
          {busy ? labels.sending : last ? form.buttonLabel || labels.submit : labels.next}
        </button>
      </div>
    </form>
  );
}
