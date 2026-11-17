// Store screen texts. Keys are the English source; Turkish is the shipped translation.
import { normalizeLocale } from '@fluxwp/core';
import en from './i18n/en.json';
import tr from './i18n/tr.json';

const dicts: Record<string, Record<string, string>> = { en, tr };

export type Translate = (source: string, ...args: (string | number)[]) => string;

function fill(text: string, args: (string | number)[]): string {
  let i = 0;
  return text.replace(/%%|%(?:(\d+)\$)?[sd]/g, (m, pos: string | undefined) => (m === '%%' ? '%' : String(args[pos ? Number(pos) - 1 : i++] ?? '')));
}

export function translator(locale: string): { t: Translate } {
  const d = dicts[normalizeLocale(locale)] ?? {};
  return { t: (source, ...args) => fill(d[source] ?? source, args) };
}
