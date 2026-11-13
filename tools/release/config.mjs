#!/usr/bin/env node
// Shared, dependency-free release input validation. Never interpolate dispatch inputs into a shell.
import { readFileSync, existsSync, appendFileSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

export const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
export const destinations = {
  ios: ['simulator', 'ipa', 'testflight', 'app-store', 'store-screenshots'],
  android: ['apk', 'aab', 'internal', 'alpha', 'beta', 'production'],
};

export function releaseConfig({ brand = 'flux', platform, destination, version = '', buildNumber = '' }, repo = root) {
  if (!/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(brand)) throw new Error('Invalid brand slug');
  if (!destinations[platform]?.includes(destination)) throw new Error(`Invalid ${platform} destination`);
  const file = resolve(repo, 'brands', brand, 'brand.json');
  if (!existsSync(file)) throw new Error(`Missing brands/${brand}/brand.json`);
  const b = JSON.parse(readFileSync(file, 'utf8'));
  if (typeof b.name !== 'string' || /[\r\n]/.test(b.name)) throw new Error('Invalid brand name');
  const pkg = JSON.parse(readFileSync(resolve(repo, 'apps/mobile/package.json'), 'utf8'));
  version ||= pkg.version;
  if (!/^\d+\.\d+\.\d+$/.test(version)) throw new Error('Store version must be major.minor.patch');
  if (buildNumber && (!/^[1-9]\d*$/.test(String(buildNumber)) || Number(buildNumber) > 2_100_000_000)) {
    throw new Error('Build number must be a positive integer <= 2100000000');
  }
  const bundleId = platform === 'ios' ? b.ios?.bundleIdentifier || b.bundleId : b.android?.package || b.ios?.bundleIdentifier || b.bundleId;
  if (!/^[A-Za-z][\w]*(?:\.[A-Za-z][\w]*)+$/.test(bundleId)) throw new Error('Invalid bundle identifier');
  return { brand, name: b.name, platform, destination, version, bundleId, buildNumber: String(buildNumber) };
}

export function requireSecrets(names, env = process.env) {
  const missing = names.filter(name => !env[name]?.trim());
  if (missing.length) throw new Error(`Missing release credentials: ${missing.join(', ')}. See docs/fluxapp/APP-RELEASE.md`);
}

export function preflight(config, env = process.env) {
  const publishesMetadata = ['app-store', 'store-screenshots'].includes(config.destination) || (config.platform === 'android' && !['apk', 'aab'].includes(config.destination));
  if (publishesMetadata && config.brand !== 'flux') throw new Error('Store metadata is configured only for the flux brand');
  if (config.platform === 'android' && env.PLAY_RELEASE_STATUS && !['draft', 'completed'].includes(env.PLAY_RELEASE_STATUS)) throw new Error('Invalid Google Play release status');
  if (config.platform === 'ios' && config.destination !== 'simulator') {
    requireSecrets(['APPLE_API_ISSUER', 'APPLE_API_KEY', 'APPLE_API_KEY_P8'], env);
    if (config.destination === 'app-store') {
      if (!config.buildNumber) throw new Error('Specify the already-tested TestFlight build number for App Store submission');
      if (!/^[1-9]\d*$/.test(env.ASSET_RUN_ID || '')) throw new Error('Specify the successful native screenshot workflow run ID');
      if (env.STORE_DISCLOSURES_REVIEWED !== 'true') throw new Error('Complete store disclosures before submission');
    } else if (config.destination === 'store-screenshots') {
      // Only replaces the screenshots of the editable App Store version: no build, no metadata, no review.
      if (!/^[1-9]\d*$/.test(env.ASSET_RUN_ID || '')) throw new Error('Specify the successful native screenshot workflow run ID');
    } else requireSecrets(['APPLE_TEAM_ID', 'IOS_DISTRIBUTION_CERTIFICATE', 'IOS_DISTRIBUTION_CERTIFICATE_PASSWORD', 'IOS_PROVISIONING_PROFILE'], env);
  }
  if (config.platform === 'android') {
    requireSecrets(['ANDROID_KEYSTORE', 'ANDROID_KEYSTORE_PASSWORD', 'ANDROID_KEY_ALIAS', 'ANDROID_KEY_PASSWORD'], env);
    if (!['apk', 'aab'].includes(config.destination)) requireSecrets(['GOOGLE_PLAY_SERVICE_ACCOUNT_JSON'], env);
  }
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  try {
    const c = releaseConfig({ brand: process.env.FLUX_BRAND || 'flux', platform: process.env.RELEASE_PLATFORM, destination: process.env.RELEASE_DESTINATION, version: process.env.FLUX_APP_VERSION, buildNumber: process.env.FLUX_BUILD_NUMBER });
    preflight(c);
    if (process.env.GITHUB_OUTPUT) appendFileSync(process.env.GITHUB_OUTPUT, Object.entries(c).map(([k,v]) => `${k}=${v}`).join('\n') + '\n');
    if (process.env.GITHUB_ENV) appendFileSync(process.env.GITHUB_ENV, `FLUX_APP_VERSION=${c.version}\nFLUX_BUNDLE_IDENTIFIER=${c.bundleId}\n`);
    console.log(`Release preflight: ${c.name} ${c.version} / ${c.bundleId} / ${c.platform}:${c.destination}`);
  } catch (e) { console.error(`::error::${e.message}`); process.exitCode = 1; }
}
