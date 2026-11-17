import { createContext, createElement, useContext, type ReactNode } from 'react';
import {
  useMutation,
  useQuery,
  useQueryClient,
  type QueryKey,
  type UseMutationOptions,
  type UseMutationResult,
  type UseQueryOptions,
  type UseQueryResult,
} from '@tanstack/react-query';
import type { GraphQLClient } from './client';
import type { FlxGraphQLError } from './errors';
import type { FlxOperation } from './operation';
import { flxStoreGet, flxStoreKey, flxStoreMarkStale, flxStoreSet } from './store';
import { FlxViewer } from './generated/operations';
import type { FlxViewerQuery } from './generated/types';

interface Ctx {
  client: GraphQLClient;
  /** Query key prefix (one cache per account/site). */
  scope: string;
}

const GraphQLContext = createContext<Ctx | null>(null);

export function FlxGraphQLProvider({ client, scope, children }: Ctx & { children: ReactNode }) {
  return createElement(GraphQLContext.Provider, { value: { client, scope } }, children);
}

export function useGraphQL(): Ctx {
  const c = useContext(GraphQLContext);
  if (!c) throw new Error('FlxGraphQLProvider missing');
  return c;
}

/** Query key of an operation: [scope, 'gql', name, variables]. Invalidate by [scope, 'gql', name]. */
export const flxQueryKey = (scope: string, op: { name: string }, variables?: unknown): QueryKey =>
  variables === undefined ? [scope, 'gql', op.name] : [scope, 'gql', op.name, variables];

type QueryOpts<R, T> = Omit<UseQueryOptions<R, FlxGraphQLError, T>, 'queryKey' | 'queryFn'>;

export function useFlxQuery<R, V, T = R>(
  op: FlxOperation<R, V>,
  variables: V,
  options?: QueryOpts<R, T>,
): UseQueryResult<T, FlxGraphQLError> {
  const { client, scope } = useGraphQL();
  // Page-wide store (store.ts): what the server preloaded, what the navigator prefetched or what another
  // bundle on the page read shows at once; React Query refetches it when older than the stale time.
  const storeKey = flxStoreKey(op.id, variables ?? {});
  const seeded = options?.initialData === undefined ? flxStoreGet(storeKey) : undefined;
  return useQuery<R, FlxGraphQLError, T>({
    ...(seeded ? { initialData: seeded.data as R, initialDataUpdatedAt: seeded.at } : {}),
    ...options,
    queryKey: flxQueryKey(scope, op, variables ?? {}),
    queryFn: async ({ signal }) => {
      const r = await (client.request as (o: FlxOperation<R, V>, v: V, ro: { signal: AbortSignal }) => Promise<R>)(op, variables, { signal });
      flxStoreSet(storeKey, r);
      return r;
    },
  });
}

export function useFlxMutation<R, V, C = unknown>(
  op: FlxOperation<R, V>,
  options?: Omit<UseMutationOptions<R, FlxGraphQLError, V, C>, 'mutationFn'>,
): UseMutationResult<R, FlxGraphQLError, V, C> {
  const { client } = useGraphQL();
  const qc = useQueryClient();
  return useMutation<R, FlxGraphQLError, V, C>({
    ...options,
    mutationFn: async (variables) => {
      const r = await (client.request as (o: FlxOperation<R, V>, v: V) => Promise<R>)(op, variables);
      // A write may change what other screens show: their cached reads (not on screen now) and the
      // page-wide store stay visible but are refreshed when next used. The screen on display
      // invalidates what it needs itself.
      flxStoreMarkStale();
      void qc.invalidateQueries({ refetchType: 'none', predicate: (q) => q.getObserversCount() === 0 });
      return r;
    },
  });
}

/**
 * The signed-in user and the FluxWP catalog capabilities they have (flxViewer, kit 0.2). Cached for a
 * minute; the server still checks every field, this only hides what would be refused.
 */
export function useFlxViewer(options?: QueryOpts<FlxViewerQuery, FlxViewerQuery>): UseQueryResult<FlxViewerQuery, FlxGraphQLError> {
  return useFlxQuery(FlxViewer, {}, { staleTime: 60_000, ...options });
}

/** Whether the viewer has a catalog capability (flx_gql_capabilities): undefined while loading. */
export function useFlxCan(capability: string): boolean | undefined {
  const q = useFlxViewer();
  const v = q.data?.flxViewer;
  if (!v) return undefined;
  return v.capabilities.includes(capability);
}
