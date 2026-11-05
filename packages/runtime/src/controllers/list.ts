// List screen controller: source → infinite query (page/wp/cursor), search (debounced), filters,
// pull-to-refresh, rows resolved to plain view data. Renderers only draw `rows`.
import { useCallback, useEffect, useMemo, useState } from 'react';
import { useInfiniteQuery } from '@tanstack/react-query';
import { getPath, render, test, type ExprContext, type ListFilter, type ListItem, type ListScreen, type Manifest, type Tone } from '@fluxwp/manifest';
import { runRequestRaw } from '../api';
import { qk } from '../query';
import { parsePage, sourceRequest, type Page } from '../source';
import { useScope } from '../scope';
import { type ActionView, useActions } from './action';
import { useBaseContext, useNavigate, useText } from './context';

export interface RowView {
  key: string;
  title: string;
  subtitle: string;
  meta: string;
  caption: string;
  avatar: { image: string; text: string } | null;
  image: string;
  badges: { text: string; tone: Tone }[];
  chips: { text: string; color: string }[];
  unread: boolean;
  /** Template context of this row (actions, navigation). */
  ctx: ExprContext;
}

export function rowView(item: ListItem, data: Record<string, unknown>, base: ExprContext, key: string, text: (s: string | undefined, c?: ExprContext) => string): RowView {
  const ctx: ExprContext = { ...base, scope: data, item: data };
  const chipsSrc = item.chips ? getPath(data, item.chips.path) : null;
  return {
    key,
    title: text(item.title, ctx),
    subtitle: text(item.subtitle, ctx),
    meta: text(item.meta, ctx),
    caption: text(item.caption, ctx),
    avatar: item.avatar ? { image: render(item.avatar.image, ctx), text: render(item.avatar.text, ctx) } : null,
    image: render(item.image, ctx),
    badges: (item.badges ?? []).filter((b) => test(b.when, ctx)).map((b) => ({ text: text(b.text, ctx), tone: b.tone ?? 'default' })).filter((b) => b.text),
    chips: Array.isArray(chipsSrc)
      ? chipsSrc.map((c) => ({ text: render(item.chips!.text, { ...ctx, scope: c }), color: render(item.chips!.color, { ...ctx, scope: c }) })).filter((c) => c.text)
      : [],
    unread: item.unread ? test(item.unread, ctx) : false,
    ctx,
  };
}

function useDebounced<T>(value: T, ms: number): T {
  const [v, setV] = useState(value);
  useEffect(() => {
    const t = setTimeout(() => setV(value), ms);
    return () => clearTimeout(t);
  }, [value, ms]);
  return v;
}

export interface FilterView {
  param: string;
  label: string;
  type: ListFilter['type'];
  options: { value: string; label: string }[];
  value: string | number | boolean | null;
}

export function useListController(manifest: Manifest, screen: ListScreen, params: Record<string, string>) {
  const { api } = useScope();
  const text = useText();
  const base = useBaseContext(manifest, params);
  const source = manifest.sources[screen.source]!;
  const [search, setSearch] = useState('');
  const q = useDebounced(search, 350);
  const [filters, setFilters] = useState<Record<string, string | number | boolean | null>>(() => {
    const init: Record<string, string | number | boolean | null> = {};
    for (const f of screen.filters ?? []) if (params[f.param] !== undefined) init[f.param] = params[f.param]!;
      else if (f.type === 'segmented' && Array.isArray(f.options) && f.options[0]) init[f.param] = f.options[0].value;
    return init;
  });

  const listQuery = { search: screen.search ? { param: screen.search.param, value: q } : undefined, filters };
  const first = sourceRequest(source, base, { ...listQuery, page: 1 });

  const query = useInfiniteQuery<Page, Error, { pages: Page[] }, readonly unknown[], number | string>({
    queryKey: qk.source(manifest.id, screen.source, first.path, first.query),
    initialPageParam: 1,
    queryFn: async ({ pageParam, signal }) => {
      const req = sourceRequest(source, base, typeof pageParam === 'number' ? { ...listQuery, page: pageParam } : { ...listQuery, cursor: pageParam });
      const res = await runRequestRaw(api, req, signal);
      return parsePage(source, res.data, typeof pageParam === 'number' ? pageParam : 1, res.headers);
    },
    getNextPageParam: (last) => last.next ?? undefined,
    staleTime: (source.cache?.stale ?? 30) * 1000,
    refetchInterval: source.poll && source.poll >= 10 ? source.poll * 1000 : false,
    refetchIntervalInBackground: false,
  });

  const key = source.key ?? 'id';
  const rows = useMemo(
    () => (query.data?.pages ?? []).flatMap((p) => p.items).map((d, i) => rowView(screen.item, d, base, String(getPath(d, key) ?? i), text)),
    [query.data, screen.item, base, key, text],
  );

  const { toRoute, go } = useNavigate();
  const actions = useActions(manifest, { onRefresh: () => void query.refetch() });
  const press = useCallback((row: RowView) => screen.onPress && go(screen.onPress, row.ctx), [screen.onPress, go]);

  const filterViews: FilterView[] = (screen.filters ?? []).map((f) => ({
    param: f.param,
    label: text(f.label),
    type: f.type,
    options: Array.isArray(f.options) ? f.options.map((o) => ({ value: o.value, label: text(o.label) })) : [],
    value: filters[f.param] ?? null,
  }));

  return {
    title: text(screen.title ?? manifest.title, base),
    rows,
    total: query.data?.pages[0]?.total,
    loading: query.isPending,
    refreshing: query.isRefetching && !query.isFetchingNextPage,
    error: query.error,
    /** Showing cached data while the last refresh failed (offline). */
    stale: !!query.data && query.isError,
    hasMore: !!query.hasNextPage,
    loadingMore: query.isFetchingNextPage,
    refresh: () => query.refetch(),
    loadMore: () => (query.hasNextPage && !query.isFetchingNextPage ? query.fetchNextPage() : undefined),
    search: screen.search ? { value: search, set: setSearch, placeholder: text(screen.search.placeholder) } : null,
    filters: filterViews,
    setFilter: (param: string, value: string | number | boolean | null) => setFilters((f) => ({ ...f, [param]: value })),
    empty: { title: text(screen.empty?.title), body: text(screen.empty?.body), icon: screen.empty?.icon, searching: !!q.trim() },
    onPress: screen.onPress ? press : null,
    routeFor: (row: RowView) => (screen.onPress ? toRoute(screen.onPress, row.ctx) : null),
    headerActions: actions.list(screen.actions?.header, base),
    fab: screen.actions?.fab ? actions.list([screen.actions.fab], base)[0] ?? null : null,
    rowActions: (row: RowView): ActionView[] => actions.list(screen.actions?.row, row.ctx),
    /** Bulk actions use the list context for visibility; `runAction(id, row)` runs one row. */
    bulkActions: actions.list(screen.actions?.bulk, base),
    /** Desktop table cells (manifest `columns`). Empty when the screen has no columns. */
    cells: (row: RowView) => (screen.columns ?? []).map((c) => ({ label: text(c.label), align: c.align ?? ('start' as const), width: c.width, text: text(c.value, row.ctx) })),
    runAction: (id: string, row?: RowView) => actions.run(id, row?.ctx ?? base),
    running: actions.running,
  };
}

export type ListController = ReturnType<typeof useListController>;
