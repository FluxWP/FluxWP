#!/usr/bin/env node
import { readFileSync, existsSync } from 'node:fs';
import { join } from 'node:path';
import { root, requireSecrets } from './config.mjs';
const brand = process.env.FLUX_BRAND || 'flux';
if (!/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(brand) || !existsSync(join(root, 'brands', brand, 'brand.json'))) throw new Error('Invalid release brand');
const version = JSON.parse(readFileSync(join(root, 'apps/desktop/package.json'), 'utf8')).version;
const releaseTag = process.env.RELEASE_TAG || (process.env.GITHUB_REF?.startsWith('refs/tags/') ? process.env.GITHUB_REF.slice(10) : '');
if (releaseTag && releaseTag !== `fluxapp-v${version}`) throw new Error(`Release tag must match desktop package version: fluxapp-v${version}`);
if (process.env.REQUIRE_SIGNING === 'true') {
  requireSecrets(['APPLE_CERTIFICATE', 'APPLE_CERTIFICATE_PASSWORD', 'APPLE_SIGNING_IDENTITY', 'TAURI_SIGNING_PRIVATE_KEY']);
  const api = ['APPLE_API_ISSUER', 'APPLE_API_KEY', 'APPLE_API_KEY_P8'].every(k => process.env[k]);
  const password = ['APPLE_ID', 'APPLE_PASSWORD', 'APPLE_TEAM_ID'].every(k => process.env[k]);
  if (!api && !password) throw new Error('macOS notarization credentials are incomplete');
}
console.log(`macOS release preflight passed: ${brand} ${version}`);
