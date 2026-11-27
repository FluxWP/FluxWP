import { expect, test } from 'bun:test';
import { gettextPlural } from '../../../../packages/modules/src/gettext-plural';

test('React selects the same plural slots as the Arabic and Russian WordPress catalogs', () => {
  const ar = ['zero', 'one', 'two', 'few', 'many', 'other'];
  expect([0, 1, 2, 3, 11, 100, 103].map((n) => gettextPlural(ar, 'ar', n))).toEqual([
    'zero', 'one', 'two', 'few', 'many', 'other', 'few',
  ]);
  const ru = ['one', 'few', 'many'];
  expect([1, 2, 5, 11, 21, 22, 114].map((n) => gettextPlural(ru, 'ru', n))).toEqual([
    'one', 'few', 'many', 'many', 'one', 'few', 'many',
  ]);
});

test('single-form catalogs and French/Brazilian Portuguese zero are handled', () => {
  expect(gettextPlural(['all'], 'ja', 8)).toBe('all');
  expect(gettextPlural(['one', 'many'], 'fr', 0)).toBe('one');
  expect(gettextPlural(['one', 'many'], 'pt-br', 0)).toBe('one');
  expect(gettextPlural(['one', 'many'], 'en', 0)).toBe('many');
});
