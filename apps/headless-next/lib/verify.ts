// Flux Headless webhook signature: X-Flx-Signature: t=<unix time>,v1=<hex HMAC-SHA256(secret, "<t>.<raw body>")>.
// Also used for preview links: sig = hex HMAC-SHA256(secret, "preview|<id>|<exp>").

const enc = new TextEncoder();

export async function hmacHex(secret: string, data: string): Promise<string> {
  const key = await crypto.subtle.importKey('raw', enc.encode(secret), { name: 'HMAC', hash: 'SHA-256' }, false, ['sign']);
  const sig = new Uint8Array(await crypto.subtle.sign('HMAC', key, enc.encode(data)));
  return Array.from(sig, (b) => b.toString(16).padStart(2, '0')).join('');
}

/** Constant-time comparison of two hex strings. */
export function sameHex(a: string, b: string): boolean {
  let diff = a.length ^ b.length;
  for (let i = 0; i < Math.max(a.length, b.length); i++) diff |= (a.charCodeAt(i) || 0) ^ (b.charCodeAt(i) || 0);
  return diff === 0;
}

/** True when the header signs this exact body with the secret and is at most `maxAge` seconds old. */
export async function verifySignature(body: string, header: string | null, secret: string, maxAge = 300): Promise<boolean> {
  const m = /^t=(\d+),v1=([0-9a-f]{64})$/.exec(header ?? '');
  if (!m || !secret) return false;
  const [, t = '', v1 = ''] = m;
  if (Math.abs(Date.now() / 1000 - Number(t)) > maxAge) return false;
  return sameHex(await hmacHex(secret, `${t}.${body}`), v1);
}
