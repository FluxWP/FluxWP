// Canonical keys are English; aliases accept older task-engine payloads.
import { normalizeLocale } from '@fluxwp/core';
import aliases from './aliases.json';
import en from './en.json';
import tr from './tr.json';
// English and Turkish ship with the screen; the other languages load on their own when the screen opens in one.
const dicts: Record<string, Record<string, string>> = { en, tr };
const loaders: Record<string, () => Promise<{ default: Record<string, string> }>> = {
  de: () => import('./de.json'),
  es: () => import('./es.json'),
  fr: () => import('./fr.json'),
  'pt-br': () => import('./pt-br.json'),
  ja: () => import('./ja.json'),
  zh: () => import('./zh.json'),
  ar: () => import('./ar.json'),
  ru: () => import('./ru.json'),
};
/** The language's words are here (or it has none of its own and falls back to English). */
export function ytgLangReady(value: string) {
  const l = normalizeLocale(value);
  return !!dicts[l] || !loaders[l];
}
/** Loads a language's words; resolves when tt() can use them. */
export function loadYtgLang(value: string): Promise<void> {
  const l = normalizeLocale(value);
  if (dicts[l] || !loaders[l]) return Promise.resolve();
  return loaders[l]!().then(
    (m) => void (dicts[l] = m.default),
    () => void (dicts[l] = dicts.en!),
  );
}
let lang = 'tr';
export function setYtgLang(value: string) { lang = normalizeLocale(value); }
export function flxgLocale() { return lang === 'pt-br' ? 'pt-BR' : lang === 'zh' ? 'zh-CN' : lang; }
export function tt(source: string, vars?: Record<string, string | number>) {
 const key = (aliases as Record<string,string>)[source] || source;
 let out = dicts[lang]?.[key] || dicts.en?.[key] || key;
 if (vars) for (const [k,v] of Object.entries(vars)) out=out.split('{'+k+'}').join(String(v));
 return out;
}
