// Templates and conditions (plan §7.7). Templates compile once into accessor functions; render does no parsing.
// No eval: a template is only paths + a closed list of filters.
import type { Condition } from './types/manifest';

/** Render context. Bare paths (`{{name}}`) resolve against `scope` (the row `item` or the source `data`). */
export interface ExprContext {
  scope?: unknown;
  item?: unknown;
  data?: unknown;
  params?: Record<string, unknown>;
  input?: Record<string, unknown>;
  me?: unknown;
  site?: unknown;
  can?: Record<string, boolean>;
  /** BCP 47 locale for number/date filters (user locale). */
  locale?: string;
}

const ROOTS = new Set(['item', 'data', 'params', 'input', 'me', 'site', 'can']);

export function getPath(obj: unknown, path: string): unknown {
  if (!path) return obj;
  let cur: unknown = obj;
  for (const k of path.split('.')) {
    if (cur === null || cur === undefined) return undefined;
    cur = Array.isArray(cur) && /^\d+$/.test(k) ? cur[Number(k)] : (cur as Record<string, unknown>)[k];
  }
  return cur;
}

export function lookup(ctx: ExprContext, path: string): unknown {
  const root = path.split('.', 1)[0]!;
  if (ROOTS.has(root)) return getPath(ctx[root as keyof ExprContext], path.slice(root.length + 1));
  return getPath(ctx.scope, path);
}

export const toText = (v: unknown): string => {
  if (v === null || v === undefined || v === false) return '';
  if (typeof v === 'string') return v;
  if (typeof v === 'number') return Number.isFinite(v) ? String(v) : '';
  if (v === true) return '1';
  if (Array.isArray(v)) return v.map(toText).filter(Boolean).join(', ');
  if (typeof v === 'object' && 'rendered' in (v as object)) return toText((v as { rendered: unknown }).rendered);
  return '';
};

const filled = (v: unknown) => !(v === null || v === undefined || v === '' || (Array.isArray(v) && !v.length));

// ---- dates (fallback only: servers send *_label from flx_date() / flx_time_ago()) ----

function toDate(v: unknown): Date | null {
  if (v instanceof Date) return v;
  if (typeof v === 'number') return new Date(v < 1e12 ? v * 1000 : v);
  if (typeof v === 'string' && v) {
    const d = new Date(/^\d{4}-\d{2}-\d{2} \d/.test(v) ? v.replace(' ', 'T') : v);
    return Number.isNaN(d.getTime()) ? null : d;
  }
  return null;
}

export function formatDate(v: unknown, locale: string, withTime = false): string {
  const d = toDate(v);
  if (!d) return '';
  return new Intl.DateTimeFormat(locale, withTime ? { dateStyle: 'medium', timeStyle: 'short' } : { dateStyle: 'medium' }).format(d);
}

export function timeAgo(v: unknown, locale: string, now = Date.now()): string {
  const d = toDate(v);
  if (!d) return '';
  const RTF = (Intl as unknown as { RelativeTimeFormat?: typeof Intl.RelativeTimeFormat }).RelativeTimeFormat;
  if (!RTF) return formatDate(d, locale, true);
  const s = Math.round((d.getTime() - now) / 1000);
  const abs = Math.abs(s);
  const rtf = new RTF(locale, { numeric: 'auto' });
  if (abs < 60) return rtf.format(s, 'second');
  if (abs < 3600) return rtf.format(Math.round(s / 60), 'minute');
  if (abs < 86400) return rtf.format(Math.round(s / 3600), 'hour');
  if (abs < 86400 * 30) return rtf.format(Math.round(s / 86400), 'day');
  return formatDate(d, locale);
}

// ---- filters ----

type Arg = { literal: string } | { path: string };
type Filter = (v: unknown, args: Arg[], ctx: ExprContext) => unknown;

const argVal = (a: Arg | undefined, ctx: ExprContext): unknown => {
  if (!a) return undefined;
  if ('literal' in a) return a.literal;
  const v = lookup(ctx, a.path);
  return v === undefined ? a.path : v;
};

const num = (v: unknown) => (typeof v === 'number' ? v : typeof v === 'string' && v.trim() !== '' ? Number(v) : NaN);

const FILTERS: Record<string, Filter> = {
  number: (v, _a, c) => (Number.isFinite(num(v)) ? new Intl.NumberFormat(c.locale ?? 'tr').format(num(v)) : ''),
  money: (v, a, c) => {
    const n = num(v);
    if (!Number.isFinite(n)) return '';
    const cur = String(argVal(a[0], c) ?? 'TRY').toUpperCase();
    try {
      return new Intl.NumberFormat(c.locale ?? 'tr', { style: 'currency', currency: /^[A-Z]{3}$/.test(cur) ? cur : 'TRY' }).format(n);
    } catch {
      return new Intl.NumberFormat(c.locale ?? 'tr').format(n);
    }
  },
  date: (v, _a, c) => formatDate(v, c.locale ?? 'tr'),
  datetime: (v, _a, c) => formatDate(v, c.locale ?? 'tr', true),
  ago: (v, _a, c) => timeAgo(v, c.locale ?? 'tr'),
  default: (v, a, c) => (filled(v) ? v : argVal(a[0], c)),
  join: (v, a, c) => (Array.isArray(v) ? v.map(toText).filter(Boolean).join(String(argVal(a[0], c) ?? ', ')) : v),
  count: (v) => (Array.isArray(v) ? v.length : v && typeof v === 'object' ? Object.keys(v).length : 0),
  upper: (v, _a, c) => toText(v).toLocaleUpperCase(c.locale ?? 'tr'),
  lower: (v, _a, c) => toText(v).toLocaleLowerCase(c.locale ?? 'tr'),
  truncate: (v, a, c) => {
    const n = Number(argVal(a[0], c)) || 80;
    const s = toText(v);
    return s.length > n ? s.slice(0, n - 1).trimEnd() + '…' : s;
  },
  initials: (v) =>
    toText(v)
      .split(/\s+/)
      .filter(Boolean)
      .slice(0, 2)
      .map((w) => w[0]!.toLocaleUpperCase())
      .join(''),
  phone: (v) => toText(v).replace(/[^\d]/g, ''),
  bool: (v, a, c) => toText(v && v !== '0' ? argVal(a[0], c) : argVal(a[1], c)),
};

export const FILTER_NAMES = Object.keys(FILTERS);

// ---- compile ----

function splitArgs(s: string): string[] {
  const out: string[] = [];
  let cur = '';
  let q: string | null = null;
  for (const ch of s) {
    if (q) {
      cur += ch;
      if (ch === q) q = null;
    } else if (ch === "'" || ch === '"') {
      q = ch;
      cur += ch;
    } else if (ch === ':') {
      out.push(cur);
      cur = '';
    } else cur += ch;
  }
  out.push(cur);
  return out;
}

function parseArg(s: string): Arg {
  const t = s.trim();
  const m = t.match(/^(['"])(.*)\1$/);
  return m ? { literal: m[2]! } : { path: t };
}

type Expr = (ctx: ExprContext) => unknown;

function compileExpr(src: string): Expr {
  const [head, ...pipes] = src.split('|');
  const path = head!.trim();
  const steps = pipes.map((p) => {
    const [name, ...args] = splitArgs(p);
    const f = FILTERS[name!.trim()];
    const parsed = args.map(parseArg);
    return f ? (v: unknown, ctx: ExprContext) => f(v, parsed, ctx) : (v: unknown) => v;
  });
  return (ctx) => steps.reduce((v, step) => step(v, ctx), lookup(ctx, path));
}

const TPL = /\{\{([^{}]+)\}\}/g;
const cache = new Map<string, (ctx: ExprContext) => unknown>();

/**
 * Compiles a template. `"{{x}}"` alone returns the raw value (number, bool, object: request bodies);
 * otherwise the result is a string. Text without `{{` is returned as is.
 */
export function compile(tpl: string): (ctx: ExprContext) => unknown {
  const hit = cache.get(tpl);
  if (hit) return hit;
  let fn: (ctx: ExprContext) => unknown;
  const whole = tpl.match(/^\{\{([^{}]+)\}\}$/);
  if (whole) fn = compileExpr(whole[1]!);
  else if (!tpl.includes('{{')) fn = () => tpl;
  else {
    const parts: (string | Expr)[] = [];
    let last = 0;
    for (const m of tpl.matchAll(TPL)) {
      if (m.index! > last) parts.push(tpl.slice(last, m.index));
      parts.push(compileExpr(m[1]!));
      last = m.index! + m[0].length;
    }
    if (last < tpl.length) parts.push(tpl.slice(last));
    fn = (ctx) => parts.map((p) => (typeof p === 'string' ? p : toText(p(ctx)))).join('');
  }
  if (cache.size > 2000) cache.clear();
  cache.set(tpl, fn);
  return fn;
}

export const render = (tpl: string | undefined, ctx: ExprContext): string => (tpl ? toText(compile(tpl)(ctx)) : '');
export const value = (tpl: unknown, ctx: ExprContext): unknown => (typeof tpl === 'string' ? compile(tpl)(ctx) : tpl);

/** Deep template fill for request bodies / params (keeps types for whole-value templates). */
export function fill<T>(v: T, ctx: ExprContext): T {
  if (typeof v === 'string') return compile(v)(ctx) as T;
  if (Array.isArray(v)) return v.map((x) => fill(x, ctx)) as T;
  if (v && typeof v === 'object') return Object.fromEntries(Object.entries(v).map(([k, x]) => [k, fill(x, ctx)])) as T;
  return v;
}

// ---- conditions ----

export function test(cond: Condition | string | undefined, ctx: ExprContext): boolean {
  if (cond === undefined) return true;
  if (typeof cond === 'string') return !!lookup(ctx, cond);
  if ('all' in cond) return cond.all.every((c) => test(c, ctx));
  if ('any' in cond) return cond.any.some((c) => test(c, ctx));
  if ('not' in cond) return !test(cond.not, ctx);
  const v = lookup(ctx, cond.path);
  const x = cond.value;
  switch (cond.op) {
    case 'eq':
      return v == x; // eslint-disable-line eqeqeq
    case 'ne':
      return v != x; // eslint-disable-line eqeqeq
    case 'in':
      return Array.isArray(x) && x.some((y) => y == v); // eslint-disable-line eqeqeq
    case 'nin':
      return !(Array.isArray(x) && x.some((y) => y == v)); // eslint-disable-line eqeqeq
    case 'gt':
      return num(v) > num(x);
    case 'gte':
      return num(v) >= num(x);
    case 'lt':
      return num(v) < num(x);
    case 'lte':
      return num(v) <= num(x);
    case 'filled':
      return filled(v);
    case 'empty':
      return !filled(v);
    case 'truthy':
      return !!v && v !== '0';
    case 'falsy':
      return !v || v === '0';
    default:
      return false;
  }
}
