/** Error codes of Flux GraphQL (`extensions.code`), see plugins/flx-graphql/includes/kit/errors.php. */
export type FlxErrorCode =
  | 'UNAUTHENTICATED'
  | 'FORBIDDEN'
  | 'NOT_FOUND'
  | 'INVALID_INPUT'
  | 'CONFLICT'
  | 'RATE_LIMITED'
  | 'INTERNAL'
  | 'PERSISTED_QUERY_NOT_FOUND'
  | 'QUERY_NOT_ALLOWED'
  | 'NETWORK'
  | 'BAD_RESPONSE';

export interface GraphQLErrorItem {
  message: string;
  path?: ReadonlyArray<string | number>;
  extensions?: { code?: string; field?: string; data?: unknown; [key: string]: unknown };
}

const KNOWN: ReadonlySet<string> = new Set<FlxErrorCode>([
  'UNAUTHENTICATED', 'FORBIDDEN', 'NOT_FOUND', 'INVALID_INPUT', 'CONFLICT', 'RATE_LIMITED', 'INTERNAL',
  'PERSISTED_QUERY_NOT_FOUND', 'QUERY_NOT_ALLOWED', 'NETWORK', 'BAD_RESPONSE',
]);

const STATUS: Record<number, FlxErrorCode> = { 401: 'UNAUTHENTICATED', 403: 'FORBIDDEN', 404: 'NOT_FOUND', 429: 'RATE_LIMITED' };

/** A failed GraphQL request: transport error, or `errors` with no usable `data`. */
export class FlxGraphQLError extends Error {
  readonly code: FlxErrorCode;
  readonly field: string | undefined;
  readonly errors: readonly GraphQLErrorItem[];
  readonly status: number;
  readonly operation: string;

  constructor(operation: string, status: number, errors: readonly GraphQLErrorItem[], fallback?: FlxErrorCode) {
    const first = errors[0];
    super(first?.message || fallback || 'GraphQL request failed');
    this.name = 'FlxGraphQLError';
    this.operation = operation;
    this.status = status;
    this.errors = errors;
    const raw = first?.extensions?.code;
    this.code = raw && KNOWN.has(raw) ? (raw as FlxErrorCode) : (fallback ?? STATUS[status] ?? 'INTERNAL');
    this.field = typeof first?.extensions?.field === 'string' ? first.extensions.field : undefined;
  }
}

export const isFlxGraphQLError = (e: unknown): e is FlxGraphQLError => e instanceof FlxGraphQLError;
