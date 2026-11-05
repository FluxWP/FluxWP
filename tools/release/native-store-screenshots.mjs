#!/usr/bin/env node
// Runs only on a dedicated CI simulator, with the app's built-in fictitious demo account.
import { readFileSync, writeFileSync, mkdirSync, readdirSync } from 'node:fs';
import { join, relative } from 'node:path';
import { createHash } from 'node:crypto';
import { spawnSync } from 'node:child_process';
import { root } from './config.mjs';

function run(command, args, capture = false) {
  const r = spawnSync(command, args, { encoding: 'utf8', stdio: capture ? 'pipe' : 'inherit', env: { ...process.env, MAESTRO_CLI_NO_ANALYTICS: '1' } });
  if (r.status !== 0) throw new Error(`${command} failed (${r.status})`);
  return r.stdout?.trim();
}
const manifest = JSON.parse(readFileSync(join(root, 'release-out/ios/simulator.json'), 'utf8'));
const out = join(root, 'release-out/store-assets');
mkdirSync(out, { recursive: true });
const runtimes = JSON.parse(run('xcrun', ['simctl', 'list', 'runtimes', '--json'], true)).runtimes.filter(r => r.isAvailable && r.identifier.includes('.iOS-'));
const runtime = runtimes.sort((a,b) => a.version.localeCompare(b.version, undefined, { numeric: true })).at(-1);
if (!runtime) throw new Error('No installed iOS simulator runtime');
const types = JSON.parse(run('xcrun', ['simctl', 'list', 'devicetypes', '--json'], true)).devicetypes;
const phone = types.find(t => t.name === 'iPhone 16 Pro Max') || types.filter(t => /iPhone.*Pro Max/.test(t.name)).at(-1);
const tablet = types.find(t => t.name === 'iPad Pro 13-inch (M4)') || types.filter(t => /iPad Pro.*13-inch/.test(t.name)).at(-1);
if (!phone || !tablet) throw new Error('A large iPhone and 13-inch iPad simulator are required');
const screenshots = [];
for (const locale of ['tr-TR', 'en-US']) {
  for (const [device, kind] of [[phone, 'iphone'], [tablet, 'ipad']]) {
    const id = run('xcrun', ['simctl', 'create', `FluxWP-${kind}-${locale}`, device.identifier, runtime.identifier], true);
    const folder = join(out, 'ios', locale === 'tr-TR' ? 'tr' : locale);
    mkdirSync(folder, { recursive: true });
    const names = ['01-connect', '02-dashboard', '03-contacts', '04-posts', '05-menu'].map(s => `${kind}-${s}`);
    const shot = n => JSON.stringify(join(folder, n));
    try {
      run('xcrun', ['simctl', 'boot', id]);
      run('xcrun', ['simctl', 'bootstatus', id, '-b']);
      run('xcrun', ['simctl', 'status_bar', id, 'override', '--time', '9:41', '--dataNetwork', 'wifi', '--wifiMode', 'active', '--wifiBars', '3', '--batteryState', 'charged', '--batteryLevel', '100']);
      run('xcrun', ['simctl', 'install', id, manifest.app]);
      const flow = join(out, `flow-${kind}-${locale}.yaml`);
      writeFileSync(flow, `appId: ${process.env.FLUX_BUNDLE_IDENTIFIER}\n---\n- launchApp:\n    clearState: true\n    arguments:\n      AppleLanguages: '${locale === 'tr-TR' ? '(tr)' : '(en)'}'\n      AppleLocale: '${locale.replace('-', '_')}'\n- extendedWaitUntil:\n    visible:\n      id: demo\n    timeout: 60000\n- takeScreenshot: ${shot(names[0])}\n- tapOn:\n    id: demo\n- extendedWaitUntil:\n    visible:\n      id: open-drawer\n    timeout: 60000\n- waitForAnimationToEnd\n- takeScreenshot: ${shot(names[1])}\n- tapOn:\n    text: '(Kişiler|Contacts)'\n- waitForAnimationToEnd\n- takeScreenshot: ${shot(names[2])}\n- tapOn:\n    text: '(Yazılar|Posts)'\n- waitForAnimationToEnd\n- takeScreenshot: ${shot(names[3])}\n- tapOn:\n    id: open-drawer\n- waitForAnimationToEnd\n- takeScreenshot: ${shot(names[4])}\n`);
      run('maestro', ['--device', id, 'test', flow]);
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
writeFileSync(join(out, 'manifest.json'), JSON.stringify({native: true, brand: process.env.FLUX_BRAND, commit: process.env.GITHUB_SHA, files: screenshots}, null, 2));
console.log(`Captured ${screenshots.length} real native screenshots.`);
