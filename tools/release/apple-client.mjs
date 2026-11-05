// Official App Store Connect API client. Tokens and private keys are never logged.
import { readFileSync } from 'node:fs';
import { sign } from 'node:crypto';

export function appleToken({ keyId, issuerId, keyFile }, now = Math.floor(Date.now() / 1000)) {
  if (!keyId || !issuerId || !keyFile) throw new Error('App Store Connect API credentials are missing');
  const b64 = value => Buffer.from(JSON.stringify(value)).toString('base64url');
  const input = `${b64({ alg: 'ES256', kid: keyId, typ: 'JWT' })}.${b64({ iss: issuerId, iat: now - 10, exp: now + 900, aud: 'appstoreconnect-v1' })}`;
  return `${input}.${sign('sha256', Buffer.from(input), { key: readFileSync(keyFile), dsaEncoding: 'ieee-p1363' }).toString('base64url')}`;
}

export function appleClient(credentials) {
  return async (path, { method = 'GET', body } = {}) => {
    if (!/^\/v[12]\//.test(path)) throw new Error('Only official App Store Connect API resources are allowed');
    const response = await fetch(`https://api.appstoreconnect.apple.com${path}`, {
      method, headers: { Authorization: `Bearer ${appleToken(credentials)}`, 'Content-Type': 'application/json' },
      ...(body ? { body: JSON.stringify(body) } : {}), signal: AbortSignal.timeout(60000),
    });
    const result = response.status === 204 ? null : await response.json();
    if (!response.ok) throw new Error(`Apple API ${response.status}: ${result?.errors?.map(e => `${e.code}: ${e.detail || e.title}`).join('; ') || 'request failed'}`);
    return result;
  };
}
