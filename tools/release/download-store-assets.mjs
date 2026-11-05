#!/usr/bin/env node
// Store submissions must use screenshots from the same commit and native asset workflow.
import { spawnSync } from 'node:child_process';
import { appendFileSync, readFileSync, existsSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { join } from 'node:path';
import { root } from './config.mjs';
const id = process.env.ASSET_RUN_ID;
if (!/^[1-9]\d*$/.test(id || '')) throw new Error('Invalid screenshots_run_id');
function gh(args) {
  const r = spawnSync('gh', args, { encoding: 'utf8' });
  if (r.status) throw new Error('Cannot download/validate native screenshot artifacts');
  return r.stdout;
}
const run = JSON.parse(gh(['api', `repos/${process.env.GITHUB_REPOSITORY}/actions/runs/${id}`]));
if (run.path !== '.github/workflows/mobile-store-assets.yml' || run.conclusion !== 'success' || run.head_sha !== process.env.GITHUB_SHA) {
  throw new Error('Screenshots must come from a successful App store assets run at the release commit');
}
const dest = join(root, 'release-out/store-assets');
gh(['run', 'download', id, '--repo', process.env.GITHUB_REPOSITORY, '--name', 'appstore-screenshots', '--dir', dest]);
const manifest = JSON.parse(readFileSync(join(dest, 'manifest.json'), 'utf8'));
if (manifest.commit !== process.env.GITHUB_SHA || manifest.brand !== process.env.FLUX_BRAND || manifest.native !== true) throw new Error('Screenshot manifest does not match this release');
if (manifest.files?.length !== 20 || !manifest.files.every(f => {
  if (typeof f.path !== 'string' || !/^ios\/(tr|en-US)\/(iphone|ipad)-[a-z0-9-]+\.png$/.test(f.path) || !existsSync(join(dest, f.path))) return false;
  const data = readFileSync(join(dest, f.path));
  return data.length > 24 && data.subarray(0, 8).equals(Buffer.from([137,80,78,71,13,10,26,10])) && createHash('sha256').update(data).digest('hex') === f.sha256 && data.readUInt32BE(16) === f.width && data.readUInt32BE(20) === f.height;
})) throw new Error('Native screenshot bundle is incomplete');
appendFileSync(process.env.GITHUB_ENV, `IOS_SCREENSHOTS_PATH=${join(dest, 'ios')}\n`);
console.log('Verified native store screenshots for this commit.');
