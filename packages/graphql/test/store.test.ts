import { describe, expect, it } from 'vitest';
import { flxStoreGet, flxStoreKey, flxStoreMarkStale, flxStoreSet, stableVariables } from '../src/store';

describe('page-wide store', () => {
  it('builds the same key as PHP (sorted keys, empty = {})', () => {
    expect(stableVariables(undefined)).toBe('{}');
    expect(stableVariables({})).toBe('{}');
    expect(stableVariables([])).toBe('{}');
    expect(stableVariables({ b: 1, a: { d: 'x/y', c: null }, z: undefined })).toBe('{"a":{"c":null,"d":"x/y"},"b":1}');
    expect(flxStoreKey('abc', { first: 5 })).toBe('abc:{"first":5}');
  });

  it('keeps results and marks them stale after a write', () => {
    flxStoreSet('k:{}', { v: 1 }, 123);
    expect(flxStoreGet('k:{}')).toEqual({ data: { v: 1 }, at: 123 });
    flxStoreMarkStale();
    expect(flxStoreGet('k:{}')?.at).toBe(0);
  });
});
