// Flux Block Editor dashboard texts. Keys are the English source strings of Flux Block Editor, so the
// plugin's language files translate them too (plugins/flx-blocks/tools/i18n/tr_TR.json).
import de from './i18n/de.json';
import es from './i18n/es.json';
import fr from './i18n/fr.json';
import ptBr from './i18n/pt-br.json';
import ja from './i18n/ja.json';
import zh from './i18n/zh.json';
import ar from './i18n/ar.json';
import ru from './i18n/ru.json';
import { normalizeLocale } from '@fluxwp/core';
import en from './i18n/en.json';
import tr from './i18n/tr.json';

const dicts: Record<string, Record<string, string>> = { en, tr, de, es, fr, 'pt-br': ptBr, ja, zh, ar, ru };

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
