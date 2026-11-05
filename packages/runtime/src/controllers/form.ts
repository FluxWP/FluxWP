// Form controller: react-hook-form state, valibot field rules from the manifest, optional `load` source,
// submit → request → after. Server WP_Error `data.params` messages land on the fields.
import { useCallback, useEffect, useMemo } from 'react';
import { useForm, type RegisterOptions } from 'react-hook-form';
import { useQuery } from '@tanstack/react-query';
import * as v from 'valibot';
import { ApiError } from '@fluxwp/core';
import { getPath, test, type ExprContext, type FormField, type FormScreen, type Manifest } from '@fluxwp/manifest';
import { runRequest } from '../api';
import { useConfig } from '../hooks';
import { qk } from '../query';
import { formRequest, setPath, sourceRequest } from '../source';
import { useScope } from '../scope';
import { useBaseContext, useInvalidate, useNavigate, useText } from './context';

type Values = Record<string, unknown>;

/** valibot rule for one field; messages are app i18n keys (renderer translates). */
export function fieldSchema(f: FormField): v.GenericSchema<unknown> {
  const empty = (x: unknown) => x === undefined || x === null || x === '' || (Array.isArray(x) && !x.length);
  const checks: v.GenericSchema<unknown>[] = [];
  const s = (x: unknown) => String(x ?? '');
  const rule = (ok: (x: unknown) => boolean, key: string) => checks.push(v.custom<unknown>((x) => empty(x) || ok(x), key));
  if (f.type === 'email') rule((x) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(s(x)), 'form.invalid');
  if (f.type === 'url') rule((x) => /^https?:\/\/\S+$/i.test(s(x)), 'form.invalid');
  if (f.type === 'phone') rule((x) => s(x).replace(/\D/g, '').length >= 7, 'form.invalid');
  if (f.type === 'number' || f.type === 'money') {
    rule((x) => !Number.isNaN(Number(x)), 'form.invalid');
    if (f.min !== undefined) rule((x) => Number(x) >= f.min!, 'form.min');
    if (f.max !== undefined) rule((x) => Number(x) <= f.max!, 'form.max');
  }
  if (f.minLength !== undefined) rule((x) => s(x).length >= f.minLength!, 'form.minLength');
  if (f.maxLength !== undefined) rule((x) => s(x).length <= f.maxLength!, 'form.maxLength');
  if (f.pattern && f.pattern.length <= 200) {
    try {
      const re = new RegExp(`^(?:${f.pattern})$`);
      rule((x) => re.test(s(x)), 'form.invalid');
    } catch {
      /* invalid pattern from server: skip */
    }
  }
  if (f.required) checks.unshift(v.custom<unknown>((x) => (f.type === 'switch' ? x === true : !empty(x)), 'form.required'));
  return v.pipe(v.unknown(), ...(checks as [])) as v.GenericSchema<unknown>;
}

const plain = (x: unknown) => (x && typeof x === 'object' && 'raw' in (x as object) ? (x as { raw: unknown }).raw : x);

/** Media and relation fields travel as `{ id, url?, label? }` in the form and as the id on submit. */
export const RELATION_FIELDS = new Set<FormField['type']>(['image', 'file', 'user', 'post', 'term']);

export interface RelationValue {
  id: number;
  url?: string;
  label?: string;
}

export function relationValue(got: unknown): RelationValue | null {
  if (got === undefined || got === null || got === '' || got === 0) return null;
  if (typeof got === 'number' || (typeof got === 'string' && got !== '')) {
    const id = Number(got);
    return id ? { id } : null;
  }
  if (typeof got === 'object') {
    const o = got as { id?: unknown; source_url?: string; url?: string; name?: string; rendered?: string };
    const id = Number(o.id);
    if (!id) return null;
    const label = o.name || (typeof o.rendered === 'string' ? o.rendered : undefined);
    return { id, url: o.source_url || o.url, label };
  }
  return null;
}

function fieldValue(f: FormField, got: unknown): unknown {
  if (RELATION_FIELDS.has(f.type)) return relationValue(got);
  if (got !== undefined && got !== null) return got;
  if (f.default !== undefined) return f.default;
  if (f.type === 'switch') return false;
  if (f.type === 'multiselect' || f.type === 'tags') return [];
  return '';
}

export function initialValues(fields: FormField[], loaded?: Values): Values {
  const out: Values = {};
  for (const f of fields) {
    const got = loaded ? plain(getPath(loaded, f.load ?? f.name)) : undefined;
    setPath(out, f.name, fieldValue(f, got));
  }
  return out;
}

/** Drops empty secrets and relations; image/user/term/post/file submit their id, not the preview object. */
export function submitValues(fields: FormField[], vals: Values): Values {
  const sendable: Values = {};
  for (const f of fields) {
    const x = getPath(vals, f.name);
    if (f.type === 'readonly' || (f.type === 'secret' && !x)) continue;
    if (RELATION_FIELDS.has(f.type)) {
      const id = relationValue(x)?.id;
      if (!id) continue;
      setPath(sendable, f.name, id);
      continue;
    }
    setPath(sendable, f.name, x);
  }
  return sendable;
}

export function useFormController(manifest: Manifest, screen: FormScreen, params: Record<string, string>) {
  const { api, renderer } = useScope();
  const config = useConfig();
  const text = useText();
  const base = useBaseContext(manifest, params);
  const invalidate = useInvalidate(manifest.id);
  const { go } = useNavigate();
  const loadSrc = screen.load && params.id ? manifest.sources[screen.load] : undefined;
  const loadReq = loadSrc ? sourceRequest(loadSrc, base) : null;
  const loaded = useQuery({
    queryKey: loadReq ? qk.source(manifest.id, screen.load!, loadReq.path, loadReq.query) : ['form-none'],
    queryFn: ({ signal }) => runRequest<Values>(api, loadReq!, signal),
    enabled: !!loadReq,
  });

  const form = useForm<Values>({ defaultValues: initialValues(screen.fields, undefined), mode: 'onTouched' });
  useEffect(() => {
    if (loaded.data) form.reset(initialValues(screen.fields, loaded.data));
  }, [loaded.data, screen.fields, form]);

  const values = form.watch();
  const ctx: ExprContext = useMemo(() => ({ ...base, scope: values, data: loaded.data, input: values }), [base, values, loaded.data]);

  const fields = screen.fields
    .filter((f) => f.type !== 'hidden' && test(f.when, ctx))
    .map((f) => ({
      ...f,
      label: text(f.label, ctx),
      placeholder: text(f.placeholder, ctx) || undefined,
      help: text(f.help, ctx) || undefined,
      options: Array.isArray(f.options) ? f.options.map((o) => ({ value: o.value, label: text(o.label, ctx) })) : f.options,
      readonly: f.type === 'readonly' || (f.readonly_when ? test(f.readonly_when, ctx) : false),
    }));

  const rules = useCallback((f: FormField): RegisterOptions<Values> => {
    const schema = fieldSchema(f);
    return { validate: (x) => { const r = v.safeParse(schema, x); return r.success || r.issues[0]!.message; } };
  }, []);

  const submit = form.handleSubmit(async (vals) => {
    if (screen.validate?.any_of?.length && screen.validate.any_of.every((n) => { const x = getPath(vals, n); return x === '' || x === undefined || x === null; })) {
      form.setError('root', { message: text(screen.validate.message) || renderer.t('form.invalid') });
      return;
    }
    try {
      await runRequest(api, formRequest(screen, submitValues(screen.fields, vals), base, config.transport?.method_override !== false));
      const after = screen.after;
      renderer.ui.haptic?.('success');
      renderer.ui.toast(text(after?.toast) || renderer.t('form.saved'), 'ok');
      await invalidate(after?.invalidate);
      if (after?.back !== false) renderer.nav.back();
      else if (after?.navigate) go(after.navigate, ctx);
    } catch (e) {
      const bad = e instanceof ApiError ? ((e.data as { params?: Record<string, string> } | undefined)?.params ?? {}) : {};
      for (const [name, msg] of Object.entries(bad)) form.setError(name, { message: msg });
      form.setError('root', { message: e instanceof ApiError && e.message ? e.message : renderer.t('action.failed') });
    }
  });

  return {
    title: text(screen.title ?? manifest.title, ctx),
    form,
    fields,
    rules,
    submit,
    submitting: form.formState.isSubmitting,
    loading: !!loadReq && loaded.isPending,
    error: loaded.error,
    dirty: form.formState.isDirty,
    dirtyGuard: screen.dirty_guard !== false,
  };
}
