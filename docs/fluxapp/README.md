# FluxWP

WordPress siteleri için çok platformlu yönetim uygulaması. Geçici ad; ad, logo ve renkler `brands/` içinden değişir.

| Platform | Kod |
|---|---|
| Web + macOS + Windows | `packages/panel` (React), masaüstünde `apps/desktop` (Tauri 2) sarar |
| iOS + Android | ayrı repo (Expo), ortak `@fluxwp/core` |

Mimari: [MIMARI.md](MIMARI.md). Kod repo kökünde: `packages/`, `apps/desktop`, `brands/`, `tools/fluxapp/`.

## Geliştirme
```bash
bun install           # marka dosyalarını da üretir
bun run dev           # panel tarayıcıda (http://localhost:5173), "Demo ile dene"
bun run desktop:dev   # Tauri (macOS: Xcode CLT + Rust gerekir)
bun run test && bun run typecheck
```

## Marka (white-label)
```bash
FLUX_BRAND=<marka> bun run desktop:build
```
`brands/<slug>/brand.json`: `name`, `bundleId`, `scheme`, `colors`, `icon`, `logo`, `defaultSite`, `lockSite`.
`loginMethods`: giriş ekranındaki yollar (`oauth`, `apikey`). `lockSite: true` olan build doğrudan `defaultSite`'a bağlanır, site ekleme gizlenir.

## Sunucu
Flux App eklentisi (`flxapp/v1`): OAuth + PKCE, `/me` (rol bazlı menü, modüller), `/dashboard`, `/session`. Yerel ekranlar `flxapp_modules` ile kaydolur.

## macOS imza ve notarize
`fluxapp-desktop.yml` secret'lar varsa imzalı + notarize dmg üretir, yoksa imzasız. secret'lar repo başınadır (Settings → Secrets → Actions).

| Secret | Ne |
|---|---|
| `APPLE_CERTIFICATE` | **Developer ID Application** sertifikası, .p12 dosyasının base64'ü (`base64 -i cert.p12 \| pbcopy`). TestFlight'ın "Apple Distribution" sertifikası dmg için geçmez. |
| `APPLE_CERTIFICATE_PASSWORD` | .p12 şifresi |
| `APPLE_SIGNING_IDENTITY` | İsteğe bağlı, ör. `Developer ID Application: Ad (TEAMID)` |
| `APPLE_API_ISSUER`, `APPLE_API_KEY`, `APPLE_API_KEY_P8` | Notarize için App Store Connect API anahtarı (Issuer ID, Key ID, .p8 içeriği). Flutter'daki API anahtarı aynen kullanılabilir. |
| `APPLE_ID`, `APPLE_PASSWORD`, `APPLE_TEAM_ID` | API anahtarı yerine Apple ID ile notarize (uygulamaya özel şifre). |
| `TAURI_SIGNING_PRIVATE_KEY` | Otomatik güncelleme imza anahtarı (minisign, `tauri signer generate`). Açık anahtarı `apps/desktop/updater.pub`'da. Yoksa güncelleme paketi üretilmez, uygulama yeni sürümün dmg'sini açar. |
| `TAURI_SIGNING_PRIVATE_KEY_PASSWORD` | Anahtarın şifresi (şifresizse boş). |

## Otomatik güncelleme
Uygulama `brand.updateUrl` (wp-update-server ≥ 1.4.0) üzerinden `fluxapp-v<sürüm>` release'lerini denetler: açılışta ve 6 saatte bir. İmzalı `.app.tar.gz` varsa arka planda indirir, sol altta "Yeniden başlat ve güncelle" gösterir; yoksa dmg'yi açar. Depo private olduğu için dosyalar güncelleme sunucusundan geçer (token istemciye gitmez). Anahtar değişirse eski sürümler yeni paketi kabul etmez; anahtarı kaybetmeyin.
