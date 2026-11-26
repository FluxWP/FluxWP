// Forms › Automations › one flow: a vertical editor (trigger with conditions, then steps) over
// flxFormsAutomationEditor / flxFormsAutomationSave. The flow is an open JSON document (same shape the engine
// reads); the server whitelists every field when it saves.
import { Fragment, cloneElement, isValidElement, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { FlxFormsAutomationEditor, FlxFormsAutomationSave, FlxFormsAutomations } from '@fluxwp/graphql/operations';
import { Loading, confirmDialog, usePageHead } from '@fluxwp/ui';
import type { Translate } from './i18n';
import { errorText, payloadError, useForms } from './common';
import { Svg } from './classic';

interface FormField {
  id: string;
  label: string;
  type: string;
  options: { id: string; label: string }[];
}
interface FormInfo {
  id: number;
  title: string;
  type: string;
  currency: string;
  fields: FormField[];
  guess: Partial<Record<PersonKey, string>>;
}
interface Template {
  id: number;
  name: string;
  fields: { key: string; label: string; type: string; default: string }[];
}
interface EditorData {
  flow: Flow | null;
  forms: FormInfo[];
  templates: Template[];
  events: { id: number; name: string; date: string }[];
  providers: Record<string, { id: number; name: string }[]>;
  channels: Record<string, string>;
  certAvailable: boolean;
  saplingPrice: number;
  adminEmail: string;
}
interface VarMap {
  mode: string;
  field: string;
  value: string;
  unit_price: number;
}
export interface Action {
  id: string;
  type: ActionType;
  [k: string]: unknown;
}
interface Flow {
  id: number;
  title: string;
  enabled: boolean;
  trigger: { type: string; form_id: number };
  match: string;
  conditions: { field: string; op: string; value: string }[];
  actions: Action[];
}
type PersonKey = 'name_field' | 'surname_field' | 'phone_field' | 'email_field';

export const ACTIONS = ['create_certificate', 'send_certificate', 'show_on_thankyou', 'send_email', 'webhook'] as const;
export type ActionType = (typeof ACTIONS)[number];
const CERT: ActionType[] = ['create_certificate', 'send_certificate', 'show_on_thankyou'];
const TAGS = ['{ad_soyad}', '{fidan_sayisi}', '{sertifika_link}', '{sertifika_no}', '{amount}', '{track}', '{form_title}', '{all_fields}', '{field:ID}'];
const OPS = ['eq', 'neq', 'contains', 'gt', 'lt', 'filled', 'empty'];

export function actionLabel(type: string, t: Translate): string {
  const m: Record<string, string> = {
    create_certificate: t('Create certificate'),
    send_certificate: t('Send certificate'),
    show_on_thankyou: t('Show on thank-you screen'),
    send_email: t('Send e-mail'),
    webhook: t('Webhook'),
  };
  return m[type] ?? type;
}

function actionDesc(type: ActionType, t: Translate): string {
  const m: Record<ActionType, string> = {
    create_certificate: t('Creates a certificate from a Flux Certificates template.'),
    send_certificate: t('Sends the certificate link by e-mail, SMS or WhatsApp.'),
    show_on_thankyou: t('Shows a certificate card right after the submission.'),
    send_email: t('Sends a notification e-mail.'),
    webhook: t('POSTs the submission as JSON to a URL.'),
  };
  return m[type];
}

const s = (v: unknown): string => (typeof v === 'string' ? v : typeof v === 'number' ? String(v) : '');
const n = (v: unknown): number => (typeof v === 'number' ? v : Number(v) || 0);
const b = (v: unknown): boolean => v === true || v === 1 || v === '1';
const uid = () => 'a' + Math.random().toString(36).slice(2, 10);

export function FlowEditor({ id }: { id: number }) {
  const { t, go } = useForms();
  const q = useFlxQuery(FlxFormsAutomationEditor, { id: Math.max(0, id) });
  const data = q.data?.flxFormsAutomationEditor as EditorData | undefined;
  if (q.isError) return <p className="flxfrm-bad">{errorText(q.error, t('Something went wrong.'))}</p>;
  if (!data) return <Loading />;
  if (!data.flow) {
    return (
      <div className="flxfa flx-raw">
        <p className="flxfa-notice flxfa-notice--warn">{t('Automation not found.')}</p>
        <button type="button" className="flxfa-btn" onClick={() => go('automations')}>
          {t('All automations')}
        </button>
      </div>
    );
  }
  return <Editor key={data.flow.id} data={data} initial={data.flow} />;
}

function Editor({ data, initial }: { data: EditorData; initial: Flow }) {
  const { t, go } = useForms();
  const qc = useQueryClient();
  const { scope } = useGraphQL();
  const [flow, setFlow] = useState<Flow>(() => structuredClone(initial));
  const [dirty, setDirty] = useState(false);
  const [open, setOpen] = useState<Record<string, boolean>>({});
  const [menuAt, setMenuAt] = useState<number | null>(null);
  const [state, setState] = useState<'' | 'saving' | 'saved' | 'error'>('');
  const [stateText, setStateText] = useState('');
  const [dragFrom, setDragFrom] = useState(-1);
  const [dragOver, setDragOver] = useState(-1);
  const save = useFlxMutation(FlxFormsAutomationSave);
  usePageHead(t('Automations'));

  const form = useMemo(() => data.forms.find((f) => f.id === flow.trigger.form_id) ?? null, [data.forms, flow.trigger.form_id]);
  const currency = form?.currency || 'TRY';
  const money = (v: number) => {
    try {
      return new Intl.NumberFormat(document.documentElement.lang || undefined, { style: 'currency', currency, maximumFractionDigits: 2 }).format(v);
    } catch {
      return String(v);
    }
  };
  const edit = (fn: (f: Flow) => void) => {
    setFlow((f) => {
      const next = structuredClone(f);
      fn(next);
      return next;
    });
    setDirty(true);
    setState('');
  };
  const editAction = (i: number, fn: (a: Action) => void) =>
    edit((f) => {
      const a = f.actions[i];
      if (a) fn(a);
    });

  const submit = async () => {
    setState('saving');
    try {
      const r = (await save.mutateAsync({ input: { flow } })).flxFormsAutomationSave;
      const err = payloadError(r?.errors);
      if (err) throw new Error(err);
      const saved = r?.flow as Flow | null | undefined;
      if (saved) {
        setFlow(structuredClone(saved));
        qc.setQueryData(flxQueryKey(scope, FlxFormsAutomationEditor, { id: saved.id }), { flxFormsAutomationEditor: { ...data, flow: saved } });
        if (!flow.id) go('automations', { flow: saved.id });
      }
      setDirty(false);
      setState('saved');
      setStateText(t('Saved.'));
      void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxFormsAutomations) });
    } catch (e) {
      setState('error');
      setStateText(errorText(e, t('Could not save. Please try again.')));
    }
  };
  const submitRef = useRef(submit);
  submitRef.current = submit;

  useEffect(() => {
    const key = (e: KeyboardEvent) => {
      if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        void submitRef.current();
      }
    };
    document.addEventListener('keydown', key);
    return () => document.removeEventListener('keydown', key);
  }, []);
  useEffect(() => {
    if (!dirty) return;
    const warn = (e: BeforeUnloadEvent) => {
      e.preventDefault();
      e.returnValue = '';
    };
    window.addEventListener('beforeunload', warn);
    return () => window.removeEventListener('beforeunload', warn);
  }, [dirty]);

  const fieldLabel = (fid: string) => {
    if (!fid) return '—';
    for (const fm of form ? [form] : data.forms) {
      const f = fm.fields.find((x) => x.id === fid);
      if (f) return f.label;
    }
    return fid;
  };
  const tplById = (tid: unknown) => data.templates.find((x) => x.id === n(tid)) ?? null;

  const defaultsFor = (type: ActionType): Record<string, unknown> => {
    const g = form?.guess ?? {};
    switch (type) {
      case 'create_certificate': {
        const tpl = data.templates.find((x) => /fidan bağış/i.test(x.name)) ?? data.templates[0];
        const vars: Record<string, VarMap> = {};
        tpl?.fields.forEach((fl) => {
          if (fl.key === 'fidan_sayisi') vars[fl.key] = { mode: flow.trigger.type === 'payment_completed' ? 'amount_div' : 'default', field: '', value: '', unit_price: data.saplingPrice || 150 };
        });
        return { template_id: tpl ? tpl.id : 0, name_field: g.name_field ?? '', surname_field: g.surname_field ?? '', phone_field: g.phone_field ?? '', email_field: g.email_field ?? '', event_id: 0, vars };
      }
      case 'send_certificate':
        return { channels: ['whatsapp', 'email'], providers: { email: 0, sms: 0, whatsapp: 0 } };
      case 'show_on_thankyou':
        return {
          title: t('Your certificate is ready'),
          message: '',
          preview: true,
          btn_view: true,
          btn_pdf: true,
          btn_share: true,
          view_label: t('View certificate'),
          pdf_label: t('Download PDF'),
          share_label: t('Share'),
          redirect: 0,
        };
      case 'send_email':
        return { to: 'admin', to_field: g.email_field ?? '', to_custom: '', subject: '', body: '{all_fields}\n\n{sertifika_link}' };
      case 'webhook':
        return { url: '', secret: '' };
    }
  };

  const summaryOf = (a: Action): string => {
    switch (a.type) {
      case 'create_certificate': {
        const tpl = tplById(a.template_id);
        const bits = [tpl ? tpl.name : t('Choose a template')];
        const vars = (a.vars ?? {}) as Record<string, VarMap>;
        for (const [k, m] of Object.entries(vars)) {
          if (m.mode === 'amount_div') bits.push(k + ' = ' + t('Payment amount') + ' ÷ ' + money(n(m.unit_price) || 1));
          else if (m.mode === 'fixed') bits.push(k + ' = ' + m.value);
          else if (m.mode === 'field' || m.mode === 'option_qty') bits.push(k + ' ← ' + fieldLabel(m.field));
        }
        return bits.join(' · ');
      }
      case 'send_certificate':
        return ((a.channels as string[] | undefined) ?? []).map((c) => data.channels[c] ?? c).join(', ') || '—';
      case 'show_on_thankyou':
        return (s(a.title) || t('Your certificate is ready')) + (n(a.redirect) ? ' · ↗ ' + n(a.redirect) + 's' : '');
      case 'send_email':
        return a.to === 'admin' ? t('Site administrator') : a.to === 'field' ? fieldLabel(s(a.to_field)) : s(a.to_custom) || '—';
      case 'webhook':
        try {
          return a.url ? new URL(s(a.url)).host : '—';
        } catch {
          return s(a.url) || '—';
        }
    }
  };

  const move = (from: number, to: number) => {
    if (to < 0 || to >= flow.actions.length || from === to) return;
    edit((f) => {
      const [a] = f.actions.splice(from, 1);
      if (a) f.actions.splice(to, 0, a);
    });
  };
  const insert = (at: number, type: ActionType) => {
    const a: Action = { id: uid(), type, ...defaultsFor(type) };
    edit((f) => {
      f.actions.splice(at, 0, a);
    });
    setOpen((o) => ({ ...o, [a.id]: true }));
    setMenuAt(null);
  };
  const changeForm = (fid: number) =>
    edit((f) => {
      f.trigger.form_id = fid;
      const fm = data.forms.find((x) => x.id === fid);
      const g = fm?.guess ?? {};
      for (const a of f.actions) {
        if (a.type !== 'create_certificate') continue;
        for (const k of ['name_field', 'surname_field', 'phone_field', 'email_field'] as PersonKey[]) {
          const cur = s(a[k]);
          if (!cur || (fm && !fm.fields.some((x) => x.id === cur))) a[k] = g[k] ?? '';
        }
      }
    });

  const fieldPicker = (value: string, onChange: (v: string) => void, filter?: (f: FormField) => boolean, label?: string) => {
    const opts = (fields: FormField[]) =>
      fields
        .filter((f) => !filter || filter(f))
        .map((f) => (
          <option key={f.id} value={f.id}>
            {f.label + ' (' + f.type + ')'}
          </option>
        ));
    const known = (form ? form.fields : data.forms.flatMap((x) => x.fields)).some((f) => f.id === value);
    return (
      <select className="flxfa-input" value={value} aria-label={label} onChange={(e) => onChange(e.target.value)}>
        <option value="">{t('— none —')}</option>
        {form
          ? opts(form.fields)
          : data.forms.map((fm) => {
              const o = opts(fm.fields);
              return o.length ? (
                <optgroup key={fm.id} label={fm.title}>
                  {o}
                </optgroup>
              ) : null;
            })}
        {value && !known ? <option value={value}>{value}</option> : null}
      </select>
    );
  };

  const tagChips = (onTag: (tag: string) => void) => (
    <div className="flxfa-tags" aria-label={t('Tags')}>
      {TAGS.map((tag) => (
        <button key={tag} type="button" className="flxfa-tag" onClick={() => onTag(tag)}>
          {tag}
        </button>
      ))}
    </div>
  );

  /* ---------- step settings ---------- */
  const settings = (a: Action, i: number): ReactNode => {
    const set = (k: string, v: unknown) => editAction(i, (x) => void (x[k] = v));
    const text = (k: string, label: string, opts?: { type?: string; placeholder?: string; note?: string }) => (
      <Row label={label} hint={opts?.note}>
        <input
          type={opts?.type ?? 'text'}
          className={'flxfa-input' + (opts?.type === 'number' ? ' flxfa-input--num' : '')}
          value={s(a[k])}
          placeholder={opts?.placeholder}
          onChange={(e) => set(k, opts?.type === 'number' ? (e.target.value === '' ? 0 : Number(e.target.value)) : e.target.value)}
        />
      </Row>
    );
    const area = (k: string, label: string, rows: number) => <TagArea label={label} rows={rows} value={s(a[k])} onChange={(v) => set(k, v)} chips={tagChips} />;
    const certOff = !data.certAvailable && CERT.includes(a.type) ? <p className="flxfa-inline-warn">{t('Flux Certificates is not active: certificate steps are disabled.')}</p> : null;

    if (a.type === 'create_certificate') {
      const tpl = tplById(a.template_id);
      const vars = (a.vars ?? {}) as Record<string, VarMap>;
      const g = form?.guess ?? {};
      const person: [PersonKey, string, (f: FormField) => boolean][] = [
        ['name_field', t('Name field'), (f) => ['text', 'textarea', 'select', 'radio'].includes(f.type)],
        ['surname_field', t('Surname field (optional)'), (f) => f.type === 'text'],
        ['phone_field', t('Phone field'), (f) => ['mobile', 'tel', 'text', 'number'].includes(f.type)],
        ['email_field', t('E-mail field'), (f) => ['email', 'text'].includes(f.type)],
      ];
      const setVar = (key: string, patch: Partial<VarMap>) =>
        editAction(i, (x) => {
          const v = (x.vars ??= {}) as Record<string, VarMap>;
          v[key] = { ...(v[key] ?? { mode: 'default', field: '', value: '', unit_price: data.saplingPrice || 150 }), ...patch };
        });
      return (
        <>
          {certOff}
          <Row label={t('Certificate template')}>
            <select className="flxfa-input" value={n(a.template_id)} onChange={(e) => set('template_id', Number(e.target.value))}>
              <option value={0}>{t('Choose a template')}</option>
              {data.templates.map((x) => (
                <option key={x.id} value={x.id}>
                  {x.name}
                </option>
              ))}
            </select>
          </Row>
          <fieldset className="flxfa-fieldset">
            <legend>{t('Certificate owner')}</legend>
            <div className="flxfa-grid">
              {person.map(([k, label, filter]) => (
                <Row key={k} label={label} hint={s(a[k]) && g[k] === s(a[k]) ? '✓ ' + t('auto-detected') : undefined}>
                  {fieldPicker(s(a[k]), (v) => set(k, v), filter)}
                </Row>
              ))}
            </div>
          </fieldset>
          {tpl ? (
            <fieldset className="flxfa-fieldset">
              <legend>{t('Template variables')}</legend>
              {tpl.fields.map((fl) => {
                if (fl.key === 'ad_soyad') {
                  return (
                    <div key={fl.key} className="flxfa-var">
                      <div className="flxfa-var__name">
                        <strong>{fl.label}</strong>
                        <code>{fl.key}</code>
                      </div>
                      <p className="flxfa-hint">{t('Filled from the owner name above.')}</p>
                    </div>
                  );
                }
                const m = vars[fl.key] ?? { mode: 'default', field: '', value: '', unit_price: data.saplingPrice || 150 };
                const modes: [string, string][] = [
                  ['default', t('Template default') + (fl.default ? ' (' + fl.default + ')' : '')],
                  ['field', t('Form field')],
                  ['fixed', t('Fixed value')],
                ];
                if (fl.type === 'number' || fl.type === 'text') modes.push(['amount_div', t('Payment amount ÷ unit price')], ['option_qty', t('Number in the selected option (e.g. “5 saplings” → 5)')]);
                const unit = n(m.unit_price) || 1;
                return (
                  <div key={fl.key} className="flxfa-var">
                    <div className="flxfa-var__name">
                      <strong>{fl.label}</strong>
                      <code>{fl.key}</code>
                    </div>
                    <div className="flxfa-var__ctrl">
                      <select className="flxfa-input" aria-label={fl.label} value={m.mode} onChange={(e) => setVar(fl.key, { mode: e.target.value })}>
                        {modes.map(([v, l]) => (
                          <option key={v} value={v}>
                            {l}
                          </option>
                        ))}
                      </select>
                      {m.mode === 'field' || m.mode === 'option_qty'
                        ? fieldPicker(m.field, (v) => setVar(fl.key, { field: v }), m.mode === 'option_qty' ? (f) => /^(pay|radio|select|chl)/i.test(f.type) || f.options.length > 0 : undefined, t('Field'))
                        : null}
                      {m.mode === 'fixed' ? (
                        <input
                          className="flxfa-input"
                          type={fl.type === 'number' ? 'number' : fl.type === 'date' ? 'date' : 'text'}
                          aria-label={t('Value')}
                          value={m.value}
                          onChange={(e) => setVar(fl.key, { value: e.target.value })}
                        />
                      ) : null}
                      {m.mode === 'amount_div' ? (
                        <>
                          <div className="flxfa-inline">
                            <label className="flxfa-label flxfa-label--inline" htmlFor={'flxfa-u-' + a.id + fl.key}>
                              {t('Unit price')}
                            </label>
                            <input
                              id={'flxfa-u-' + a.id + fl.key}
                              type="number"
                              min="0.01"
                              step="0.01"
                              className="flxfa-input flxfa-input--num"
                              value={m.unit_price}
                              onChange={(e) => setVar(fl.key, { unit_price: Number(e.target.value) || 0 })}
                            />
                            <span className="flxfa-hint flxfa-example">{money(unit * 5) + ' ÷ ' + money(unit) + ' = 5'}</span>
                          </div>
                          <p className="flxfa-hint">
                            {t('Verified paid amount divided by the unit price, rounded down, at least 1.')}
                            {flow.trigger.type !== 'payment_completed' ? ' — ' + t('Runs only after the payment gateway has confirmed the payment.') : ''}
                          </p>
                        </>
                      ) : null}
                    </div>
                  </div>
                );
              })}
            </fieldset>
          ) : null}
          <Row label={t('Event (optional)')}>
            <select className="flxfa-input" value={n(a.event_id)} onChange={(e) => set('event_id', Number(e.target.value))}>
              <option value={0}>{t('No event')}</option>
              {data.events.map((ev) => (
                <option key={ev.id} value={ev.id}>
                  {ev.name + (ev.date ? ' · ' + ev.date : '')}
                </option>
              ))}
            </select>
          </Row>
        </>
      );
    }

    if (a.type === 'send_certificate') {
      const channels = (a.channels as string[] | undefined) ?? [];
      const providers = (a.providers ?? {}) as Record<string, number>;
      return (
        <>
          {certOff}
          <fieldset className="flxfa-fieldset">
            <legend>{t('Channels')}</legend>
            {Object.entries(data.channels).map(([ch, name]) => {
              const on = channels.includes(ch);
              const list = data.providers[ch] ?? [];
              return (
                <div key={ch} className="flxfa-channel">
                  <CheckLabel checked={on} onChange={(v) => set('channels', v ? [...channels.filter((x) => x !== ch), ch] : channels.filter((x) => x !== ch))}>
                    {name}
                  </CheckLabel>
                  <select className="flxfa-input" aria-label={t('Provider') + ' · ' + name} disabled={!on} value={n(providers[ch])} onChange={(e) => set('providers', { ...providers, [ch]: Number(e.target.value) })}>
                    <option value={0}>{t('Default provider')}</option>
                    {list.map((p) => (
                      <option key={p.id} value={p.id}>
                        {p.name}
                      </option>
                    ))}
                  </select>
                  {!list.length && ch !== 'email' ? <p className="flxfa-hint flxfa-hint--warn">{t('No active provider for this channel yet (Flux Certificates › Providers).')}</p> : null}
                </div>
              );
            })}
          </fieldset>
        </>
      );
    }

    if (a.type === 'show_on_thankyou') {
      const btn = (k: string, labelKey: string, label: string) => (
        <div className="flxfa-inline">
          <CheckLabel checked={b(a[k])} onChange={(v) => set(k, v)}>
            {label}
          </CheckLabel>
          <input type="text" className="flxfa-input flxfa-input--sm" aria-label={t('Label')} value={s(a[labelKey])} onChange={(e) => set(labelKey, e.target.value)} />
        </div>
      );
      const sample = (v: string) =>
        v
          .replace(/\{ad_soyad\}/g, 'Ayşe Yılmaz')
          .replace(/\{fidan_sayisi\}/g, '5')
          .replace(/\{sertifika_link\}/g, window.location.origin + '/sertifika/abc123')
          .replace(/\{amount\}/g, money(500))
          .replace(/\{sertifika_no\}/g, 'FLX-2026-00042');
      return (
        <div className="flxfa-show">
          <div className="flxfa-show__form">
            {text('title', t('Card title'))}
            {area('message', t('Message'), 3)}
            <div className="flxfa-inline">
              <CheckLabel checked={a.preview === undefined ? true : b(a.preview)} onChange={(v) => set('preview', v)}>
                {t('Show the certificate itself on the screen')}
              </CheckLabel>
            </div>
            {btn('btn_pdf', 'pdf_label', t('“Download PDF” button'))}
            {btn('btn_share', 'share_label', t('“Share” button'))}
            {btn('btn_view', 'view_label', t('“View certificate” link'))}
            {text('redirect', t('Open the certificate page automatically after (seconds, 0 = off)'), { type: 'number' })}
          </div>
          <div className="flxfa-preview" aria-hidden="true">
            <div className={'flxf-cert-card' + (a.preview === undefined || b(a.preview) ? ' flxf-cert-card--preview' : '')}>
              <div className="flxf-cert-card__head">
                <div className="flxf-cert-card__icon">
                  <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
                    <circle cx="12" cy="9" r="6" />
                    <path d="M8.5 13.9 7 22l5-3 5 3-1.5-8.1" />
                    <path d="m9.5 9 1.7 1.7L14.8 7.3" />
                  </svg>
                </div>
                <div className="flxf-cert-card__body">
                  <p className="flxf-cert-card__title">{sample(s(a.title) || t('Your certificate is ready'))}</p>
                  {s(a.message) ? <p className="flxf-cert-card__msg">{sample(s(a.message))}</p> : null}
                </div>
              </div>
              {a.preview === undefined || b(a.preview) ? <figure className="flxf-cert-card__figure flxfa-preview__sheet" style={{ aspectRatio: '1060/1500' }} /> : null}
              {b(a.btn_pdf) || b(a.btn_share) ? (
                <div className="flxf-cert-card__actions">
                  {b(a.btn_pdf) ? <span className="flxf-cert-btn flxf-cert-btn--primary">{s(a.pdf_label) || t('Download PDF')}</span> : null}
                  {b(a.btn_share) ? <span className="flxf-cert-btn">{s(a.share_label) || t('Share')}</span> : null}
                </div>
              ) : null}
              <p className="flxf-cert-card__no">
                FLX-2026-00042
                {b(a.btn_view) ? ' · ' : ''}
                {b(a.btn_view) ? <span className="flxf-cert-card__link">{s(a.view_label) || t('View certificate')}</span> : null}
              </p>
            </div>
          </div>
        </div>
      );
    }

    if (a.type === 'send_email') {
      return (
        <>
          <Row label={t('To')} htmlFor={'flxfa-to-' + a.id}>
            <div className="flxfa-stack">
              <select id={'flxfa-to-' + a.id} className="flxfa-input" value={s(a.to) || 'admin'} onChange={(e) => set('to', e.target.value)}>
                <option value="admin">{t('Site administrator') + (data.adminEmail ? ' (' + data.adminEmail + ')' : '')}</option>
                <option value="field">{t('E-mail from a form field')}</option>
                <option value="custom">{t('Custom addresses')}</option>
              </select>
              {a.to === 'field'
                ? fieldPicker(
                    s(a.to_field),
                    (v) => set('to_field', v),
                    (f) => f.type === 'email',
                    t('E-mail from a form field'),
                  )
                : null}
              {a.to === 'custom' ? (
                <input type="text" className="flxfa-input" aria-label={t('Custom addresses')} placeholder="ornek@site.com, diger@site.com" value={s(a.to_custom)} onChange={(e) => set('to_custom', e.target.value)} />
              ) : null}
            </div>
          </Row>
          {text('subject', t('Subject'), { placeholder: '{form_title} · {track}' })}
          {area('body', t('Body'), 6)}
        </>
      );
    }

    return (
      <>
        {text('url', t('URL (https only)'), { type: 'url', placeholder: 'https://example.com/hook', note: t('Only https:// URLs to public hosts are allowed.') })}
        <Row label={t('Signing secret (optional)')} hint={t('Adds an X-FLXF-Signature: sha256=HMAC header.')}>
          <input type="text" className="flxfa-input" autoComplete="off" value={s(a.secret)} onChange={(e) => set('secret', e.target.value)} />
        </Row>
      </>
    );
  };

  const connector = (at: number) => (
    <div className="flxfa-connector">
      <span className="flxfa-line" aria-hidden="true"></span>
      <button
        type="button"
        className="flxfa-add"
        title={t('Insert a step here')}
        aria-label={t('Insert a step here')}
        aria-haspopup="true"
        aria-expanded={menuAt === at}
        onClick={() => setMenuAt((m) => (m === at ? null : at))}
      >
        <Svg name="plus" />
      </button>
      {menuAt === at ? (
        <div className="flxfa-menu" role="menu">
          {ACTIONS.map((type) => {
            const off = !data.certAvailable && CERT.includes(type);
            return (
              <button key={type} type="button" role="menuitem" className="flxfa-menu__item" disabled={off} onClick={() => insert(at, type)}>
                <span className={'flxfa-node__icon flxfa-node__icon--' + type}>
                  <Svg name={type} />
                </span>
                <span className="flxfa-menu__text">
                  <strong>{actionLabel(type, t)}</strong>
                  <span>{off ? t('Flux Certificates is not active: certificate steps are disabled.') : actionDesc(type, t)}</span>
                </span>
              </button>
            );
          })}
        </div>
      ) : null}
    </div>
  );

  const triggerSummary = (flow.trigger.type === 'payment_completed' ? t('A payment is completed') : t('A form is submitted')) + ' · ' + (form ? form.title : t('Any form'));

  const removeStep = async (at: number) => {
    if (await confirmDialog(t('Delete this step?'), true)) edit((f) => void f.actions.splice(at, 1));
  };
  const stateLabel = state === 'saving' ? t('Saving…') : state ? stateText : dirty ? t('You have unsaved changes.') : '';

  return (
    <div className="flxfa flx-raw">
      <div className="flxfa-ed-head">
        <a
          className="flxfa-back"
          href="#/automations"
          onClick={(e) => {
            e.preventDefault();
            go('automations');
          }}
        >
          <Svg name="back" />
          {t('All automations')}
        </a>
        <div className="flxfa-ed-head__main">
          <input
            type="text"
            className="flxfa-input flxfa-title-input"
            value={flow.title}
            placeholder={t('Automation name')}
            aria-label={t('Automation name')}
            onChange={(e) => edit((f) => void (f.title = e.target.value))}
          />
          <div className="flxfa-ed-head__actions">
            <label className="flxfa-switch flxfa-switch--label">
              <input type="checkbox" checked={flow.enabled} onChange={(e) => edit((f) => void (f.enabled = e.target.checked))} />
              <span></span>
              <em>{t('Enabled')}</em>
            </label>
            <span className={'flxfa-save-state' + (state === 'saved' ? ' is-saved' : state === 'error' ? ' is-error' : !state && dirty ? ' is-dirty' : '')} role="status" aria-live="polite">
              {stateLabel}
            </span>
            <button type="button" className="flxfa-btn flxfa-btn--primary" id="flxfa-save" disabled={save.isPending} onClick={() => void submit()}>
              {t('Save')}
            </button>
          </div>
        </div>
      </div>
      {!data.certAvailable ? (
        <div className="flxfa-notice flxfa-notice--warn">
          <Svg name="alert" size={18} />
          <div>{t('Flux Certificates is not active: certificate steps are disabled.')}</div>
        </div>
      ) : null}

      <div className="flxfa-canvas">
        <section className="flxfa-node flxfa-node--trigger" aria-label={t('Trigger')}>
          <header className="flxfa-node__head">
            <span className="flxfa-node__icon flxfa-node__icon--trigger">
              <Svg name="bolt" size={18} />
            </span>
            <div className="flxfa-node__titles">
              <span className="flxfa-node__kicker">{t('Trigger')}</span>
              <span className="flxfa-node__title">{triggerSummary}</span>
            </div>
          </header>
          <div className="flxfa-node__body">
            <Row label={t('When')} hint={flow.trigger.type === 'payment_completed' ? t('Runs only after the payment gateway has confirmed the payment.') : undefined}>
              <select className="flxfa-input" value={flow.trigger.type} onChange={(e) => edit((f) => void (f.trigger.type = e.target.value))}>
                <option value="form_submitted">{t('A form is submitted')}</option>
                <option value="payment_completed">{t('A payment is completed')}</option>
              </select>
            </Row>
            <Row label={t('Form')}>
              <select className="flxfa-input" value={flow.trigger.form_id} onChange={(e) => changeForm(Number(e.target.value))}>
                <option value={0}>{t('Any form')}</option>
                {data.forms.map((x) => (
                  <option key={x.id} value={x.id}>
                    {x.title + ' (#' + x.id + (x.type === 'payment' ? ' · ' + x.type : '') + ')'}
                  </option>
                ))}
              </select>
            </Row>
            <div className="flxfa-conds">
              <div className="flxfa-conds__head">
                <span className="flxfa-label">{t('Conditions')}</span>
                {flow.conditions.length > 1 ? (
                  <select className="flxfa-input flxfa-input--sm" aria-label={t('Conditions')} value={flow.match} onChange={(e) => edit((f) => void (f.match = e.target.value))}>
                    <option value="all">{t('All conditions must match')}</option>
                    <option value="any">{t('Any condition may match')}</option>
                  </select>
                ) : null}
              </div>
              {!flow.conditions.length ? <p className="flxfa-hint">{t('No conditions: runs for every submission.')}</p> : null}
              {flow.conditions.map((c, ci) => (
                <div key={ci} className="flxfa-cond">
                  {fieldPicker(c.field, (v) => edit((f) => void (f.conditions[ci] && (f.conditions[ci].field = v))), undefined, t('Field'))}
                  <select className="flxfa-input" aria-label={t('Conditions')} value={c.op} onChange={(e) => edit((f) => void (f.conditions[ci] && (f.conditions[ci].op = e.target.value)))}>
                    {OPS.map((o) => (
                      <option key={o} value={o}>
                        {opLabel(o, t)}
                      </option>
                    ))}
                  </select>
                  <input
                    type="text"
                    className="flxfa-input"
                    aria-label={t('Value')}
                    hidden={c.op === 'filled' || c.op === 'empty'}
                    value={c.value}
                    onChange={(e) => edit((f) => void (f.conditions[ci] && (f.conditions[ci].value = e.target.value)))}
                  />
                  <button type="button" className="flxfa-icon-btn flxfa-icon-btn--danger" aria-label={t('Delete step')} title={t('Delete step')} onClick={() => edit((f) => void f.conditions.splice(ci, 1))}>
                    <Svg name="trash" />
                  </button>
                </div>
              ))}
              <button type="button" className="flxfa-link-btn" onClick={() => edit((f) => void f.conditions.push({ field: '', op: 'eq', value: '' }))}>
                <Svg name="plus" size={14} />
                {t('Add condition')}
              </button>
            </div>
          </div>
        </section>

        {connector(0)}
        {!flow.actions.length ? <p className="flxfa-hint flxfa-center">{t('No steps yet. Add the first one below.')}</p> : null}
        {flow.actions.map((a, i) => {
          const isOpen = !!open[a.id];
          const needsCert = (a.type === 'send_certificate' || a.type === 'show_on_thankyou') && !flow.actions.slice(0, i).some((x) => x.type === 'create_certificate');
          const off = !data.certAvailable && CERT.includes(a.type);
          return (
            <Fragment key={a.id}>
              <section
                className={'flxfa-node flxfa-node--action' + (isOpen ? ' is-open' : '') + (off ? ' is-disabled' : '') + (dragFrom === i ? ' is-dragging' : '') + (dragOver === i && dragFrom !== i ? ' is-drop' : '')}
                aria-label={actionLabel(a.type, t)}
                draggable={dragFrom === i}
                onDragOver={(e) => {
                  if (dragFrom < 0) return;
                  e.preventDefault();
                  setDragOver(i);
                }}
                onDragLeave={() => setDragOver((o) => (o === i ? -1 : o))}
                onDrop={(e) => {
                  e.preventDefault();
                  if (dragFrom >= 0) move(dragFrom, i);
                  setDragFrom(-1);
                  setDragOver(-1);
                }}
                onDragEnd={() => {
                  setDragFrom(-1);
                  setDragOver(-1);
                }}
              >
                <header className="flxfa-node__head">
                  <span className="flxfa-grip" title={t('Move up') + ' / ' + t('Move down')} aria-hidden="true" onMouseDown={() => setDragFrom(i)} onMouseUp={() => setDragFrom(-1)}>
                    <Svg name="grip" />
                  </span>
                  <span className={'flxfa-node__icon flxfa-node__icon--' + a.type}>
                    <Svg name={a.type} size={18} />
                  </span>
                  <button type="button" className="flxfa-node__toggle" aria-expanded={isOpen} title={t('Show / hide settings')} onClick={() => setOpen((o) => ({ ...o, [a.id]: !o[a.id] }))}>
                    <span className="flxfa-node__titles">
                      <span className="flxfa-node__kicker">{t('Step') + ' ' + (i + 1)}</span>
                      <span className="flxfa-node__title">{actionLabel(a.type, t)}</span>
                      <span className="flxfa-node__summary">{summaryOf(a)}</span>
                    </span>
                    <span className={'flxfa-chev' + (isOpen ? ' is-open' : '')}>
                      <Svg name="chevron" />
                    </span>
                  </button>
                  <div className="flxfa-node__tools">
                    <button type="button" className="flxfa-icon-btn" title={t('Move up')} aria-label={t('Move up')} disabled={i === 0} onClick={() => move(i, i - 1)}>
                      <Svg name="up" />
                    </button>
                    <button type="button" className="flxfa-icon-btn" title={t('Move down')} aria-label={t('Move down')} disabled={i === flow.actions.length - 1} onClick={() => move(i, i + 1)}>
                      <Svg name="down" />
                    </button>
                    <button type="button" className="flxfa-icon-btn flxfa-icon-btn--danger" title={t('Delete step')} aria-label={t('Delete step')} onClick={() => void removeStep(i)}>
                      <Svg name="trash" />
                    </button>
                  </div>
                </header>
                {needsCert ? (
                  <p className="flxfa-node__warn">
                    <Svg name="alert" size={14} />
                    {t('Needs a "Create certificate" step before it.')}
                  </p>
                ) : null}
                {isOpen ? <div className="flxfa-node__body">{settings(a, i)}</div> : null}
              </section>
              {connector(i + 1)}
            </Fragment>
          );
        })}
        <div className="flxfa-end">
          <Svg name="flag" size={14} />
          {t('End')}
        </div>
      </div>
    </div>
  );
}

function opLabel(o: string, t: Translate): string {
  const m: Record<string, string> = {
    eq: t('equals'),
    neq: t('does not equal'),
    contains: t('contains'),
    gt: t('is greater than'),
    lt: t('is less than'),
    filled: t('is filled'),
    empty: t('is empty'),
  };
  return m[o] ?? o;
}

/** A textarea with tag chips that insert at the cursor. */
function TagArea({ label, rows, value, onChange, chips }: { label: string; rows: number; value: string; onChange: (v: string) => void; chips: (onTag: (tag: string) => void) => ReactNode }) {
  const ref = useRef<HTMLTextAreaElement>(null);
  const put = (tag: string) => {
    const el = ref.current;
    const start = el?.selectionStart ?? value.length;
    const end = el?.selectionEnd ?? start;
    onChange(value.slice(0, start) + tag + value.slice(end));
    window.setTimeout(() => {
      el?.focus();
      el?.setSelectionRange(start + tag.length, start + tag.length);
    }, 0);
  };
  return (
    <>
      <Row label={label}>
        <textarea ref={ref} className="flxfa-input" rows={rows} value={value} onChange={(e) => onChange(e.target.value)} />
      </Row>
      {chips(put)}
    </>
  );
}

let rowSeq = 0;

/** A classic settings row: label, control, hint (admin.js row()). */
function Row({ label, hint, htmlFor, children }: { label: string; hint?: string | undefined; htmlFor?: string; children: ReactNode }) {
  const [auto] = useState(() => 'flxfa-c-' + ++rowSeq);
  const id = htmlFor ?? auto;
  const child = isValidElement<{ id?: string }>(children) && typeof children.type === 'string' && /^(input|select|textarea)$/.test(children.type) && !children.props.id ? cloneElement(children, { id }) : children;
  return (
    <div className="flxfa-row">
      <label className="flxfa-label" htmlFor={id}>
        {label}
      </label>
      {child}
      {hint ? <p className="flxfa-hint">{hint}</p> : null}
    </div>
  );
}

/** label.flxfa-check with a checkbox. */
function CheckLabel({ checked, onChange, children }: { checked: boolean; onChange: (v: boolean) => void; children: ReactNode }) {
  return (
    <label className="flxfa-check">
      <input type="checkbox" checked={checked} onChange={(e) => onChange(e.target.checked)} />
      <span>{children}</span>
    </label>
  );
}
