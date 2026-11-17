// Settings of this front end. Two variables are enough:
//   FLUX_WP_URL  address of the WordPress site (https://cms.example.com)
//   FLUX_KEY     connection key from FluxWP › Headless (front end id + secret, "<id>.<secret>")
// The older separate variables still work and win when set: FLUX_GRAPHQL_URL, FLUX_FRONTEND_ID,
// FLUX_REVALIDATE_SECRET, FLUX_PREVIEW_USER + FLUX_PREVIEW_APP_PASSWORD.

const key = (process.env.FLUX_KEY ?? '').trim();
const dot = key.indexOf('.');
const keyId = dot > 0 ? key.slice(0, dot) : '';
const keySecret = dot > 0 ? key.slice(dot + 1) : '';
const wp = (process.env.FLUX_WP_URL ?? '').trim().replace(/\/+$/, '');

/** Flux GraphQL endpoint: FLUX_GRAPHQL_URL, else <FLUX_WP_URL>/graphql. */
export const GRAPHQL_URL = process.env.FLUX_GRAPHQL_URL || (wp ? `${wp}/graphql` : '');
/** Front end id in WordPress; empty answers as the default front end. */
export const FRONTEND_ID = process.env.FLUX_FRONTEND_ID || keyId || undefined;
/** Signs webhooks, preview links and preview requests. */
export const SECRET = process.env.FLUX_REVALIDATE_SECRET || keySecret;
/** Optional: preview through an application password instead of the secret. */
export const PREVIEW_USER = process.env.FLUX_PREVIEW_USER ?? '';
export const PREVIEW_APP_PASSWORD = process.env.FLUX_PREVIEW_APP_PASSWORD ?? '';
