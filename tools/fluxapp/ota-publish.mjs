#!/usr/bin/env node
// Publish an `expo export` output as an OTA update to our own update server (FluxWP/updater,
// Expo Updates protocol v1). No EAS. Manifests and directives are signed HERE with the code
// signing private key; the server only stores and serves the signed bytes.
//
//   node tools/fluxapp/ota-publish.mjs publish  --dist apps/mobile/dist --brand flux --channel production \
//        --runtime-ios <fp> --runtime-android <fp> --expo-config expoConfig.json [--message ..] [--commit sha]
//   node tools/fluxapp/ota-publish.mjs rollback --brand flux --channel production --runtime-ios <fp> [--runtime-android <fp>]
//
// Env: FLUX_OTA_URL (default https://updater.fluxwp.com), FLUX_OTA_PUBLISH_KEY (panel › Mobile OTA),
//      FLUX_OTA_PRIVATE_KEY (PEM, from `expo-updates codesigning:generate`).
// docs/mobile/OTA.md
import { createHash, createPrivateKey, createPublicKey, randomUUID, sign, X509Certificate } from 'node:crypto';
import { existsSync, readFileSync } from 'node:fs';
import { join, resolve } from 'node:path';

const MIME = {
  png: 'image/png', jpg: 'image/jpeg', jpeg: 'image/jpeg', gif: 'image/gif', webp: 'image/webp', svg: 'image/svg+xml',
  ttf: 'font/ttf', otf: 'font/otf', woff: 'font/woff', woff2: 'font/woff2', json: 'application/json', lottie: 'application/json',
  mp3: 'audio/mpeg', wav: 'audio/wav', mp4: 'video/mp4', txt: 'text/plain',
};
const SAFE_EXT = /^[a-z0-9]{1,8}$/;

const die = (msg) => {
  console.error(`::error::${msg}`);
  process.exit(1);
};

function args(argv) {
  const out = { _: [] };
  for (let i = 0; i < argv.length; i++) {
    const a = argv[i];
    if (a.startsWith('--')) {
      const k = a.slice(2);
      const v = argv[i + 1] && !argv[i + 1].startsWith('--') ? argv[++i] : 'true';
      out[k] = v;
    } else out._.push(a);
  }
  return out;
}

const b64url = (buf) => buf.toString('base64').replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');

/** RFC 8941 dictionary, as the expo-signature header expects. */
const signature = (body, key) => `sig="${sign('sha256', Buffer.from(body, 'utf8'), key).toString('base64')}", keyid="main"`;

async function api(base, key, path, body, type = 'application/json') {
  for (let attempt = 1; ; attempt++) {
    let res;
    try {
      res = await fetch(`${base}${path}`, {
        method: 'POST',
        headers: { 'content-type': type, 'x-fluxwp-ota-key': key, 'user-agent': 'fluxwp-ota-publish' },
        body,
      });
    } catch (e) {
      if (attempt < 4) {
        await new Promise((r) => setTimeout(r, 2000 * attempt));
        continue;
      }
      die(`${path}: ${e.message}`);
    }
    const text = await res.text();
    let json;
    try {
      json = JSON.parse(text);
    } catch {
      json = { error: text.slice(0, 300) };
    }
    if (res.ok && json.ok) return json;
    if ((res.status >= 500 || res.status === 429) && attempt < 4) {
      await new Promise((r) => setTimeout(r, 2000 * attempt));
      continue;
    }
    die(`${path}: HTTP ${res.status} ${json.code || ''} ${json.error || ''}`.trim());
  }
}

function loadKey() {
  const pem = process.env.FLUX_OTA_PRIVATE_KEY || '';
  if (!pem.includes('PRIVATE KEY')) die('FLUX_OTA_PRIVATE_KEY secret is missing (PEM private key)');
  return createPrivateKey(pem);
}

/** The private key must belong to the certificate embedded in the app, or every device rejects the update. */
function checkPair(key, brand) {
  const certFile = resolve(`brands/${brand}/ota-certificate.pem`);
  if (!existsSync(certFile)) die(`brands/${brand}/ota-certificate.pem missing: OTA is off for this brand (docs/mobile/OTA.md)`);
  const cert = new X509Certificate(readFileSync(certFile));
  const a = createPublicKey(key).export({ type: 'spki', format: 'der' });
  const b = cert.publicKey.export({ type: 'spki', format: 'der' });
  if (!a.equals(b)) die(`FLUX_OTA_PRIVATE_KEY does not match brands/${brand}/ota-certificate.pem`);
  if (new Date(cert.validTo) < new Date()) die('OTA certificate expired');
}

function platformAssets(dist, meta, platform, base) {
  const fm = meta.fileMetadata && meta.fileMetadata[platform];
  if (!fm || !fm.bundle) die(`dist/metadata.json has no ${platform} bundle (export with --platform ${platform})`);
  const files = new Map();
  const entry = (rel, ext, launch) => {
    if (!SAFE_EXT.test(ext)) die(`unsupported asset extension: ${ext}`);
    const bytes = readFileSync(join(dist, rel));
    const hex = createHash('sha256').update(bytes).digest('hex');
    files.set(hex, bytes);
    return {
      hash: b64url(createHash('sha256').update(bytes).digest()),
      key: createHash('md5').update(bytes).digest('hex'),
      fileExtension: launch ? '.bundle' : `.${ext}`,
      contentType: launch ? 'application/javascript' : MIME[ext] || 'application/octet-stream',
      url: `${base}/assets/${hex}.${launch ? 'js' : ext}`,
    };
  };
  const launchAsset = entry(fm.bundle, 'js', true);
  const assets = (fm.assets || []).map((a) => entry(a.path, String(a.ext || '').toLowerCase(), false));
  return { launchAsset, assets, files };
}

async function publish(o) {
  const brand = o.brand || 'flux';
  const channel = o.channel || 'production';
  const server = (process.env.FLUX_OTA_URL || 'https://updater.fluxwp.com').replace(/\/+$/, '');
  const key = process.env.FLUX_OTA_PUBLISH_KEY || die('FLUX_OTA_PUBLISH_KEY secret is missing (update server › Mobile OTA)');
  const pk = loadKey();
  checkPair(pk, brand);
  const dist = resolve(o.dist || 'apps/mobile/dist');
  const meta = JSON.parse(readFileSync(join(dist, 'metadata.json'), 'utf8'));
  // Constants.expoConfig in the running update comes from here, so it is required (expo config --json --type public).
  if (!o['expo-config']) die('--expo-config missing (bunx expo config --json --type public > dist/expoConfig.json)');
  const expoClient = JSON.parse(readFileSync(o['expo-config'], 'utf8'));
  if (!expoClient.updates || !expoClient.extra) die('--expo-config does not look like the public Expo config');
  const base = `${server}/v1/expo/${brand}`;
  const platforms = (o.platforms || 'ios,android').split(',').filter(Boolean);
  const createdAt = new Date().toISOString();
  const updates = [];
  const files = new Map();
  for (const platform of platforms) {
    const runtime = o[`runtime-${platform}`];
    if (!runtime) die(`--runtime-${platform} missing`);
    const { launchAsset, assets, files: f } = platformAssets(dist, meta, platform, base);
    for (const [h, b] of f) files.set(h, b);
    const manifest = JSON.stringify({
      id: randomUUID(),
      createdAt,
      runtimeVersion: runtime,
      launchAsset,
      assets,
      metadata: {},
      extra: { expoClient },
    });
    updates.push({ platform, runtime, manifest, signature: signature(manifest, pk) });
  }
  const { missing } = await api(base, key, '/assets/missing', JSON.stringify({ hashes: [...files.keys()] }));
  let sent = 0;
  for (const h of missing) {
    await api(base, key, `/assets/${h}`, files.get(h), 'application/octet-stream');
    sent += files.get(h).length;
  }
  console.log(`assets: ${files.size} total, ${missing.length} uploaded (${(sent / 1048576).toFixed(1)} MB)`);
  const noUpdate = JSON.stringify({ type: 'noUpdateAvailable' });
  const res = await api(
    base,
    key,
    '/publish',
    JSON.stringify({
      channel,
      message: o.message || '',
      commit: o.commit || '',
      no_update: { body: noUpdate, signature: signature(noUpdate, pk) },
      updates,
    }),
  );
  const lines = [`### OTA ${brand} / ${channel}`, '', '| Platform | Runtime | Update | Size | Devices on this runtime |', '|---|---|---|---|---|'];
  for (const u of res.updates) {
    lines.push(`| ${u.platform} | \`${u.runtime.slice(0, 12)}\` | \`${u.id}\` | ${(u.bytes / 1048576).toFixed(1)} MB | ${u.seen ? 'yes' : '**none yet**'} |`);
    if (!u.seen) {
      console.log(`::warning::No ${u.platform} device has asked for runtime ${u.runtime} yet. The update reaches only builds with this exact native fingerprint (ship a store/TestFlight build first if native code changed).`);
    }
  }
  console.log(lines.join('\n'));
  if (process.env.GITHUB_STEP_SUMMARY) {
    const { appendFileSync } = await import('node:fs');
    appendFileSync(process.env.GITHUB_STEP_SUMMARY, lines.join('\n') + '\n');
  }
}

async function rollback(o) {
  const brand = o.brand || 'flux';
  const channel = o.channel || 'production';
  const server = (process.env.FLUX_OTA_URL || 'https://updater.fluxwp.com').replace(/\/+$/, '');
  const key = process.env.FLUX_OTA_PUBLISH_KEY || die('FLUX_OTA_PUBLISH_KEY secret is missing');
  const pk = loadKey();
  checkPair(pk, brand);
  const base = `${server}/v1/expo/${brand}`;
  let n = 0;
  for (const platform of ['ios', 'android']) {
    const runtime = o[`runtime-${platform}`];
    if (!runtime) continue;
    const directive = JSON.stringify({ type: 'rollBackToEmbedded', parameters: { commitTime: new Date().toISOString() } });
    await api(base, key, '/rollback', JSON.stringify({ channel, platform, runtime, directive, signature: signature(directive, pk) }));
    console.log(`${platform} ${runtime}: devices roll back to the embedded bundle on next launch`);
    n++;
  }
  if (!n) die('pass --runtime-ios and/or --runtime-android');
}

const o = args(process.argv.slice(2));
const cmd = o._[0];
if (cmd === 'publish') await publish(o);
else if (cmd === 'rollback') await rollback(o);
else die('usage: ota-publish.mjs publish|rollback …');
