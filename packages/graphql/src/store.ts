/**
 * Page-wide result store shared by every FluxWP bundle on the page.
 *
 * In wp-admin each plugin ships its own copy of the screen bundle, so two screens of two plugins can run
 * in two copies of React Query. This store sits on `globalThis` and is keyed by the operation's persisted
 * id (sha256 of its document) plus its variables, so a result written by one copy is valid for any other
 * copy that sends the same document, whatever the bundle version.
 *
 * Writers: the server (Flux GraphQL prints the first queries of a screen into the page,
 * `<script type="application/json" id="flx-gql-preload">`), the screen navigator (prefetch on hover) and
 * every successful query (react.ts). Readers: `useFlxQuery` uses an entry as initial data, so a screen
 * shows at once and refreshes in the background when the entry is older than the stale time.
 *
 * Key format (also built by PHP, flx_gql_preload_key()): `<id>:<variables as JSON, object keys sorted>`,
 * no variables = `{}`.
 */

export interface FlxStoreEntry {
  data: unknown;
  /** Date.now() when the result was read from the server; 0 = stale (a write happened since). */
  at: number;
}

interface Seed {
  at?: number;
  entries?: Record<string, unknown>;
}

interface StoreGlobal {
  __flxGqlStore?: Map<string, FlxStoreEntry>;
}

/** Entries kept at most (oldest dropped first). */
const MAX_ENTRIES = 300;

const g = globalThis as StoreGlobal & { document?: Document };

function store(): Map<string, FlxStoreEntry> {
  let s = g.__flxGqlStore;
  if (!s) {
    s = new Map();
    g.__flxGqlStore = s;
    seedFromPage(s);
  }
  return s;
}

/** Reads the server's preload block once (the navigator may have read it first; then it is gone). */
function seedFromPage(s: Map<string, FlxStoreEntry>): void {
  const el = g.document?.getElementById?.('flx-gql-preload');
  if (!el) return;
  try {
    const seed = JSON.parse(el.textContent || '{}') as Seed;
    const at = typeof seed.at === 'number' ? seed.at : Date.now();
    for (const [k, v] of Object.entries(seed.entries ?? {})) s.set(k, { data: v, at });
  } catch {
    /* a broken block only costs the preload */
  }
  el.remove();
}

/** JSON with object keys sorted; `undefined`, `null`, `{}` and `[]` as the whole value are `{}`. */
export function stableVariables(v: unknown): string {
  if (v === undefined || v === null) return '{}';
  if (Array.isArray(v) && v.length === 0) return '{}';
  if (typeof v === 'object' && !Array.isArray(v) && Object.keys(v).length === 0) return '{}';
  return JSON.stringify(sortKeys(v));
}

function sortKeys(v: unknown): unknown {
  if (Array.isArray(v)) return v.map(sortKeys);
  if (v && typeof v === 'object') {
    const out: Record<string, unknown> = {};
    for (const k of Object.keys(v as Record<string, unknown>).sort()) {
      const x = (v as Record<string, unknown>)[k];
      if (x !== undefined) out[k] = sortKeys(x);
    }
    return out;
  }
  return v;
}

export const flxStoreKey = (id: string, variables?: unknown): string => `${id}:${stableVariables(variables)}`;

export function flxStoreGet(key: string): FlxStoreEntry | undefined {
  return store().get(key);
}

export function flxStoreSet(key: string, data: unknown, at = Date.now()): void {
  const s = store();
  s.delete(key);
  s.set(key, { data, at });
  if (s.size > MAX_ENTRIES) {
    const first = s.keys().next().value;
    if (first !== undefined) s.delete(first);
  }
}

/** After a write: every stored result is still shown, but refreshed on its next use. */
export function flxStoreMarkStale(): void {
  for (const e of store().values()) e.at = 0;
}
