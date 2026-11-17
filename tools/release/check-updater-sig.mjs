#!/usr/bin/env node
// Otomatik güncelleme imzası, uygulamanın içine gömülen public key ile aynı anahtardan mı?
// Kullanım: node tools/release/check-updater-sig.mjs <dosya.sig> [...]
// Anahtar farklıysa kurulu uygulamalar paketi indirir ama doğrulayamaz ("Denetlenemedi"); yayın burada durur.
import { readFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');

/** Tauri .sig / .pub dosyası: minisign metninin base64'ü. 2. satır: 2 bayt algoritma + 8 bayt anahtar kimliği + ... */
export function keyId(b64File) {
  const text = Buffer.from(readFileSync(b64File, 'utf8').trim(), 'base64').toString('utf8');
  const line = text.split('\n').map((l) => l.trim()).find((l) => l && !l.startsWith('untrusted comment:') && !l.startsWith('trusted comment:'));
  if (!line) throw new Error(`${b64File}: minisign satırı yok`);
  const raw = Buffer.from(line, 'base64');
  if (raw.length < 10) throw new Error(`${b64File}: geçersiz minisign verisi`);
  return Buffer.from(raw.subarray(2, 10)).reverse().toString('hex').toUpperCase();
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  const pub = keyId(join(root, 'apps/desktop/updater.pub'));
  const conf = JSON.parse(readFileSync(join(root, 'apps/desktop/src-tauri/tauri.conf.json'), 'utf8'));
  const confKey = Buffer.from(conf.plugins.updater.pubkey, 'base64').toString('utf8').match(/public key: ([0-9A-F]+)/)?.[1];
  let bad = 0;
  if (confKey !== pub) {
    console.error(`::error::tauri.conf.json pubkey (${confKey}) updater.pub (${pub}) ile aynı değil`);
    bad++;
  }
  for (const f of process.argv.slice(2)) {
    const id = keyId(f);
    if (id === pub) console.log(`ok ${f} (${id})`);
    else {
      console.error(`::error::${f} ${id} anahtarıyla imzalı, uygulama ${pub} bekliyor. TAURI_SIGNING_PRIVATE_KEY secret'ı apps/desktop/updater.pub ile eşleşmiyor.`);
      bad++;
    }
  }
  process.exit(bad ? 1 : 0);
}
