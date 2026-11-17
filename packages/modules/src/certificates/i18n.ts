// Certificate screen texts. The key is the English source; Turkish is i18n/tr.json.
import { normalizeLocale } from '@fluxwp/core';
import tr from './i18n/tr.json';

const dicts: Record<string, Record<string, string>> = { tr };

export type Translate = (source: string, ...args: (string | number)[]) => string;

function fill(text: string, args: (string | number)[]): string {
  let i = 0;
  return text.replace(/%(?:(\d+)\$)?[sd]/g, (_m, pos: string | undefined) => String(args[pos ? Number(pos) - 1 : i++] ?? ''));
}

export function translator(locale: string): { t: Translate; tn: (single: string, plural: string, n: number) => string } {
  const d = dicts[normalizeLocale(locale)] ?? {};
  const t: Translate = (source, ...args) => fill(d[source] ?? source, args);
  const tn = (single: string, plural: string, n: number) => fill(n === 1 ? (d[single] ?? single) : (d[plural] ?? plural), [n]);
  return { t, tn };
}
