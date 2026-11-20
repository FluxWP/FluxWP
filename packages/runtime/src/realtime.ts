// Live updates over WebSocket (Docker sites with the FluxWP realtime service). Optional: config
// `transport.realtime` is present only when the site's realtime server is healthy; without it nothing connects
// and the app polls as before. Signals carry no data: each one names query keys (`inv`) to refetch.
//
// Protocol (docker/images/realtime/server.ts): connect, send {"auth": ticket, "vis": bool} first (ticket from
// POST flx-realtime/v1/ticket, 60 s, single use), then {"vis": bool} when the screen goes to the background or
// back, so the site does not send a phone push for something already on screen.
import { useEffect, useSyncExternalStore } from 'react';
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

/* Per-account "connected" flag, read by polling hooks to slow down. */
const liveAccounts = new Set<string>();
const listeners = new Set<() => void>();
function setAccountLive(id: string, v: boolean) {
  if (v === liveAccounts.has(id)) return;
  if (v) liveAccounts.add(id);
  else liveAccounts.delete(id);
  listeners.forEach((l) => l());
}
const subscribeLive = (l: () => void) => {
  listeners.add(l);
  return () => listeners.delete(l);
};

/** True while this account has a live realtime connection. */
export function useRealtimeLive(): boolean {
  const { account } = useScope();
  return useSyncExternalStore(subscribeLive, () => liveAccounts.has(account.id), () => false);
}

/** Polling interval to use: `ms` normally, a 120 s safety net while realtime is connected. */
export function useLivePoll(ms: number): number {
  return useRealtimeLive() ? Math.max(ms, 120_000) : ms;
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
        if (Array.isArray(m.inv) && m.inv.length) void client.invalidateQueries({ queryKey: m.inv });
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
