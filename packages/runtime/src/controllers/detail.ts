// Detail and dashboard controllers: source data → visible sections with resolved values.
import { useMemo } from 'react';
import { useQueries, useQuery } from '@tanstack/react-query';
import { getPath, render, test, type DashboardScreen, type DetailScreen, type DetailSection, type ExprContext, type Manifest, type Tone } from '@fluxwp/manifest';
import { runRequest } from '../api';
import { qk } from '../query';
import { sourceRequest } from '../source';
import { useScope } from '../scope';
import { useActions } from './action';
import { useBaseContext, useNavigate, useText } from './context';
import { rowView } from './list';

export type SectionView =
  | { type: 'fields'; title: string; fields: { label: string; value: string; link: string; copy: boolean }[] }
  | { type: 'stats'; title: string; items: { label: string; value: string; sub: string; tone: Tone; press: (() => void) | null }[] }
  | { type: 'chips'; title: string; chips: { text: string; color: string }[] }
  | { type: 'timeline'; title: string; items: { title: string; body: string; at: string; label: string; link: string }[] }
  | { type: 'list'; title: string; rows: ReturnType<typeof rowView>[] }
  | { type: 'content'; title: string; value: string; format: 'markdown' | 'html' | 'text' }
  | { type: 'actions'; title: string; actions: { id: string; label: string; icon?: string; tone?: Tone }[] }
  | { type: 'notice'; title: string; tone: Tone; text: string };

type Text = (s: string | undefined, c?: ExprContext) => string;

export function sectionViews(
  sections: DetailSection[],
  ctx: ExprContext,
  text: Text,
  extras: { actions: (ids: string[], c: ExprContext) => { id: string; label: string; icon?: string; tone?: Tone }[]; go: (nav: NonNullable<Extract<DetailSection, { type: 'stats' }>['items'][number]['onPress']>, c: ExprContext) => void },
): SectionView[] {
  const out: SectionView[] = [];
  for (const s of sections) {
    if (!test(s.when, ctx)) continue;
    const title = text(s.title, ctx);
    const list = (path: string) => {
      const v = getPath(ctx.scope, path);
      return Array.isArray(v) ? (v as Record<string, unknown>[]) : [];
    };
    switch (s.type) {
      case 'fields':
        out.push({ type: 'fields', title, fields: s.fields.filter((f) => test(f.when, ctx)).map((f) => ({ label: text(f.label, ctx), value: text(f.value, ctx), link: render(f.link, ctx), copy: !!f.copy })).filter((f) => f.value) });
        break;
      case 'stats':
        out.push({ type: 'stats', title, items: s.items.filter((i) => test(i.when, ctx)).map((i) => ({ label: text(i.label, ctx), value: text(i.value, ctx), sub: text(i.sub, ctx), tone: i.tone ?? 'default', press: i.onPress ? () => extras.go(i.onPress!, ctx) : null })) });
        break;
      case 'chips':
        out.push({ type: 'chips', title, chips: list(s.path).map((c) => ({ text: render(s.text, { ...ctx, scope: c }), color: render(s.color, { ...ctx, scope: c }) })).filter((c) => c.text) });
        break;
      case 'timeline':
        out.push({ type: 'timeline', title, items: list(s.path).map((c) => { const x = { ...ctx, scope: c }; return { title: render(s.item.title, x), body: render(s.item.body, x), at: render(s.item.at, x), label: render(s.item.label, x), link: render(s.item.link, x) }; }) });
        break;
      case 'list':
        out.push({ type: 'list', title, rows: list(s.path).map((c, i) => rowView(s.item, c, ctx, String(c.id ?? i), text)) });
        break;
      case 'content':
        out.push({ type: 'content', title, value: text(s.value, ctx), format: s.format ?? 'text' });
        break;
      case 'actions':
        out.push({ type: 'actions', title, actions: extras.actions(s.items, ctx) });
        break;
      case 'notice':
        out.push({ type: 'notice', title, tone: s.tone ?? 'info', text: text(s.text, ctx) });
        break;
    }
  }
  return out.filter((s) => !('fields' in s) || s.fields.length);
}

export function useDetailController(manifest: Manifest, screen: DetailScreen, params: Record<string, string>) {
  const { api } = useScope();
  const text = useText();
  const base = useBaseContext(manifest, params);
  const source = manifest.sources[screen.source]!;
  const req = sourceRequest(source, base);
  const query = useQuery({
    queryKey: qk.source(manifest.id, screen.source, req.path, req.query),
    queryFn: ({ signal }) => runRequest<Record<string, unknown>>(api, req, signal),
    staleTime: (source.cache?.stale ?? 30) * 1000,
  });
  const { go } = useNavigate();
  const actions = useActions(manifest, { onRefresh: () => void query.refetch() });
  const ctx = useMemo<ExprContext>(() => ({ ...base, scope: query.data, data: query.data }), [base, query.data]);
  const sections = useMemo(
    () => (query.data ? sectionViews(screen.sections, ctx, text, { actions: (ids, c) => actions.list(ids, c), go }) : []),
    [query.data, screen.sections, ctx, text, actions, go],
  );
  return {
    title: text(screen.title ?? manifest.title, ctx),
    header: query.data && screen.header ? { title: text(screen.header.title, ctx), subtitle: text(screen.header.subtitle, ctx), avatar: screen.header.avatar ? { image: render(screen.header.avatar.image, ctx), text: render(screen.header.avatar.text, ctx) } : null } : null,
    sections,
    loading: query.isPending,
    refreshing: query.isRefetching,
    error: query.error,
    stale: !!query.data && query.isError,
    refresh: () => query.refetch(),
    headerActions: actions.list(screen.actions?.header, ctx),
    footerActions: actions.list(screen.actions?.footer, ctx),
    runAction: (id: string) => actions.run(id, ctx),
    running: actions.running,
  };
}

export function useDashboardController(manifest: Manifest, screen: DashboardScreen, params: Record<string, string>) {
  const { api } = useScope();
  const text = useText();
  const base = useBaseContext(manifest, params);
  const ids = screen.sources ?? [];
  const results = useQueries({
    queries: ids.map((id) => {
      const src = manifest.sources[id]!;
      const req = sourceRequest(src, base);
      return { queryKey: qk.source(manifest.id, id, req.path, req.query), queryFn: ({ signal }: { signal: AbortSignal }) => runRequest<Record<string, unknown>>(api, req, signal), staleTime: (src.cache?.stale ?? 60) * 1000 };
    }),
  });
  const { go } = useNavigate();
  const actions = useActions(manifest);
  const data = ids.length === 1 ? results[0]?.data : Object.fromEntries(ids.map((id, i) => [id, results[i]?.data]));
  const ctx = useMemo<ExprContext>(() => ({ ...base, scope: data, data }), [base, data]);
  return {
    title: text(screen.title ?? manifest.title, ctx),
    sections: sectionViews(screen.sections, ctx, text, { actions: (x, c) => actions.list(x, c), go }),
    loading: results.some((r) => r.isPending),
    error: results.find((r) => r.error)?.error ?? null,
    refresh: () => Promise.all(results.map((r) => r.refetch())),
    runAction: (id: string) => actions.run(id, ctx),
  };
}
