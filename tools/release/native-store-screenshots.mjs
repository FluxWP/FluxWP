#!/usr/bin/env node
// Runs only on a dedicated CI simulator, with the app's built-in fictitious demo account.
import { readFileSync, writeFileSync, mkdirSync, readdirSync } from 'node:fs';
import { join, relative } from 'node:path';
import { createHash } from 'node:crypto';
import { spawnSync } from 'node:child_process';
import { root } from './config.mjs';
import { captureWithXCTest } from './native-capture.mjs';

function run(command, args, capture = false) {
  const r = spawnSync(command, args, { timeout: 15*60*1000, encoding: 'utf8', stdio: capture ? 'pipe' : 'inherit' });
  if (r.status !== 0) throw new Error(`${command} failed (${r.status})`);
  return r.stdout?.trim();
}
const manifest = JSON.parse(readFileSync(join(root, 'release-out/ios/simulator.json'), 'utf8'));
const out = join(root, 'release-out/store-assets');
mkdirSync(out, { recursive: true });
const runtimes = JSON.parse(run('xcrun', ['simctl', 'list', 'runtimes', '--json'], true)).runtimes.filter(r => r.isAvailable && r.identifier.includes('.iOS-'));
const runtime = runtimes.sort((a,b) => a.version.localeCompare(b.version, undefined, { numeric: true })).at(-1);
if (!runtime) throw new Error('No installed iOS simulator runtime');
console.log('Available iOS runtimes:', runtimes.map(r=>r.version).join(', '), '; chosen:', runtime.version);
const types = JSON.parse(run('xcrun', ['simctl', 'list', 'devicetypes', '--json'], true)).devicetypes;
const phone = types.find(t => t.name === 'iPhone 16 Pro Max') || types.filter(t => /iPhone.*Pro Max/.test(t.name)).at(-1);
const tablet = types.find(t => t.name === 'iPad Pro 13-inch (M4)') || types.filter(t => /iPad Pro.*13-inch/.test(t.name)).at(-1);
if (!phone || !tablet) throw new Error('A large iPhone and 13-inch iPad simulator are required');
const screenshots = [];
const locales = process.env.SCREENSHOT_LOCALE ? [process.env.SCREENSHOT_LOCALE] : ['tr-TR', 'en-US'];
if (!locales.every(l => ['tr-TR', 'en-US'].includes(l))) throw new Error('Invalid screenshot locale');
for (const locale of locales) {
  for (const [device, kind] of [[phone, 'iphone'], [tablet, 'ipad']]) {
    if (process.env.SCREENSHOT_DEVICE && process.env.SCREENSHOT_DEVICE !== kind) continue;
    const id = run('xcrun', ['simctl', 'create', `FluxWP-${kind}-${locale}`, device.identifier, runtime.identifier], true);
    const folder = join(out, 'ios', locale === 'tr-TR' ? 'tr' : locale);
    mkdirSync(folder, { recursive: true });
    const names = ['01-connect', '02-dashboard', '03-messages', '04-chat', '05-ai', '06-contacts', '07-posts', '08-menu', '09-accounts'].map(s => `${kind}-${s}`);
    try {
      console.log(`Booting ${kind} ${locale} on iOS ${runtime.version}`);
      run('xcrun', ['simctl', 'boot', id]);
      run('open', ['-a', 'Simulator', '--args', '-CurrentDeviceUDID', id]);
      run('xcrun', ['simctl', 'bootstatus', id, '-b']);
      console.log('Simulator boot completed; installing the verified native app.');
      run('xcrun', ['simctl', 'status_bar', id, 'override', '--time', '9:41', '--dataNetwork', 'wifi', '--wifiMode', 'active', '--wifiBars', '3', '--batteryState', 'charged', '--batteryLevel', '100']);
      run('xcrun', ['simctl', 'install', id, manifest.app]);
      captureWithXCTest({id, kind, locale, bundleId: process.env.FLUX_BUNDLE_IDENTIFIER, folder, names});
      for (const name of names) {
        const file = `${name}.png`;
        if (!readdirSync(folder).includes(file)) throw new Error(`Missing native screenshot ${locale}/${file}`);
        const data = readFileSync(join(folder, file));
        screenshots.push({ path: relative(out, join(folder, file)), sha256: createHash('sha256').update(data).digest('hex'), width: data.readUInt32BE(16), height: data.readUInt32BE(20) });
      }
    } finally {
      spawnSync('xcrun', ['simctl', 'shutdown', id], { stdio: 'ignore' });
      spawnSync('xcrun', ['simctl', 'delete', id], { stdio: 'ignore' });
    }
  }
}
const manifestName = process.env.SCREENSHOT_DEVICE ? `manifest-${process.env.SCREENSHOT_DEVICE}-${process.env.SCREENSHOT_LOCALE}.json` : 'manifest.json';
writeFileSync(join(out, manifestName), JSON.stringify({native: true, brand: process.env.FLUX_BRAND, commit: process.env.GITHUB_SHA, nativeInputHash: manifest.nativeInputHash, sourceCommit: manifest.sourceCommit, files: screenshots}, null, 2));
console.log(`Captured ${screenshots.length} real native screenshots.`);
