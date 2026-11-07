import { test } from 'node:test';
import assert from 'node:assert/strict';
import { androidVersionCode } from './android-version-code.mjs';

test('initial Android codes leave headroom and increase for subsequent builds', () => {
  const now = Date.UTC(2026, 10, 6);
  const first = androidVersionCode(now);
  assert.ok(first > 1 && first < 100_000_000);
  assert.equal(androidVersionCode(now + 1000), first + 1);
  for (const invalid of [0, NaN, Infinity, Date.UTC(2100, 0, 1)]) assert.throws(() => androidVersionCode(invalid));
});
