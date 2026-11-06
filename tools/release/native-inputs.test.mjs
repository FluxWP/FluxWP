import { test } from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { execFileSync } from 'node:child_process';
import { nativeInputHash } from './native-inputs.mjs';

test('capture-only edits reuse native builds; app edits invalidate them', () => {
  const dir = mkdtempSync(join(tmpdir(), 'flux-native-inputs-'));
  const git = (...args) => execFileSync('git', args, {cwd: dir, stdio: 'pipe'});
  const commit = () => { git('add', '.'); git('-c', 'user.name=Test', '-c', 'user.email=test@example.invalid', 'commit', '-m', 'fixture'); };
  try {
    git('init');
    mkdirSync(join(dir, 'apps/mobile'), {recursive: true});
    mkdirSync(join(dir, 'tools/release'), {recursive: true});
    writeFileSync(join(dir, 'apps/mobile/app.json'), '{}');
    commit();
    const original = nativeInputHash('HEAD', dir);
    writeFileSync(join(dir, 'tools/release/capture.mjs'), '// capture changed');
    commit();
    assert.equal(nativeInputHash('HEAD', dir), original);
    writeFileSync(join(dir, 'apps/mobile/app.json'), '{"version":"2"}');
    commit();
    assert.notEqual(nativeInputHash('HEAD', dir), original);
    assert.throws(() => nativeInputHash('missing-ref', dir), /Cannot fingerprint/);
  } finally { rmSync(dir, {recursive: true, force: true}); }
});
