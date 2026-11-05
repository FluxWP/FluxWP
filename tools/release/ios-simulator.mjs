#!/usr/bin/env node
// A real Release simulator build (not the Expo web preview), used for QA and store screenshots.
import { readdirSync, mkdirSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { spawnSync } from 'node:child_process';
import { root } from './config.mjs';

const ios = join(root, 'apps/mobile/ios');
const workspace = readdirSync(ios).find(f => f.endsWith('.xcworkspace'));
if (!workspace) throw new Error('Run Expo prebuild and pod install first');
const list = spawnSync('xcodebuild', ['-list', '-json', '-workspace', join(ios, workspace)], { encoding: 'utf8' });
if (list.status !== 0) throw new Error('Cannot inspect the generated Xcode workspace');
const schemes = JSON.parse(list.stdout).workspace.schemes;
const project = workspace.replace(/\.xcworkspace$/, '');
if (!schemes.includes(project)) throw new Error(`Missing application scheme ${project}`);
const derived = join(root, 'release-out/simulator-derived');
const logs = join(root, 'release-out/logs/ios');
mkdirSync(logs, { recursive: true });
const build = spawnSync('xcodebuild', ['-workspace', join(ios, workspace), '-scheme', project, '-configuration', 'Release', '-sdk', 'iphonesimulator', '-destination', 'generic/platform=iOS Simulator', '-derivedDataPath', derived, 'CODE_SIGNING_ALLOWED=NO', 'build'], { stdio: 'inherit' });
if (build.status !== 0) process.exit(build.status || 1);
const products = join(derived, 'Build/Products/Release-iphonesimulator');
const app = readdirSync(products).find(f => f.endsWith('.app'));
if (!app) throw new Error('Xcode did not produce a simulator app');
const out = join(root, 'release-out/ios');
mkdirSync(out, { recursive: true });
const archive = join(out, `${project}-simulator.zip`);
const zip = spawnSync('ditto', ['-c', '-k', '--sequesterRsrc', '--keepParent', join(products, app), archive], { stdio: 'inherit' });
if (zip.status) throw new Error('Cannot preserve simulator app permissions in archive');
writeFileSync(join(out, 'simulator.json'), JSON.stringify({ app: join(products, app) }, null, 2));
console.log(`Native simulator app archived: release-out/ios/${project}-simulator.zip`);
