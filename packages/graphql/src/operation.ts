/**
 * A GraphQL operation shipped by a FluxWP plugin. Generated into `generated/operations.ts` from
 * `plugins/<slug>/graphql/operations/*.graphql`; the server knows it by `id` (persisted query).
 * `TResult` / `TVariables` exist only for type inference.
 */
export interface FlxOperation<TResult, TVariables> {
  /** sha256 of the printed document; the key in the plugin's persisted-queries.json. */
  readonly id: string;
  readonly name: string;
  readonly kind: 'query' | 'mutation';
  /** Plugin slug whose manifest carries the operation. */
  readonly plugin: string;
  /** Full document (operation + fragments). Sent only when the server does not know `id`. */
  readonly document: string;
  readonly __result?: TResult;
  readonly __variables?: TVariables;
}

export type ResultOf<O> = O extends FlxOperation<infer R, unknown> ? R : never;
export type VariablesOf<O> = O extends FlxOperation<unknown, infer V> ? V : never;

/** Operations without required variables can be called without a variables argument. */
export type VariablesArg<V> = Record<string, never> extends V ? [variables?: V] : [variables: V];
