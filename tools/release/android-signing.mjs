#!/usr/bin/env node
// Apply upload-key signing to Expo's generated Gradle project. Generated native folders stay ignored.
import { readFileSync, writeFileSync } from 'node:fs';
const file = new URL('../../apps/mobile/android/app/build.gradle', import.meta.url);
let text = readFileSync(file, 'utf8');
const signing = `
        release {
            storeType 'PKCS12'
            storeFile file(findProperty('FLUX_UPLOAD_STORE_FILE'))
            storePassword findProperty('FLUX_UPLOAD_STORE_PASSWORD')
            keyAlias findProperty('FLUX_UPLOAD_KEY_ALIAS')
            keyPassword findProperty('FLUX_UPLOAD_KEY_PASSWORD')
        }
`;
if (!/signingConfigs\s*\{/.test(text) || !/signingConfig\s+signingConfigs\.debug/.test(text)) {
  throw new Error('Expo Android signing template changed; refusing to produce an unsigned/debug-signed release.');
}
text = text.replace(/signingConfigs\s*\{/, `signingConfigs {${signing}`);
// Only replace release's signing config. Debug must keep its generated development key.
const release = /(buildTypes\s*\{[\s\S]*?release\s*\{[\s\S]*?)signingConfig\s+signingConfigs\.debug/;
if (!release.test(text)) throw new Error('Cannot identify Android release signing config');
text = text.replace(release, '$1signingConfig signingConfigs.release');
writeFileSync(file, text);
console.log('Android release uses the configured upload keystore.');
