// Flux Block Editor dashboard texts. Keys are the English source strings of Flux Block Editor, so the
// plugin's language files translate them too (plugins/flx-blocks/tools/i18n/tr_TR.json).
import { normalizeLocale } from '@fluxwp/core';
import en from './i18n/en.json';
import tr from './i18n/tr.json';

const dicts: Record<string, Record<string, string>> = { en, tr };

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
