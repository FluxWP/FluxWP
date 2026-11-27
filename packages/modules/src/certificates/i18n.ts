// Certificate screen texts. The key is the English source; Turkish is i18n/tr.json.
import de from './i18n/de.json';
import es from './i18n/es.json';
import fr from './i18n/fr.json';
import ptBr from './i18n/pt-br.json';
import ja from './i18n/ja.json';
import zh from './i18n/zh.json';
import ar from './i18n/ar.json';
import ru from './i18n/ru.json';
import { normalizeLocale } from '@fluxwp/core';
import tr from './i18n/tr.json';

const dicts: Record<string, Record<string, string>> = { tr, de, es, fr, 'pt-br': ptBr, ja, zh, ar, ru };

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
