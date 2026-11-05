import { test } from 'node:test';
import assert from 'node:assert/strict';
import { releaseConfig, requireSecrets, preflight } from './config.mjs';

test('the real main brand resolves stable store identifiers', () => {
  assert.equal(releaseConfig({ platform: 'ios', destination: 'testflight' }).bundleId, 'com.fluxwp.app');
  assert.equal(releaseConfig({ platform: 'android', destination: 'internal' }).bundleId, 'com.fluxwp.app');
});
test('reject path traversal, shell expressions, unsupported destinations and invalid versions', () => {
  for (const brand of ['../flux', 'flux/../../', 'flux;echo bad', '$(id)', 'flux\nother']) {
    assert.throws(() => releaseConfig({ brand, platform: 'ios', destination: 'testflight' }));
  }
  assert.throws(() => releaseConfig({ platform: 'ios', destination: 'production' }));
  for (const version of ['v1.0.0', '1.0.0-beta.1', '1.0\nBAD=1']) {
    assert.throws(() => releaseConfig({ platform: 'ios', destination: 'testflight', version }));
  }
  for (const buildNumber of ['0', '-1', '1.1', '2100000001', '1\nBAD=1']) {
    assert.throws(() => releaseConfig({ platform: 'android', destination: 'internal', buildNumber }));
  }
});
test('missing credentials fail by name without leaking supplied values', () => {
  assert.throws(() => requireSecrets(['FOUND', 'MISSING'], { FOUND: 'never-log-me' }), e => e.message.includes('MISSING') && !e.message.includes('never-log-me'));
  assert.doesNotThrow(() => preflight(releaseConfig({ platform: 'ios', destination: 'simulator' }), {}));
  assert.throws(() => preflight(releaseConfig({ platform: 'ios', destination: 'testflight' }), {}), /APPLE_API_KEY/);
  assert.throws(() => preflight(releaseConfig({ platform: 'ios', destination: 'testflight' }), { APPLE_API_KEY: 'test', APPLE_API_ISSUER: 'test', APPLE_API_KEY_P8: 'test' }), /IOS_PROVISIONING_PROFILE/);
  assert.throws(() => preflight(releaseConfig({ platform: 'android', destination: 'apk' }), {}), /ANDROID_KEYSTORE/);
});
test('App Store submission promotes a selected TestFlight build and requires reviewed assets', () => {
  const credentials = { APPLE_API_KEY: 'test', APPLE_API_ISSUER: 'test', APPLE_API_KEY_P8: 'test' };
  assert.throws(() => preflight(releaseConfig({ platform: 'ios', destination: 'app-store' }), credentials), /already-tested/);
  const config = releaseConfig({ platform: 'ios', destination: 'app-store', buildNumber: '12' });
  assert.throws(() => preflight(config, credentials), /screenshot/);
  assert.throws(() => preflight(config, { ...credentials, ASSET_RUN_ID: '1234' }), /disclosures/);
  assert.doesNotThrow(() => preflight(config, { ...credentials, ASSET_RUN_ID: '1234', STORE_DISCLOSURES_REVIEWED: 'true' }));
});
