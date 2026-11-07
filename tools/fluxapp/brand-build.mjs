#!/usr/bin/env bun
// bun run brand:build <slug> [--platform ios|android|all] [--profile production|preview] [--prepare]
// Applies brands/<slug>/brand.json to the desktop Tauri config and the Expo app, then starts EAS Build.
// --prepare writes the configs and prints the eas command (no EXPO_TOKEN, no upload).
// EXPO_TOKEN is read from the environment and is never written to the repo or to argv.
import { spawnSync } from 'node:child_process';
import { existsSync, readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const require = createRequire(import.meta.url);
const { profileNames } = require('./brand-lib.cjs');

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');

function arg(name, fallback) {
  const i = process.argv.indexOf(`--${name}`);
  return i >= 0 && process.argv[i + 1] && !process.argv[i + 1].startsWith('--') ? process.argv[i + 1] : fallback;
}

const slug = process.argv.slice(2).find((a) => !a.startsWith('--'));
const platform = arg('platform', 'all');
const kind = arg('profile', 'production');
const prepare = process.argv.includes('--prepare');

if (!slug || !['all', 'ios', 'android'].includes(platform) || !['production', 'preview'].includes(kind)) {
  console.error('Usage: bun run brand:build <slug> [--platform ios|android|all] [--profile production|preview] [--prepare]');
  process.exit(1);
}

const names = profileNames(slug);
const profile = kind === 'preview' ? names.preview : names.production;
const easPath = join(root, 'apps/mobile/eas.json');
const eas = existsSync(easPath) ? JSON.parse(readFileSync(easPath, 'utf8')) : { build: {} };
if (!eas.build?.[profile]) {
  console.error(`[brand] eas profile "${profile}" is missing. Run: bun run brand:new ${slug}`);
  process.exit(1);
}

const env = { ...process.env, FLUX_BRAND: slug };
const run = (cmd, args, cwd) => {
  const r = spawnSync(cmd, args, { cwd, env, stdio: 'inherit' });
  if (r.status !== 0) process.exit(r.status ?? 1);
};

run('bun', ['tools/fluxapp/brand.mjs'], root);
run('node', ['apps/mobile/scripts/brand.cjs'], root);

const easArgs = ['eas-cli', 'build', '--non-interactive', '--platform', platform, '--profile', profile];
console.log(`[brand] ${slug} → eas build --profile ${profile} --platform ${platform}`);
if (prepare) {
  console.log(`[brand] prepared. When EXPO_TOKEN is set: bunx ${easArgs.join(' ')}`);
  process.exit(0);
}
if (!process.env.EXPO_TOKEN) {
  console.error('[brand] EXPO_TOKEN is not set. Use --prepare to only write configs, or export EXPO_TOKEN (it is not stored in the repo).');
  process.exit(1);
}
run('bunx', easArgs, join(root, 'apps/mobile'));
