// QA uses an already-built AAB; the temporary APK is never a store release.
import { execFileSync } from 'node:child_process';
import { readFileSync, writeFileSync, mkdirSync, readdirSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { nativeInputHash } from './native-inputs.mjs';

const runId = process.env.ANDROID_BUILD_RUN_ID;
const repo = process.env.GITHUB_REPOSITORY;
if (!/^[1-9]\d*$/.test(runId || '') || repo !== 'FluxWP/FluxWP') throw Error('Invalid Android QA source');
const gh = args => execFileSync('gh', args, { encoding: 'utf8' });
const run = JSON.parse(gh(['api', `repos/${repo}/actions/runs/${runId}`]));
if (run.path !== '.github/workflows/mobile-release-android.yml' || run.conclusion !== 'success') throw Error('A successful Android release build is required');
if (nativeInputHash(run.head_sha) !== nativeInputHash()) throw Error('Android application inputs changed since this build');
const out = 'release-out/android-qa';
mkdirSync(out, { recursive: true });
gh(['run', 'download', runId, '--repo', repo, '--name', `android-flux-aab-${runId}`, '--dir', out]);
const release = JSON.parse(readFileSync(`${out}/release.json`, 'utf8'));
if (release.bundle_id !== 'com.fluxwp.app' || release.destination !== 'aab' || release.commit !== run.head_sha) throw Error('Android artifact provenance mismatch');
const bundles = readdirSync(out).filter(f => f.endsWith('.aab'));
if (bundles.length !== 1) throw Error('Exactly one Android bundle is required');
const bundle = `${out}/${bundles[0]}`;
const sha256 = createHash('sha256').update(readFileSync(bundle)).digest('hex');
if (readFileSync(`${bundle}.sha256`, 'utf8').trim().split(/\s+/)[0] !== sha256) throw Error('Android bundle checksum mismatch');
execFileSync('keytool', ['-genkeypair', '-keystore', `${out}/qa.p12`, '-storetype', 'PKCS12', '-storepass', 'android', '-keypass', 'android', '-alias', 'androidqa', '-keyalg', 'RSA', '-keysize', '2048', '-validity', '1', '-dname', 'CN=FluxWP isolated QA']);
execFileSync('java', ['-jar', `${out}/bundletool.jar`, 'build-apks', `--bundle=${bundle}`, `--output=${out}/qa.apks`, '--mode=universal', `--ks=${out}/qa.p12`, '--ks-pass=pass:android', '--ks-key-alias=androidqa', '--key-pass=pass:android', '--overwrite'], { stdio: 'inherit' });
writeFileSync(`${out}/universal.apk`, execFileSync('unzip', ['-p', `${out}/qa.apks`, 'universal.apk'], { maxBuffer: 200 * 1024 * 1024 }));
writeFileSync(`${out}/provenance.json`, JSON.stringify({ ...release, buildRunId: runId, aabSha256: sha256, nativeInputHash: nativeInputHash(), captureCommit: process.env.GITHUB_SHA }, null, 2));
console.log('Verified Android bundle prepared for isolated emulator QA.');
