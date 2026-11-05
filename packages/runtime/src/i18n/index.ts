// Shell strings + @fluxwp/core strings as one i18next resource set (TR default, EN).
import { normalizeLocale, resources as coreResources, type Locale } from '@fluxwp/core';
import tr from './tr.json';
import en from './en.json';

export const shellStrings = { tr, en } as const;

type Tree = { [k: string]: string | Tree };

function deepMerge(a: Tree, b: Tree): Tree {
  const out: Tree = { ...a };
  for (const [k, v] of Object.entries(b)) {
    const cur = out[k];
    out[k] = typeof v === 'object' && typeof cur === 'object' ? deepMerge(cur, v) : v;
  }
  return out;
}

/** i18next `resources` for an app built on core + shell. */
export const appResources = Object.fromEntries(
  (Object.keys(coreResources) as Locale[]).map((l) => [l, { translation: deepMerge(coreResources[l].translation as unknown as Tree, shellStrings[l] as unknown as Tree) }]),
) as Record<Locale, { translation: Tree }>;

/** "$t:key" (built-in configs) → translated; any other text is server text and shown as is. */
export const textOf = (s: string | undefined, t: (k: string) => string): string => (s?.startsWith('$t:') ? t(s.slice(3)) : (s ?? ''));

export { normalizeLocale };
