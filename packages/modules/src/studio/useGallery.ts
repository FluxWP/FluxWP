// The gallery list: filters, offset paging, merge of updates, follow-up of running generations.
import { useCallback, useEffect, useRef, useState } from 'react';
import { useGraphQL } from '@fluxwp/graphql/react';
import { FlxStudioItems, FlxStudioPoll } from '@fluxwp/graphql/operations';
import { call, isPending, toKind, type Filter, type Item } from './lib';

export interface Gallery {
  items: Item[];
  hasMore: boolean;
  loading: boolean;
  error: string;
  loadMore: () => void;
  reload: () => void;
  upsert: (it: Item, prepend?: boolean) => void;
  patch: (id: number, p: Partial<Item>) => void;
  remove: (ids: number[]) => void;
  /** Called with the items that finished (or failed) while following jobs. */
  follow: (cb: (r: { done: number; failed: number }) => void) => void;
  kick: (ms?: number) => void;
}

export function useGallery(f: Filter): Gallery {
  const { client } = useGraphQL();
  const [items, setItems] = useState<Item[]>([]);
  const [hasMore, setHasMore] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const cursor = useRef<string | null>(null);
  const moreRef = useRef(false);
  const busy = useRef(false);
  const token = useRef(0);
  const filterRef = useRef(f);
  filterRef.current = f;
  const itemsRef = useRef(items);
  itemsRef.current = items;

  const load = useCallback(
    async (reset: boolean) => {
      if (!reset && (busy.current || !moreRef.current)) return;
      if (reset) token.current++;
      const tk = token.current;
      busy.current = true;
      setLoading(true);
      try {
        const r = await client.request(FlxStudioItems, {
          first: 60,
          after: reset ? null : cursor.current,
          folderId: f.folder === 'all' ? null : f.folder,
          kind: f.kind ? toKind(f.kind) : null,
          favorite: f.fav ? true : null,
          search: f.q || null,
          allOwners: f.owner === 'all',
        });
        if (tk !== token.current) return;
        const page = r.flxStudioItems;
        cursor.current = page.pageInfo.endCursor ?? null;
        moreRef.current = page.pageInfo.hasNextPage;
        setHasMore(page.pageInfo.hasNextPage);
        setItems((prev) => {
          const base = reset ? [] : prev;
          const seen = new Set(base.map((x) => x.databaseId));
          return [...base, ...page.nodes.filter((x) => !seen.has(x.databaseId))];
        });
        setError('');
      } catch (e) {
        if (tk === token.current && reset) {
          setItems([]);
          setError(e instanceof Error ? e.message : 'error');
        }
      } finally {
        if (tk === token.current) {
          busy.current = false;
          setLoading(false);
        }
      }
    },
    [client, f.folder, f.kind, f.fav, f.q, f.owner],
  );
  useEffect(() => {
    void load(true);
  }, [load]);

  const matches = (it: Item): boolean => {
    const x = filterRef.current;
    if (x.fav || x.q) return false;
    if (x.kind && it.kind !== toKind(x.kind)) return false;
    if (x.folder !== 'all' && x.folder !== (it.folderId ?? 0)) return false;
    return true;
  };
  const upsert = useCallback((it: Item, prepend?: boolean) => {
    setItems((prev) => {
      const i = prev.findIndex((x) => x.databaseId === it.databaseId);
      if (i >= 0) {
        const n = [...prev];
        n[i] = it;
        return n;
      }
      if (!matches(it)) return prev;
      return prepend ? [it, ...prev] : [...prev, it];
    });
  }, []);
  const patch = useCallback((id: number, p: Partial<Item>) => setItems((prev) => prev.map((x) => (x.databaseId === id ? { ...x, ...p } : x))), []);
  const remove = useCallback((ids: number[]) => setItems((prev) => prev.filter((x) => !ids.includes(x.databaseId))), []);

  // Following running generations: 4 s, then 8 s, then 15 s; paused while the tab is hidden.
  const jobsKey = [...new Set(items.filter(isPending).flatMap((x) => (x.job ? [x.job] : [])))].join(',');
  const [tick, setTick] = useState(0);
  const start = useRef(0);
  const nextWait = useRef<number | null>(null);
  const cb = useRef<(r: { done: number; failed: number }) => void>(() => undefined);
  useEffect(() => {
    if (!jobsKey) {
      start.current = 0;
      return;
    }
    if (!start.current) start.current = Date.now();
    const age = (Date.now() - start.current) / 1000;
    const wait = nextWait.current ?? (age < 120 ? 4000 : age < 600 ? 8000 : 15000);
    nextWait.current = null;
    const id = window.setTimeout(() => {
      if (document.hidden) return;
      const jobs = jobsKey.split(',').slice(0, 8);
      void call(client.request(FlxStudioPoll, { input: { jobs } }).then((x) => x.flxStudioPoll))
        .then((r) => {
          let done = 0;
          let failed = 0;
          for (const it of r.items ?? []) {
            const old = itemsRef.current.find((x) => x.databaseId === it.databaseId);
            if (old && isPending(old) && it.state === 'DONE') done++;
            if (old && isPending(old) && it.state === 'FAILED') failed++;
            upsert(it, true);
          }
          if (done || failed) cb.current({ done, failed });
        })
        .catch(() => undefined)
        .finally(() => setTick((x) => x + 1));
    }, wait);
    return () => window.clearTimeout(id);
  }, [jobsKey, tick, client, upsert]);
  useEffect(() => {
    const on = () => {
      if (!document.hidden) {
        nextWait.current = 500;
        setTick((x) => x + 1);
      }
    };
    document.addEventListener('visibilitychange', on);
    return () => document.removeEventListener('visibilitychange', on);
  }, []);

  return {
    items,
    hasMore,
    loading,
    error,
    loadMore: () => void load(false),
    reload: () => void load(true),
    upsert,
    patch,
    remove,
    follow: (fn) => {
      cb.current = fn;
    },
    kick: (ms) => {
      start.current = Date.now();
      nextWait.current = ms ?? 3000;
      setTick((x) => x + 1);
    },
  };
}
