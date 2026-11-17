// In-flight request counter. The site warm-up waits until the network goes quiet before it hides a screen.
type Fetch = typeof fetch;

let inflight = 0;
let quietSince = Date.now();
const listeners = new Set<() => void>();

const changed = () => listeners.forEach((fn) => fn());

/** Wraps the platform fetch so every API call (REST and GraphQL) is counted. */
export function countingFetch(inner: Fetch): Fetch {
  return (async (...args: Parameters<Fetch>) => {
    inflight++;
    changed();
    try {
      return await inner(...args);
    } finally {
      inflight = Math.max(0, inflight - 1);
      if (!inflight) quietSince = Date.now();
      changed();
    }
  }) as Fetch;
}

export const inFlight = () => inflight;

/**
 * Resolves once no request has been in flight for `quietMs`, or after `capMs` at most.
 * `minMs` keeps it from resolving before a screen had the chance to start its requests.
 */
export function waitForIdle({ quietMs = 350, capMs = 8000, minMs = 250 } = {}): Promise<void> {
  const start = Date.now();
  return new Promise((resolve) => {
    let timer: ReturnType<typeof setTimeout> | null = null;
    const done = () => {
      if (timer) clearTimeout(timer);
      listeners.delete(check);
      resolve();
    };
    const check = () => {
      if (timer) clearTimeout(timer);
      const now = Date.now();
      if (now - start >= capMs) return done();
      if (inflight === 0) {
        const wait = Math.max(quietMs - (now - quietSince), minMs - (now - start), 0);
        if (wait === 0) return done();
        timer = setTimeout(check, wait);
        return;
      }
      timer = setTimeout(check, capMs - (now - start));
    };
    listeners.add(check);
    check();
  });
}
