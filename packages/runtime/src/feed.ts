// Realtime feed: connected flag + topic listeners per account. No React and no app imports, so shared engines
// (tasks, WhatsApp) can depend on it without pulling in the whole realtime hook chain.
export interface RealtimeMessage {
  v?: number;
  t: string;
  inv?: string[];
  d?: Record<string, unknown>;
  at?: number;
}

/**
 * Per-account feed: the connected flag plus topic listeners. Engines and screens that do not use react-query
 * (WhatsApp, Tasks, the chatbot thread) subscribe here and sync at once on a signal. The panel hands the same
 * feed to module screens through the module host; wp-admin pages have none and keep polling.
 */
export interface RealtimeFeed {
  /** True while the account has a live connection (polling can fall back to a slow safety net). */
  live(): boolean;
  /** Signals whose topic starts with `prefix` (e.g. "chat."). Returns an unsubscribe. */
  on(prefix: string, fn: (m: RealtimeMessage) => void): () => void;
  /** Connected / disconnected changes. Returns an unsubscribe. */
  onLive(fn: (live: boolean) => void): () => void;
}

/** Slowest polling while a feed is live. */
export const REALTIME_SAFETY_MS = 120_000;

/** Polling delay for an engine: `ms` normally, a slow safety net while `feed` is connected. */
export function feedPoll(feed: RealtimeFeed | undefined, ms: number): number {
  return feed?.live() ? Math.max(ms, REALTIME_SAFETY_MS) : ms;
}

export interface FeedImpl extends RealtimeFeed {
  emit(m: RealtimeMessage): void;
  set(v: boolean): void;
}


export function makeFeed(): FeedImpl {
  let live = false;
  const topics = new Set<{ prefix: string; fn: (m: RealtimeMessage) => void }>();
  const lives = new Set<(v: boolean) => void>();
  return {
    live: () => live,
    on(prefix, fn) {
      const e = { prefix, fn };
      topics.add(e);
      return () => void topics.delete(e);
    },
    onLive(fn) {
      lives.add(fn);
      return () => void lives.delete(fn);
    },
    emit(m) {
      for (const e of [...topics])
        if (m.t.startsWith(e.prefix)) {
          try {
            e.fn(m);
          } catch {
            /* a listener must not break the others */
          }
        }
    },
    set(v) {
      if (v === live) return;
      live = v;
      for (const fn of [...lives]) fn(v);
    },
  };
}

