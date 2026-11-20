// Live updates over WebSocket (Docker sites with the FluxWP realtime service). Optional: config
// `transport.realtime` is present only when the site's realtime server is healthy; without it nothing connects
// and the app polls as before. Signals carry no data: each one names query keys (`inv`) to refetch.
//
// Protocol (docker/images/realtime/server.ts): connect, send {"auth": ticket, "vis": bool} first (ticket from
// POST flx-realtime/v1/ticket, 60 s, single use), then {"vis": bool} when the screen goes to the background or
// back, so the site does not send a phone push for something already on screen.
import { useEffect, useSyncExternalStore } from 'react';
import type { QueryClient } from '@tanstack/react-query';
import { platform } from '@fluxwp/core';
import { useScope } from './scope';
import { useConfig } from './hooks';

export interface RealtimeMessage {
  v?: number;
  t: string;
  inv?: string[];
  d?: Record<string, unknown>;
  at?: number;
}

type WsLike = Pick<WebSocket, 'send' | 'close' | 'readyState'> & {
  onopen: ((e: unknown) => void) | null;
  onmessage: ((e: { data: unknown }) => void) | null;
  onclose: ((e: { code: number }) => void) | null;
  onerror: ((e: unknown) => void) | null;
};

export interface RealtimeOptions {
  url: string;
  /** Fresh single-use ticket for every (re)connect. */
  ticket: () => Promise<string>;
  onMessage: (m: RealtimeMessage) => void;
  onLive?: (live: boolean) => void;
  visible?: () => boolean;
  subscribeVisibility?: (fn: (visible: boolean) => void) => () => void;
  socket?: (url: string) => WsLike;
  /** Test hook; default setTimeout. */
  later?: (fn: () => void, ms: number) => unknown;
  cancel?: (h: unknown) => void;
}

/** Close codes from the server. */
export const RT_CLOSE = { authTimeout: 4000, expired: 4001, badTicket: 4003, tooMany: 4008, revoked: 4009 } as const;

/** Backoff after `fails` failures in a row: 1, 2, 4 … 30 s with jitter; after 6 failures 10 minutes. */
export function realtimeDelay(fails: number, rnd = Math.random()): number {
  if (fails >= 6) return 10 * 60_000;
  return Math.min(30_000, 1000 * 2 ** Math.max(0, fails - 1)) * (0.8 + rnd * 0.4);
}

/** One socket with reconnects. Never throws; failures only delay the next try (the app keeps polling). */
export class RealtimeConnection {
  private ws: WsLike | null = null;
  private timer: unknown = null;
  private fails = 0;
  private stopped = true;
  private live = false;
  private unVis: (() => void) | null = null;

  constructor(private o: RealtimeOptions) {}

  start() {
    if (!this.stopped) return;
    this.stopped = false;
    this.unVis = this.o.subscribeVisibility?.((v) => this.sendVis(v)) ?? null;
    void this.connect();
  }

  stop() {
    this.stopped = true;
    this.unVis?.();
    this.unVis = null;
    if (this.timer !== null) (this.o.cancel ?? clearTimeout)(this.timer as ReturnType<typeof setTimeout>);
    this.timer = null;
    const ws = this.ws;
    this.ws = null;
    try {
      ws?.close(1000);
    } catch {
      /* already closed */
    }
    this.setLive(false);
  }

  private setLive(v: boolean) {
    if (this.live === v) return;
    this.live = v;
    this.o.onLive?.(v);
  }

  private sendVis(v: boolean) {
    if (this.live && this.ws?.readyState === 1) this.ws.send(JSON.stringify({ vis: v }));
  }

  private retry() {
    if (this.stopped) return;
    this.fails++;
    this.timer = (this.o.later ?? setTimeout)(() => {
      this.timer = null;
      void this.connect();
    }, realtimeDelay(this.fails));
  }

  private async connect() {
    if (this.stopped) return;
    let ticket: string;
    try {
      ticket = await this.o.ticket();
    } catch {
      return this.retry();
    }
    if (this.stopped || !ticket) return this.stopped ? undefined : this.retry();
    let ws: WsLike;
    try {
      ws = this.o.socket ? this.o.socket(this.o.url) : (new WebSocket(this.o.url) as unknown as WsLike);
    } catch {
      return this.retry();
    }
    this.ws = ws;
    ws.onopen = () => ws.send(JSON.stringify({ auth: ticket, vis: this.o.visible?.() ?? true }));
    ws.onmessage = (e) => {
      let m: RealtimeMessage;
      try {
        m = JSON.parse(String(e.data));
      } catch {
        return;
      }
      if (!m || typeof m.t !== 'string') return;
      if (m.t === 'hello') {
        this.fails = 0;
        this.setLive(true);
        return;
      }
      if (m.t === 'pong') return;
      this.o.onMessage(m);
    };
    ws.onerror = () => undefined;
    ws.onclose = (e) => {
      if (this.ws !== ws) return;
      this.ws = null;
      const wasLive = this.live;
      this.setLive(false);
      if (this.stopped) return;
      // A planned renewal or a lost connection after a good session: reconnect soon, without counting a failure.
      if (wasLive && e.code !== RT_CLOSE.revoked && e.code !== RT_CLOSE.tooMany) this.fails = 0;
      if (e.code === RT_CLOSE.revoked) this.fails = Math.max(this.fails, 5); // Signed out: back off hard.
      this.retry();
    };
  }
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

interface FeedImpl extends RealtimeFeed {
  emit(m: RealtimeMessage): void;
  set(v: boolean): void;
}

const feeds = new Map<string, FeedImpl>();

function makeFeed(): FeedImpl {
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

/** The account's feed (created on first use). */
export function realtimeFeed(accountId: string): RealtimeFeed {
  return feedImpl(accountId);
}
function feedImpl(accountId: string): FeedImpl {
  let f = feeds.get(accountId);
  if (!f) feeds.set(accountId, (f = makeFeed()));
  return f;
}

const listeners = new Set<() => void>();
const subscribeLive = (l: () => void) => {
  listeners.add(l);
  return () => listeners.delete(l);
};
function setAccountLive(id: string, v: boolean) {
  const f = feedImpl(id);
  if (v === f.live()) return;
  f.set(v);
  listeners.forEach((l) => l());
}

/** True while this account has a live realtime connection. */
export function useRealtimeLive(): boolean {
  const { account } = useScope();
  return useSyncExternalStore(subscribeLive, () => feedImpl(account.id).live(), () => false);
}

/** Polling interval to use: `ms` normally, a 120 s safety net while realtime is connected. */
export function useLivePoll(ms: number): number {
  return useRealtimeLive() ? Math.max(ms, 120_000) : ms;
}

/**
 * `inv` is a react-query key prefix. `["gql", "FlxChatbot"]` is special: it reloads every GraphQL operation of
 * this account whose name starts with "FlxChatbot" (their keys are [account, "gql", name, variables]).
 */
export function invalidateFor(client: QueryClient, accountId: string, inv: unknown): void {
  if (!Array.isArray(inv) || !inv.length) return;
  if (inv[0] === 'gql') {
    const prefix = typeof inv[1] === 'string' ? inv[1] : '';
    void client.invalidateQueries({
      predicate: (q) => q.queryKey[0] === accountId && q.queryKey[1] === 'gql' && String(q.queryKey[2] ?? '').startsWith(prefix),
    });
    return;
  }
  void client.invalidateQueries({ queryKey: inv });
}

/**
 * Connects this account's realtime socket while mounted (render once per account, e.g. in the shell).
 * Every signal refetches the queries under its `inv` key; `onMessage` sees the raw signal too.
 */
export function useRealtime(onMessage?: (m: RealtimeMessage) => void) {
  const { api, client, account } = useScope();
  const rt = useConfig().transport?.realtime;
  const url = rt?.url ?? '';
  useEffect(() => {
    if (!url || account.authKind === 'demo' || typeof WebSocket === 'undefined') return;
    let vis: { visible(): boolean; subscribe(fn: (v: boolean) => void): () => void } | undefined;
    try {
      vis = platform().visibility;
    } catch {
      vis = undefined;
    }
    const c = new RealtimeConnection({
      url,
      ticket: async () => (await api<{ ticket: string }>('flx-realtime/v1/ticket', { method: 'POST', body: {} })).ticket,
      onMessage: (m) => {
        invalidateFor(client, account.id, m.inv);
        feedImpl(account.id).emit(m);
        onMessage?.(m);
      },
      onLive: (v) => setAccountLive(account.id, v),
      visible: vis ? () => vis!.visible() : undefined,
      subscribeVisibility: vis ? (fn) => vis!.subscribe(fn) : undefined,
    });
    c.start();
    return () => c.stop();
  }, [url, api, client, account.id, account.authKind]); // eslint-disable-line react-hooks/exhaustive-deps
}
