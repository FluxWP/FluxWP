import { describe, expect, it } from 'vitest';
import { countingFetch, inFlight, waitForIdle } from './net';

const later = <T,>(ms: number, v: T) => new Promise<T>((r) => setTimeout(() => r(v), ms));

describe('net', () => {
  it('counts requests in flight', async () => {
    const f = countingFetch((() => later(30, new Response('ok'))) as typeof fetch);
    const a = f('x');
    const b = f('y');
    expect(inFlight()).toBe(2);
    await Promise.all([a, b]);
    expect(inFlight()).toBe(0);
  });

  it('counts a failed request as finished', async () => {
    const f = countingFetch((() => Promise.reject(new Error('down'))) as typeof fetch);
    await expect(f('x')).rejects.toThrow('down');
    expect(inFlight()).toBe(0);
  });

  it('waits for the network to go quiet', async () => {
    const f = countingFetch((() => later(120, new Response('ok'))) as typeof fetch);
    const t0 = Date.now();
    void f('x');
    await waitForIdle({ quietMs: 50, minMs: 0, capMs: 2000 });
    expect(Date.now() - t0).toBeGreaterThanOrEqual(160);
    expect(inFlight()).toBe(0);
  });

  it('gives up at the cap', async () => {
    const f = countingFetch((() => later(1000, new Response('ok'))) as typeof fetch);
    const t0 = Date.now();
    const p = f('x');
    await waitForIdle({ quietMs: 50, minMs: 0, capMs: 150 });
    expect(Date.now() - t0).toBeLessThan(600);
    await p;
  });
});
