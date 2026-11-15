import { createContext, createElement, useContext, type ReactNode } from 'react';
import {
  useMutation,
  useQuery,
  type QueryKey,
  type UseMutationOptions,
  type UseMutationResult,
  type UseQueryOptions,
  type UseQueryResult,
} from '@tanstack/react-query';
import type { GraphQLClient } from './client';
import type { FlxGraphQLError } from './errors';
import type { FlxOperation } from './operation';
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
  return useQuery<R, FlxGraphQLError, T>({
    ...options,
    queryKey: flxQueryKey(scope, op, variables ?? {}),
    queryFn: ({ signal }) => (client.request as (o: FlxOperation<R, V>, v: V, ro: { signal: AbortSignal }) => Promise<R>)(op, variables, { signal }),
  });
}

export function useFlxMutation<R, V, C = unknown>(
  op: FlxOperation<R, V>,
  options?: Omit<UseMutationOptions<R, FlxGraphQLError, V, C>, 'mutationFn'>,
): UseMutationResult<R, FlxGraphQLError, V, C> {
  const { client } = useGraphQL();
  return useMutation<R, FlxGraphQLError, V, C>({
    ...options,
    mutationFn: (variables) => (client.request as (o: FlxOperation<R, V>, v: V) => Promise<R>)(op, variables),
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
