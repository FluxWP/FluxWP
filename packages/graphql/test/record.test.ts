import { describe, expect, it } from 'vitest';
import { flxStoreNote, flxStoreRecord } from '../src/store';

describe('flxStoreRecord', () => {
  it('records keys in first-use order, once each, until stopped', () => {
    flxStoreNote('before');
    const stop = flxStoreRecord();
    flxStoreNote('a:{}');
    flxStoreNote('b:{"x":1}');
    flxStoreNote('a:{}');
    expect(stop()).toEqual(['a:{}', 'b:{"x":1}']);
    flxStoreNote('after');
  });
  it('overlapping recordings each see every key', () => {
    const one = flxStoreRecord();
    flxStoreNote('a:{}');
    const two = flxStoreRecord();
    flxStoreNote('b:{}');
    expect(two()).toEqual(['b:{}']);
    expect(one()).toEqual(['a:{}', 'b:{}']);
  });
});
