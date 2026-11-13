#!/usr/bin/env node
// Secret values stay in environment variables / mode-0600 temporary files, never command arguments.
import { mkdirSync, writeFileSync, appendFileSync, mkdtempSync } from 'node:fs';
import { join } from 'node:path';
import { tmpdir } from 'node:os';
import { requireSecrets } from './config.mjs';

const dir = mkdtempSync(join(process.env.RUNNER_TEMP || tmpdir(), 'fluxwp-release-'));
mkdirSync(dir, { recursive: true, mode: 0o700 });
const values = { FLUX_RELEASE_TEMP: dir };
if (!process.env.GITHUB_ENV) throw new Error('GITHUB_ENV is required');
appendFileSync(process.env.GITHUB_ENV, `FLUX_RELEASE_TEMP=${dir}\n`);
function save(name, filename, output, base64 = false) {
  requireSecrets([name]);
  const file = join(dir, filename);
  const data = base64 ? Buffer.from(process.env[name], 'base64') : process.env[name];
  if (!data.length) throw new Error(`Empty ${name}`);
  writeFileSync(file, data, { mode: 0o600 });
  values[output] = file;
}
if (process.env.RELEASE_PLATFORM === 'ios') {
  if (!['app-store', 'store-screenshots'].includes(process.env.RELEASE_DESTINATION)) {
    save('IOS_DISTRIBUTION_CERTIFICATE', 'distribution.p12', 'IOS_CERTIFICATE_PATH', true);
    save('IOS_PROVISIONING_PROFILE', 'profile.mobileprovision', 'IOS_PROFILE_PATH', true);
  }
  save('APPLE_API_KEY_P8', `AuthKey_${process.env.APPLE_API_KEY}.p8`, 'ASC_KEY_FILE');
} else if (process.env.RELEASE_PLATFORM === 'android') {
  save('ANDROID_KEYSTORE', 'upload.keystore', 'ANDROID_KEYSTORE_PATH', true);
  if (!['apk', 'aab'].includes(process.env.RELEASE_DESTINATION)) save('GOOGLE_PLAY_SERVICE_ACCOUNT_JSON', 'google-play.json', 'GOOGLE_PLAY_JSON_PATH');
  // Gradle reads these through environment-backed project properties, so passwords are not CLI flags.
  Object.assign(values, {
    ORG_GRADLE_PROJECT_FLUX_UPLOAD_STORE_FILE: values.ANDROID_KEYSTORE_PATH,
    ORG_GRADLE_PROJECT_FLUX_UPLOAD_STORE_PASSWORD: process.env.ANDROID_KEYSTORE_PASSWORD,
    ORG_GRADLE_PROJECT_FLUX_UPLOAD_KEY_ALIAS: process.env.ANDROID_KEY_ALIAS,
    ORG_GRADLE_PROJECT_FLUX_UPLOAD_KEY_PASSWORD: process.env.ANDROID_KEY_PASSWORD,
  });
} else throw new Error('Unsupported RELEASE_PLATFORM');
for (const [key, value] of Object.entries(values)) {
  if (/\r|\n/.test(value)) throw new Error(`Multiline ${key} is not supported`);
  appendFileSync(process.env.GITHUB_ENV, `${key}=${value}\n`);
}
console.log('Release credential files prepared (temporary, mode 0600).');
