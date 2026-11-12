/**
 * Default token library slug exposed by the REST API.
 *
 */
export const DEFAULT_LIBRARY_SLUG = 'default';

/**
 * Global injected by the admin feed localizer.
 *
 */
export const DESIGN_TOKENS_GLOBAL = 'flxbkDesignTokens';

/**
 * Global injected on the Style Library admin bundle by the pickable-token localizer (the same
 * payload the block editor's token picker reads). Read by `helpers/tokens.js`'s
 * `getPickableTokensPool()`.
 *
 */
export const PICKABLE_TOKENS_GLOBAL = 'flxbkDesignTokensPickable';
