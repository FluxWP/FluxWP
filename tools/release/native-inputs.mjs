import { spawnSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { root } from './config.mjs';
export const nativePaths = ['apps/mobile', 'packages', 'brands', 'bun.lock', 'package.json', 'tools/fluxapp/brand.mjs', 'tools/fluxapp/brand-lib.cjs'];
export function nativeInputHash(ref = 'HEAD', repo = root) {
  const r = spawnSync('git', ['ls-tree', '-r', ref, '--', ...nativePaths], {cwd: repo, encoding: 'utf8'});
  if (r.status || !r.stdout.trim()) throw new Error('Cannot fingerprint native app inputs');
  return createHash('sha256').update(r.stdout).digest('hex');
}
