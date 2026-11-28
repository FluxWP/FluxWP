import tr from './i18n/tr.json';
// Communication screen texts. Keys are the English source; Turkish is the shipped translation.
import de from './i18n/de.json';
import es from './i18n/es.json';
import fr from './i18n/fr.json';
import ptBr from './i18n/pt-br.json';
import ja from './i18n/ja.json';
import zh from './i18n/zh.json';
import ar from './i18n/ar.json';
import ru from './i18n/ru.json';
import { normalizeLocale } from '@fluxwp/core';


const dicts: Record<string, Record<string, string>> = { tr, de, es, fr, 'pt-br': ptBr, ja, zh, ar, ru };

export type Translate = (source: string, params?: Record<string, string | number>) => string;

export function translator(locale: string): Translate {
  const d = dicts[normalizeLocale(locale)] ?? {};
  return (source, params) => {
    const text = d[source] ?? source;
    return params ? text.replace(/\{(\w+)\}/g, (m, k: string) => (k in params ? String(params[k]) : m)) : text;
  };
}
