// RFC 7636 PKCE (S256). globalThis.crypto: tarayıcı, Tauri, Node 20+; RN'de expo-crypto polyfill.

const b64url = (bytes: Uint8Array): string => {
  let s = '';
  for (const b of bytes) s += String.fromCharCode(b);
  return btoa(s).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
};

export function randomString(bytes = 32): string {
  const a = new Uint8Array(bytes);
  globalThis.crypto.getRandomValues(a);
  return b64url(a);
}

export async function pkceChallenge(verifier: string): Promise<string> {
  const digest = await globalThis.crypto.subtle.digest('SHA-256', new TextEncoder().encode(verifier));
  return b64url(new Uint8Array(digest));
}

export async function createPkce() {
  const verifier = randomString(48);
  return { verifier, challenge: await pkceChallenge(verifier), method: 'S256' as const };
}
