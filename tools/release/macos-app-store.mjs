// Mac App Store signing stays separate from Developer ID / direct-update releases.
import { readFileSync, writeFileSync, mkdirSync, mkdtempSync, appendFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { tmpdir } from 'node:os';
import { execFileSync } from 'node:child_process';
import { randomBytes, createHash } from 'node:crypto';

const root = process.cwd();
const required = name => {
  if (!process.env[name]) throw new Error(`Missing ${name}`);
  return process.env[name];
};
const run = (command, args) => {
  try { return execFileSync(command, args, { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }); }
  catch { throw new Error(`${command} failed; signing arguments and credentials suppressed`); }
};
const stage = process.argv[2];
if (stage === 'prepare') {
  const version = process.env.STORE_VERSION || JSON.parse(readFileSync('apps/desktop/package.json')).version;
  const build = required('STORE_BUILD_NUMBER');
  if (!/^\d+\.\d+\.\d+$/.test(version) || !/^\d{1,9}$/.test(build)) throw new Error('Invalid store version/build number');
  if (process.env.FLUX_BRAND !== 'flux') throw new Error('Only the reviewed flux store app is configured');
  const team = required('APPLE_TEAM_ID');
  const bundleId = JSON.parse(readFileSync('brands/flux/brand.json')).ios.bundleIdentifier;
  const dir = mkdtempSync(join(process.env.RUNNER_TEMP || tmpdir(), 'fluxwp-mas-'));
  const materialize = (name, env) => {
    const path = join(dir, name);
    writeFileSync(path, Buffer.from(required(env), 'base64'), { mode: 0o600 });
    return path;
  };
  const profile = materialize('store.provisionprofile', 'MACOS_PROVISIONING_PROFILE');
  const xml = run('security', ['cms', '-D', '-i', profile]);
  writeFileSync(join(dir, 'profile.plist'), xml, { mode: 0o600 });
  const parsed = JSON.parse(run('python3', ['-c', 'import plistlib,json,sys,datetime; p=plistlib.load(open(sys.argv[1],"rb")); print(json.dumps({"TeamIdentifier":p.get("TeamIdentifier"),"Entitlements":p["Entitlements"],"ProvisionedDevices":p.get("ProvisionedDevices"),"ExpirationDate":p["ExpirationDate"].replace(tzinfo=datetime.timezone.utc).isoformat()}))', join(dir, 'profile.plist')]));
  const ent = parsed.Entitlements;
  if (parsed.TeamIdentifier?.[0] !== team || ent['com.apple.application-identifier'] !== `${team}.${bundleId}` || ent['get-task-allow'] || parsed.ProvisionedDevices || Date.parse(parsed.ExpirationDate) <= Date.now()) throw new Error('Wrong, development or expired Mac store profile');
  const keychain = join(dir, 'store.keychain-db');
  const password = randomBytes(32).toString('base64url');
  run('security', ['create-keychain', '-p', password, keychain]);
  run('security', ['set-keychain-settings', '-lut', '21600', keychain]);
  run('security', ['unlock-keychain', '-p', password, keychain]);
  for (const [file, certificate, pass] of [
    ['app.p12', 'IOS_DISTRIBUTION_CERTIFICATE', 'IOS_DISTRIBUTION_CERTIFICATE_PASSWORD'],
    ['installer.p12', 'MACOS_INSTALLER_CERTIFICATE', 'MACOS_INSTALLER_CERTIFICATE_PASSWORD'],
  ]) {
    run('security', ['import', materialize(file, certificate), '-k', keychain, '-P', required(pass), '-T', '/usr/bin/codesign', '-T', '/usr/bin/productbuild', '-T', '/usr/bin/productsign']);
  }
  run('security', ['set-key-partition-list', '-S', 'apple-tool:,apple:,codesign:', '-s', '-k', password, keychain]);
  const previous = run('security', ['list-keychains', '-d', 'user']).match(/"([^"]+)"/g)?.map(x => x.slice(1, -1)) || [];
  run('security', ['list-keychains', '-d', 'user', '-s', keychain, ...previous]);
  const identities = run('security', ['find-identity', '-v', '-p', 'codesigning', keychain]);
  const identity = identities.match(/"(Apple Distribution:[^"]+)"/)?.[1];
  const installer = run('security', ['find-identity', '-v', keychain]).match(/"(3rd Party Mac Developer Installer:[^"]+)"/)?.[1];
  if (!identity?.endsWith(`(${team})`) || !installer?.endsWith(`(${team})`)) throw new Error('App/installer signing identities missing or wrong team');
  const entitlements = join(dir, 'Entitlements.plist');
  writeFileSync(entitlements, `<?xml version="1.0" encoding="UTF-8"?>\n<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">\n<plist version="1.0"><dict>
<key>com.apple.security.app-sandbox</key><true/>
<key>com.apple.application-identifier</key><string>${team}.${bundleId}</string>
<key>com.apple.developer.team-identifier</key><string>${team}</string>
<key>keychain-access-groups</key><array><string>${team}.${bundleId}</string></array>
<key>com.apple.security.network.client</key><true/>
<key>com.apple.security.network.server</key><true/>
<key>com.apple.security.files.user-selected.read-write</key><true/>
</dict></plist>\n`, { mode: 0o600 });
  const info = join(dir, 'Info.plist');
  writeFileSync(info, '<?xml version="1.0"?><plist version="1.0"><dict><key>ITSAppUsesNonExemptEncryption</key><false/></dict></plist>\n');
  const capability = JSON.parse(readFileSync('apps/desktop/src-tauri/capabilities/default.json'));
  capability.identifier = 'mac-app-store';
  capability.permissions = capability.permissions.filter(x => !['updater:default', 'process:allow-restart'].includes(x));
  const capabilityPath = join(dir, 'capability.json');
  writeFileSync(capabilityPath, JSON.stringify(capability));
  const config = {
    identifier: bundleId, version,
    app: { security: { capabilities: [capability] } },
    bundle: { targets: ['app'], createUpdaterArtifacts: false, macOS: { minimumSystemVersion: '11.0', bundleVersion: build, signingIdentity: identity, entitlements, infoPlist: info, files: { 'embedded.provisionprofile': profile } } },
    plugins: { updater: { endpoints: [] } },
  };
  const configPath = join(dir, 'store.conf.json');
  writeFileSync(configPath, JSON.stringify(config, null, 2));
  writeFileSync(join(dir, 'state.json'), JSON.stringify({ dir, keychain, previous, identity, installer, bundleId, version, build }));
  appendFileSync(required('GITHUB_ENV'), `MAS_TEMP=${dir}\nMAS_CONFIG=${configPath}\nVITE_APP_STORE=true\nAPPLE_SIGNING_IDENTITY=${identity}\n`);
  console.log(`Prepared sandboxed Mac App Store ${version}(${build}) for ${bundleId}`);
} else if (stage === 'package') {
  const state = JSON.parse(readFileSync(join(required('MAS_TEMP'), 'state.json')));
  const app = resolve('apps/desktop/src-tauri/target/universal-apple-darwin/release/bundle/macos/FluxWP.app');
  const info = JSON.parse(run('plutil', ['-convert', 'json', '-o', '-', join(app, 'Contents/Info.plist')]));
  if (info.CFBundleIdentifier !== state.bundleId || info.CFBundleShortVersionString !== state.version || info.CFBundleVersion !== state.build) throw new Error('Native bundle metadata mismatch');
  run('codesign', ['--verify', '--deep', '--strict', app]);
  const signedEntitlements = run('codesign', ['-d', '--entitlements', ':-', app]);
  if (!signedEntitlements.includes('com.apple.security.app-sandbox')) throw new Error('Sandbox entitlement missing');
  const architectures = run('lipo', ['-archs', join(app, 'Contents/MacOS', info.CFBundleExecutable)]);
  if (!architectures.includes('arm64') || !architectures.includes('x86_64')) throw new Error('Universal app required');
  const out = resolve('release-out/macos-app-store');
  mkdirSync(out, { recursive: true });
  const pkg = join(out, `FluxWP-${state.version}-${state.build}.pkg`);
  run('xcrun', ['productbuild', '--sign', state.installer, '--keychain', state.keychain, '--component', app, '/Applications', pkg]);
  run('pkgutil', ['--check-signature', pkg]);
  const sha256 = createHash('sha256').update(readFileSync(pkg)).digest('hex');
  writeFileSync(join(out, 'SHA256SUMS'), `${sha256}  ${pkg.split('/').at(-1)}\n`);
  writeFileSync(join(out, 'release.json'), JSON.stringify({ platform: 'MAC_OS', bundleId: state.bundleId, version: state.version, build: state.build, commit: process.env.GITHUB_SHA, sandbox: true, architectures: architectures.trim(), sha256 }, null, 2) + '\n');
  if (process.env.MAS_UPLOAD === 'true') {
    const keyId = required('APPLE_API_KEY');
    if (!/^[A-Z0-9]+$/.test(keyId)) throw new Error('Invalid API key ID');
    const keys = join(state.dir, 'private_keys'); mkdirSync(keys);
    writeFileSync(join(keys, `AuthKey_${keyId}.p8`), required('APPLE_API_KEY_P8'), { mode: 0o600 });
    execFileSync('xcrun', ['altool', '--upload-app', '--type', 'macos', '--file', pkg, '--apiKey', keyId, '--apiIssuer', required('APPLE_API_ISSUER')], { cwd: state.dir, stdio: 'inherit', timeout: 1800000 });
  }
  console.log('Signed universal sandboxed store package verified');
} else if (stage === 'clean') {
  if (process.env.MAS_TEMP) {
    const state = JSON.parse(readFileSync(join(process.env.MAS_TEMP, 'state.json')));
    run('security', ['list-keychains', '-d', 'user', '-s', ...state.previous]);
    run('security', ['delete-keychain', state.keychain]);
  }
} else throw new Error('Expected prepare, package or clean');
