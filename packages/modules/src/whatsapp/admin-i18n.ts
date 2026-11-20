// Texts of the Flux Chat settings and reports screens. Keys are the English source strings of the PHP
// package (the screens reuse its wording), so the plugin's language packs translate them too. The
// dictionaries are written by plugins/flx-chat/tools/i18n.py.
import { normalizeLocale } from '@fluxwp/core';
import en from './i18n-admin/en.json';
import tr from './i18n-admin/tr.json';

const dicts: Record<string, Record<string, string>> = { en, tr };

export type Translate = (source: string, ...args: (string | number)[]) => string;

/** printf-style %s / %d / %1$s placeholders. */
function fill(text: string, args: (string | number)[]): string {
  let i = 0;
  return text.replace(/%(?:(\d+)\$)?[sd]/g, (_m, pos: string | undefined) => String(args[pos ? Number(pos) - 1 : i++] ?? ''));
}

export function adminTranslator(locale: string): { t: Translate } {
  const d = dicts[normalizeLocale(locale)] ?? {};
  const t: Translate = (source, ...args) => fill(d[source] ?? source, args);
  return { t };
}
