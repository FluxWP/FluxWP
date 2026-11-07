// @fluxwp/core PKCE needs crypto.getRandomValues + crypto.subtle.digest('SHA-256'). Hermes has neither.
import * as ExpoCrypto from 'expo-crypto';

type CryptoLike = {
  getRandomValues?: <T extends ArrayBufferView | null>(a: T) => T;
  subtle?: { digest?: (alg: string, data: BufferSource) => Promise<ArrayBuffer> };
};

const g = globalThis as unknown as { crypto?: CryptoLike };
const c: CryptoLike = g.crypto ?? (g.crypto = {});

if (!c.getRandomValues) {
  c.getRandomValues = <T extends ArrayBufferView | null>(a: T): T => {
    if (a) ExpoCrypto.getRandomValues(a as unknown as Uint8Array);
    return a;
  };
}

if (!c.subtle?.digest) {
  const algs: Record<string, ExpoCrypto.CryptoDigestAlgorithm> = {
    'SHA-256': ExpoCrypto.CryptoDigestAlgorithm.SHA256,
    'SHA-1': ExpoCrypto.CryptoDigestAlgorithm.SHA1,
    'SHA-512': ExpoCrypto.CryptoDigestAlgorithm.SHA512,
  };
  c.subtle = {
    ...c.subtle,
    digest: (alg, data) => {
      const a = algs[String(alg).toUpperCase()];
      if (!a) return Promise.reject(new Error(`digest ${alg} not supported`));
      return ExpoCrypto.digest(a, data as ArrayBuffer);
    },
  };
}
