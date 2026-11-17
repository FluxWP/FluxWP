// Forms screen texts. Keys are the English source strings of Flux Forms; the Turkish dictionary is
// i18n/tr.json. A language without a dictionary shows the English source.
import { normalizeLocale } from '@fluxwp/core';
import tr from './i18n/tr.json';

const dicts: Record<string, Record<string, string>> = { tr };

export type Translate = (source: string, ...args: (string | number)[]) => string;

/** printf-style %s / %d / %1$s placeholders. */
function fill(text: string, args: (string | number)[]): string {
  let i = 0;
  return text.replace(/%(?:(\d+)\$)?[sd]/g, (_m, pos: string | undefined) => String(args[pos ? Number(pos) - 1 : i++] ?? ''));
}

export function translator(locale: string): { t: Translate } {
  const d = dicts[normalizeLocale(locale)] ?? {};
  const t: Translate = (source, ...args) => fill(d[source] ?? source, args);
  return { t };
}
