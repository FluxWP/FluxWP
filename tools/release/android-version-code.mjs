import { appendFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

// Unix seconds already approach Play's integer limit. A fixed project epoch
// leaves room for decades of updates. Android release jobs serialize per brand.
const EPOCH = Date.UTC(2025, 0, 1);
export function androidVersionCode(now = Date.now()) {
  const value = Math.floor((now - EPOCH) / 1000);
  if (!Number.isSafeInteger(value) || value < 1 || value > 2_100_000_000) throw new Error('Android version code is outside the supported range');
  return value;
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  const code = androidVersionCode();
  if (!process.env.GITHUB_ENV) throw new Error('GITHUB_ENV is required');
  appendFileSync(process.env.GITHUB_ENV, `FLUX_BUILD_NUMBER=${code}\n`);
}
