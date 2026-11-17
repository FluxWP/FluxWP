// Field options from a manifest source: `options: { source, path, value, label }` (a select of the site's
// languages, a list of forms…). Runs the source like a screen would (shared cache) and maps the rows.
import { useQueries, type QueryClient } from '@tanstack/react-query';
import type { ApiClient } from '@fluxwp/core';
import { getPath, render, type ExprContext, type FormField, type Manifest } from '@fluxwp/manifest';
import { runRequest } from '../api';
import { qk } from '../query';
import { sourceRequest } from '../source';

export interface Option {
  value: string;
  label: string;
}
type SourceOptions = { source: string; path: string; value: string; label: string };

const fromSource = (o: FormField['options']): SourceOptions | null => (o && !Array.isArray(o) && typeof o === 'object' && 'source' in o ? (o as SourceOptions) : null);

/** Rows at `path` of a source result → options (`value` / `label` are field paths or templates). */
export function optionsFrom(data: unknown, o: SourceOptions): Option[] {
  const rows = getPath(data, o.path);
  if (!Array.isArray(rows)) return [];
  const pick = (row: unknown, p: string) => (p.includes('{{') ? render(p, { scope: row as Record<string, unknown> }) : String(getPath(row, p) ?? ''));
  return rows.filter((r) => r && typeof r === 'object').map((r) => ({ value: pick(r, o.value), label: pick(r, o.label) || pick(r, o.value) }));
}

function query(api: ApiClient, manifest: Manifest, o: SourceOptions, base: ExprContext) {
  const src = manifest.sources[o.source];
  if (!src) return null;
  const req = sourceRequest(src, base);
  return { queryKey: qk.source(manifest.id, o.source, req.path, req.query), queryFn: ({ signal }: { signal?: AbortSignal }) => runRequest(api, req, signal), staleTime: (src.cache?.stale ?? 60) * 1000 };
}

/** Form screens: fields with source options get their options resolved (empty until loaded). */
export function useSourceOptions(api: ApiClient, manifest: Manifest, fields: FormField[], base: ExprContext): Record<string, Option[]> {
  const wanted = fields.map((f) => [f.name, fromSource(f.options)] as const).filter((x): x is readonly [string, SourceOptions] => !!x[1]);
  const results = useQueries({ queries: wanted.map(([, o]) => query(api, manifest, o, base) ?? { queryKey: ['opt-none'], queryFn: async () => null, enabled: false }) });
  return Object.fromEntries(wanted.map(([name, o], i) => [name, optionsFrom(results[i]?.data, o)]));
}

/** Action prompts: resolve source options before the prompt opens. */
export async function resolvePromptOptions(client: QueryClient, api: ApiClient, manifest: Manifest, fields: FormField[], base: ExprContext): Promise<FormField[]> {
  return Promise.all(
    fields.map(async (f) => {
      const o = fromSource(f.options);
      const q = o ? query(api, manifest, o, base) : null;
      if (!o || !q) return f;
      try {
        return { ...f, options: optionsFrom(await client.fetchQuery(q), o) };
      } catch {
        return { ...f, options: [] };
      }
    }),
  );
}
