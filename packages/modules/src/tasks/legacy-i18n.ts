// Klasik çekirdek (core.js + docs.js) Türkçe kaynak metinle çağırır; sözlükler gen-react-i18n.py ile üretilir.
const load: Record<string, () => Promise<{ default: Record<string, string> }>> = {
  en: () => import('./legacy/en.json'),
  de: () => import('./legacy/de.json'),
  es: () => import('./legacy/es.json'),
  fr: () => import('./legacy/fr.json'),
  'pt-br': () => import('./legacy/pt-br.json'),
  ja: () => import('./legacy/ja.json'),
  zh: () => import('./legacy/zh.json'),
  ar: () => import('./legacy/ar.json'),
  ru: () => import('./legacy/ru.json'),
};

/** Dil kodu (flxgLocale biçimi ya da 'tr') için TR -> dil sözlüğü; Türkçe'de boş. */
export async function legacyDict(lang: string): Promise<Record<string, string>> {
  const l = lang.toLowerCase();
  if (l.startsWith('tr')) return {};
  const key = l.startsWith('pt') ? 'pt-br' : l.slice(0, 2);
  const f = load[key] ?? load.en!;
  return (await f()).default;
}
