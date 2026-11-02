#!/usr/bin/env node
// Seçili markayı (FLUX_BRAND, varsayılan "flux") uygulamanın her yerine yayar.
// Tek kaynak: brands/<slug>/brand.json + logo/ikon dosyaları.
import { readFileSync, writeFileSync, mkdirSync, existsSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const slug = process.env.FLUX_BRAND || 'flux';
const dir = join(root, 'brands', slug);
if (!existsSync(join(dir, 'brand.json'))) {
  console.error(`[brand] brands/${slug}/brand.json yok`);
  process.exit(1);
}
const brand = JSON.parse(readFileSync(join(dir, 'brand.json'), 'utf8'));

const required = ['slug', 'name', 'bundleId', 'scheme', 'icon', 'logo', 'colors', 'loginMethods', 'defaultLocale', 'locales'];
for (const k of required) {
  if (brand[k] === undefined) { console.error(`[brand] ${slug}: "${k}" eksik`); process.exit(1); }
}
if (brand.lockSite && !brand.defaultSite) { console.error(`[brand] ${slug}: lockSite için defaultSite gerekli`); process.exit(1); }
if (!brand.loginMethods.length || brand.loginMethods.some((m) => !['oauth', 'apikey'].includes(m))) { console.error(`[brand] ${slug}: loginMethods geçersiz`); process.exit(1); }
if (!/^[a-z][a-z0-9+.-]*$/.test(brand.scheme)) { console.error(`[brand] ${slug}: scheme geçersiz`); process.exit(1); }

const write = (p, data) => { mkdirSync(dirname(p), { recursive: true }); writeFileSync(p, data); };
// SVG'deki <metadata> (ör. c2pa) atılır: iç içe svg'de tanımsız namespace tauri icon'u bozar, boyutu da şişirir.
const svgClean = (s) => s.replace(/<metadata>[\s\S]*?<\/metadata>/g, '').replace(/\s+xmlns:c2pa="[^"]*"/g, '');
const readSvg = (f) => svgClean(readFileSync(join(dir, f), 'utf8'));

// 1) core: çalışma anı marka bilgisi
write(join(root, 'packages/core/src/brand/current.json'), JSON.stringify({
  ...brand,
  icon: 'brand/icon.svg',
  logo: 'brand/logo.svg',
  logoDark: 'brand/logo-dark.svg',
}, null, 2) + '\n');

// 2) panel: statik dosyalar
const pub = join(root, 'packages/panel/public/brand');
mkdirSync(pub, { recursive: true });
writeFileSync(join(pub, 'icon.svg'), readSvg(brand.icon));
writeFileSync(join(pub, 'logo.svg'), readSvg(brand.logo));
// Koyu tema logosu (yoksa açık logo kullanılır).
writeFileSync(join(pub, 'logo-dark.svg'), readSvg(brand.logoDark || brand.logo));

// 3) desktop: tauri config katmanı (tauri --config ile birleşir)
write(join(root, 'apps/desktop/src-tauri/tauri.brand.conf.json'), JSON.stringify({
  productName: brand.name,
  identifier: brand.bundleId,
  app: { windows: [{ label: 'main', title: brand.name, width: 1280, height: 820, minWidth: 900, minHeight: 600, titleBarStyle: 'Overlay', hiddenTitle: true }] },
  // Otomatik güncelleme: imzalı paket (.app.tar.gz + .sig) yalnız TAURI_SIGNING_PRIVATE_KEY varken üretilir.
  bundle: { createUpdaterArtifacts: !!process.env.TAURI_SIGNING_PRIVATE_KEY },
  plugins: {
    updater: {
      pubkey: readFileSync(join(root, 'apps/desktop/updater.pub'), 'utf8').trim(),
      endpoints: brand.updateUrl ? [`${brand.updateUrl.replace(/\/$/, '')}/v1/fluxapp/${brand.slug}/tauri/{{target}}/{{arch}}/{{current_version}}`] : [],
    },
  },
}, null, 2) + '\n');

// 4) uygulama ikonu: macOS ızgarası (1024 tuvalde 824 px yuvarlak kare, 100 px kenar). Varsayılan beyaz zemin
// ortasında marka ikonu. brand.appIcon = { background: "#FFFFFF" | null, scale: 0.6 } ile değişir;
// background null ise ikon kareyi kendisi doldurur (ikonun kendi zemini varsa).
{
  const opt = { background: '#FFFFFF', scale: 0.6, ...(brand.appIcon || {}) };
  const src = readSvg(brand.icon);
  const vb = (src.match(/viewBox="([^"]+)"/) || [])[1] || '0 0 512 512';
  const inner = src.replace(/^[\s\S]*?<svg[^>]*>/, '').replace(/<\/svg>\s*$/, '').replace(/<title>[\s\S]*?<\/title>/, '');
  const box = 824, off = 100, r = 185;
  const size = opt.background ? Math.round(box * opt.scale) : box;
  const pos = off + (box - size) / 2;
  const clip = opt.background ? '' : ` clip-path="url(#sq)"`;
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1024 1024" width="1024" height="1024">
<defs><clipPath id="sq"><rect x="${off}" y="${off}" width="${box}" height="${box}" rx="${r}"/></clipPath>
<filter id="sh" x="-10%" y="-10%" width="120%" height="125%"><feDropShadow dx="0" dy="10" stdDeviation="14" flood-color="#000" flood-opacity=".22"/></filter></defs>
${opt.background ? `<rect x="${off}" y="${off}" width="${box}" height="${box}" rx="${r}" fill="${opt.background}" filter="url(#sh)"/>
<rect x="${off + 0.5}" y="${off + 0.5}" width="${box - 1}" height="${box - 1}" rx="${r}" fill="none" stroke="#000" stroke-opacity=".06"/>` : ''}
<g${clip}><svg x="${pos}" y="${pos}" width="${size}" height="${size}" viewBox="${vb}" preserveAspectRatio="xMidYMid meet">${inner}</svg></g>
</svg>
`;
  write(join(root, 'apps/desktop/src-tauri/app-icon.svg'), svg);
}

console.log(`[brand] ${brand.name} (${slug}) uygulandı`);
