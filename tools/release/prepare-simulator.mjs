#!/usr/bin/env node
// Recover a native simulator build without rebuilding when only capture scripts change.
import { spawnSync } from 'node:child_process';
import { readdirSync, readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { join } from 'node:path';
import { createHash } from 'node:crypto';
import { root } from './config.mjs';
import { nativeInputHash } from './native-inputs.mjs';
const out = join(root, 'release-out/ios');
mkdirSync(out, {recursive: true});
const release = process.env.SIMULATOR_RELEASE;
if (release) {
  if (!/^fluxwp-native-qa-\d+\.\d+\.\d+$/.test(release)) throw new Error('Invalid native simulator cache tag');
  const r = spawnSync('gh', ['release','download',release,'--repo',process.env.GITHUB_REPOSITORY,'--pattern','*-simulator.zip','--pattern','native-build.json','--dir',out], {stdio:'inherit'});
  if (r.status) throw new Error('Cannot download native simulator cache');
}
const meta = JSON.parse(readFileSync(join(out,'native-build.json'),'utf8'));
if (meta.nativeInputHash !== nativeInputHash() || meta.brand !== process.env.FLUX_BRAND) throw new Error('Cached native app does not match the current app code/dependencies/brand');
const archives = readdirSync(out).filter(f=>f.endsWith('-simulator.zip'));
if (archives.length !== 1) throw new Error('Expected exactly one native simulator archive');
const archive = join(out, archives[0]);
if (createHash('sha256').update(readFileSync(archive)).digest('hex') !== meta.sha256) throw new Error('Native simulator archive hash mismatch');
const unpack = join(root,'release-out/native-app');
mkdirSync(unpack,{recursive:true});
const r = spawnSync('ditto',['-x','-k',archive,unpack],{stdio:'inherit'});
if (r.status) throw new Error('Cannot unpack native simulator app');
const apps = readdirSync(unpack).filter(f=>f.endsWith('.app'));
if (apps.length !== 1) throw new Error('Expected exactly one native app');
writeFileSync(join(out,'simulator.json'),JSON.stringify({app:join(unpack,apps[0]),nativeInputHash:meta.nativeInputHash,sourceCommit:meta.sourceCommit},null,2));
console.log('Verified native app input fingerprint and archive hash.');
