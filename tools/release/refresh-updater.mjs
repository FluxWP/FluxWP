#!/usr/bin/env node
// Flush the existing signed update-server cache after publishing desktop assets.
import { createHmac } from 'node:crypto';
const base = process.env.UPDATE_SERVER;
const secret = process.env.UPDATE_WEBHOOK_SECRET;
if (!base || !secret) {
  console.log('::warning::Update server refresh skipped: configure UPDATE_SERVER and UPDATE_WEBHOOK_SECRET.');
  process.exit(0);
}
const endpoint = new URL('/v1/webhook', base);
if (endpoint.protocol !== 'https:') throw new Error('Update server must use HTTPS');
const body = JSON.stringify({ source: 'github-actions' });
const signature = `sha256=${createHmac('sha256', secret).update(body).digest('hex')}`;
const response = await fetch(endpoint, {
  method: 'POST', headers: { 'Content-Type': 'application/json', 'X-GitHub-Event': 'release', 'X-Hub-Signature-256': signature },
  body, signal: AbortSignal.timeout(20000),
});
if (!response.ok) throw new Error(`Update server refresh failed (HTTP ${response.status}); published release remains available`);
console.log('Desktop update-server cache refreshed.');
