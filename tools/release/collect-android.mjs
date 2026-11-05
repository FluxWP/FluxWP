#!/usr/bin/env node
import { readdirSync, mkdirSync, copyFileSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { spawnSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { readFileSync } from 'node:fs';
import { root } from './config.mjs';
const apk = process.env.RELEASE_DESTINATION === 'apk';
const source = join(root, 'apps/mobile/android/app/build/outputs', apk ? 'apk/release' : 'bundle/release');
const files = readdirSync(source).filter(f => f.endsWith(apk ? '.apk' : '.aab'));
if (files.length !== 1) throw new Error('Expected exactly one release binary');
const file = join(source, files[0]);
if (apk) {
  const tools = join(process.env.ANDROID_HOME, 'build-tools');
  const version = readdirSync(tools).sort((a,b) => a.localeCompare(b, undefined, { numeric: true })).at(-1);
  const verify = spawnSync(join(tools, version, 'apksigner'), ['verify', '--verbose', file], { stdio: 'inherit' });
  if (verify.status) throw new Error('APK signature verification failed');
} else {
  const verify = spawnSync('jarsigner', ['-verify', file], { encoding: 'utf8' });
  if (verify.status || !/jar verified/i.test(verify.stdout)) throw new Error('AAB signature verification failed');
}
const out = join(root, 'release-out/android');
mkdirSync(out, { recursive: true });
const name = `fluxwp-${process.env.FLUX_BRAND}-${process.env.FLUX_APP_VERSION}-${process.env.FLUX_BUILD_NUMBER}.${apk ? 'apk' : 'aab'}`;
copyFileSync(file, join(out, name));
writeFileSync(join(out, `${name}.sha256`), `${createHash('sha256').update(readFileSync(file)).digest('hex')}  ${name}\n`);
writeFileSync(join(out, 'release.json'), JSON.stringify({bundle_id: process.env.FLUX_BUNDLE_IDENTIFIER, version: process.env.FLUX_APP_VERSION, build_number: process.env.FLUX_BUILD_NUMBER, destination: process.env.RELEASE_DESTINATION, commit: process.env.GITHUB_SHA}, null, 2));
