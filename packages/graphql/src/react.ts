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
