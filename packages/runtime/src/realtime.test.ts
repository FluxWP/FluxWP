import { describe, expect, it } from 'vitest';
import { RT_CLOSE, RealtimeConnection, realtimeDelay, type RealtimeMessage } from './realtime';

class FakeWs {
  readyState = 0;
  sent: unknown[] = [];
  onopen: ((e: unknown) => void) | null = null;
  onmessage: ((e: { data: unknown }) => void) | null = null;
  onclose: ((e: { code: number }) => void) | null = null;
  onerror: ((e: unknown) => void) | null = null;
  send(s: string) {
    this.sent.push(JSON.parse(s));
  }
  close(code = 1000) {
    this.readyState = 3;
    this.onclose?.({ code });
  }
  open() {
    this.readyState = 1;
    this.onopen?.({});
  }
  msg(m: object) {
    this.onmessage?.({ data: JSON.stringify(m) });
  }
}

function setup(ticket: () => Promise<string> = async () => 'tk') {
  const sockets: FakeWs[] = [];
  const timers: { fn: () => void; ms: number }[] = [];
  const got: RealtimeMessage[] = [];
  const live: boolean[] = [];
  let visFn: ((v: boolean) => void) | null = null;
  const c = new RealtimeConnection({
    url: 'wss://site/flx-rt',
    ticket,
    onMessage: (m) => got.push(m),
    onLive: (v) => live.push(v),
    visible: () => true,
    subscribeVisibility: (fn) => {
      visFn = fn;
      return () => (visFn = null);
    },
    socket: () => {
      const s = new FakeWs();
      sockets.push(s);
      return s as never;
    },
    later: (fn, ms) => timers.push({ fn, ms }),
    cancel: () => undefined,
  });
  return { c, sockets, timers, got, live, vis: (v: boolean) => visFn?.(v) };
}

const tick = () => new Promise((r) => setTimeout(r, 0));

describe('realtime', () => {
  it('authenticates first, goes live on hello, passes signals, reports visibility', async () => {
    const t = setup();
    t.c.start();
    await tick();
    const ws = t.sockets[0];
    ws.open();
    expect(ws.sent[0]).toEqual({ auth: 'tk', vis: true });
    ws.msg({ t: 'hello', uid: 1 });
    expect(t.live).toEqual([true]);
    ws.msg({ v: 1, t: 'app.event', inv: ['sync'] });
    ws.msg({ t: 'pong' });
    expect(t.got).toEqual([{ v: 1, t: 'app.event', inv: ['sync'] }]);
    t.vis(false);
    expect(ws.sent[1]).toEqual({ vis: false });
    t.c.stop();
    expect(t.live).toEqual([true, false]);
  });

  it('reconnects with backoff and a fresh ticket; revoke backs off hard', async () => {
    let n = 0;
    const t = setup(async () => 'tk' + ++n);
    t.c.start();
    await tick();
    t.sockets[0].open();
    t.sockets[0].msg({ t: 'hello' });
    t.sockets[0].close(1006); // lost after a good session
    expect(t.timers.length).toBe(1);
    expect(t.timers[0].ms).toBeLessThanOrEqual(1200);
    t.timers[0].fn();
    await tick();
    t.sockets[1].open();
    expect(t.sockets[1].sent[0]).toEqual({ auth: 'tk2', vis: true });
    t.sockets[1].close(RT_CLOSE.revoked);
    expect(t.timers[1].ms).toBeGreaterThanOrEqual(10 * 60_000);
    t.c.stop();
  });

  it('ticket failure retries instead of throwing', async () => {
    const t = setup(async () => {
      throw new Error('401');
    });
    t.c.start();
    await tick();
    expect(t.sockets.length).toBe(0);
    expect(t.timers.length).toBe(1);
    t.c.stop();
  });

  it('delay grows to 30 s, then 10 min', () => {
    expect(realtimeDelay(1, 0.5)).toBe(1000);
    expect(realtimeDelay(3, 0.5)).toBe(4000);
    expect(realtimeDelay(5, 0.5)).toBe(16000);
    expect(realtimeDelay(6, 0.5)).toBe(600000);
  });
});
