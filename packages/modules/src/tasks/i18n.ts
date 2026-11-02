// Flux Tasks metinleri: WP eklentisiyle aynı yapı (Türkçe kaynak metin = anahtar, İngilizcesi en.json).
// en.json, plugins/flx-tasks/languages/flx-tasks-en_US.po'dan tools/fluxapp/flxg-i18n.mjs ile üretilir.
import en from './en.json';

const dicts: Record<string, Record<string, string>> = { en };
let lang = 'tr';

export function setYtgLang(l: string) {
  lang = (l || 'tr').toLowerCase().split(/[-_]/)[0]!;
}
export function flxgLocale() {
  return lang === 'en' ? 'en-US' : 'tr-TR';
}

/** Çeviri: tt('Görev ara…'), tt('{n} seçili', { n: 3 }). */
export function tt(s: string, vars?: Record<string, string | number>) {
  let out = dicts[lang]?.[s] || s;
  if (vars) for (const [k, v] of Object.entries(vars)) out = out.split('{' + k + '}').join(String(v));
  return out;
}
