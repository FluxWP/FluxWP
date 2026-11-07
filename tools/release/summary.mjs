#!/usr/bin/env node
import { appendFileSync, existsSync, readFileSync, readdirSync } from 'node:fs';
import { join } from 'node:path';
import { root } from './config.mjs';
const platform = process.env.RELEASE_PLATFORM;
const dir = join(root, 'release-out', platform);
const files = existsSync(dir) ? readdirSync(dir) : [];
const manifest = join(dir, 'release.json');
const release = existsSync(manifest) ? JSON.parse(readFileSync(manifest, 'utf8')) : null;
const lines = [`## ${platform} distribution`, '', `- Brand: ${process.env.FLUX_BRAND}`, `- Destination: ${process.env.RELEASE_DESTINATION}`, `- Commit: \`${process.env.GITHUB_SHA}\``, ...(release ? [`- Version/build: ${release.version} / ${release.build_number}`] : []), '', ...files.map(f => `- ${f}`), '', 'A successful upload is not an App Store/Google Play approval. See the selected store console for processing and review status.', ''];
if (process.env.GITHUB_STEP_SUMMARY) appendFileSync(process.env.GITHUB_STEP_SUMMARY, lines.join('\n'));
console.log('Release summary written.');
