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
bun run desktop:dev   # Tauri (macOS: Xcode CLT + Rust; Windows: MSVC Build Tools + Rust)
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

## Derleme ve yayın (macOS + Windows)
`.github/workflows/fluxapp-desktop.yml` iki işi paralel çalıştırır ve dosyaları tek release'e koyar:

| İş | Runner | Çıktı |
|---|---|---|
| macOS | `macos-latest` | `fluxapp-<marka>-<sürüm>-macos-universal.dmg` (Apple Silicon + Intel), imzalı güncelleme paketi `.app.tar.gz` + `.sig` |
| Windows | `windows-latest` | `fluxapp-<marka>-<sürüm>-windows-x64-setup.exe` (NSIS, WebView2'yi gerekirse kendisi kurar), `-setup.exe.sig`, `.msi` (ön sürümde msi yok) |

Yayın: Actions › Flux Desktop › Run workflow, `release` = `fluxapp-v<sürüm>` (tag push da çalışır). `platforms` ile tek platform derlenebilir. Release yalnız iki iş de başarılıysa açılır.

İndirme bağlantıları kalıcıdır, hep son sürümü verir (Flux Update Server ≥ 1.9.7):
`https://updater.fluxwp.com/v1/fluxapp/<marka>/download/macos`, `…/windows`, `…/windows-msi`. Flux App eklentisi (≥ 0.3.8) Kullanıcılar › Uygulama erişimi'nde bu düğmeleri gösterir.

Uygulama ikonu `brands/<marka>/icon.svg`'den üretilir (`tools/fluxapp/brand.mjs`): macOS ızgarası (`app-icon.svg`) ve neredeyse tam dolu Windows simgesi (`app-icon-windows.svg` → `icon.ico`).

## İmza secret'ları
Secret yoksa o platform imzasız derlenir, iş uyarı düşer ama durmaz. Settings › Secrets and variables › Actions.

### macOS (imza + notarize)
| Secret | Ne |
|---|---|
| `APPLE_CERTIFICATE` | **Developer ID Application** sertifikası, .p12 dosyasının base64'ü (`base64 -i cert.p12 \| pbcopy`). TestFlight'ın "Apple Distribution" sertifikası dmg için geçmez. |
| `APPLE_CERTIFICATE_PASSWORD` | .p12 şifresi |
| `APPLE_SIGNING_IDENTITY` | İsteğe bağlı, ör. `Developer ID Application: Ad (TEAMID)` |
| `APPLE_API_ISSUER`, `APPLE_API_KEY`, `APPLE_API_KEY_P8` | Notarize için App Store Connect API anahtarı (Issuer ID, Key ID, .p8 içeriği). |
| `APPLE_ID`, `APPLE_PASSWORD`, `APPLE_TEAM_ID` | API anahtarı yerine Apple ID ile notarize (uygulamaya özel şifre). |

İmzasız dmg: kullanıcı ilk açılışta sağ tık › Aç demeli (Gatekeeper).

### Windows (Authenticode)
İki yoldan biri. İkisi de yoksa imzasız; SmartScreen "Windows bilgisayarınızı korudu" der, "Ek bilgi › Yine de çalıştır" ile açılır.

| Ad | Tür | Ne |
|---|---|---|
| `AZURE_TENANT_ID`, `AZURE_CLIENT_ID`, `AZURE_CLIENT_SECRET` | secret | **Azure Trusted Signing** (önerilen, aylık ücretli, SmartScreen itibarı hızlı). Uygulama kaydı (service principal), hesapta "Trusted Signing Certificate Profile Signer" rolü. |
| `AZURE_SIGNING_ENDPOINT`, `AZURE_SIGNING_ACCOUNT`, `AZURE_SIGNING_PROFILE` | variable | Bölge adresi (ör. `https://weu.codesigning.azure.net`), Trusted Signing hesap adı, sertifika profili adı. |
| `WINDOWS_CERTIFICATE`, `WINDOWS_CERTIFICATE_PASSWORD` | secret | Alternatif: dışa aktarılabilir .pfx sertifika (base64) ve şifresi. 2023'ten sonra verilen OV/EV sertifikalar donanımda tutulur, .pfx olarak alınamaz; o durumda Azure yolu. |

### Otomatik güncelleme (iki platform)
| Secret | Ne |
|---|---|
| `TAURI_SIGNING_PRIVATE_KEY` | Güncelleme imza anahtarı (minisign, `bun tauri signer generate`). Açık anahtar `apps/desktop/updater.pub`. Yoksa güncelleme paketi/imzası üretilmez, uygulama yeni sürümün dmg/setup.exe'sini açar. |
| `TAURI_SIGNING_PRIVATE_KEY_PASSWORD` | Anahtarın şifresi (şifresizse boş). |

## Otomatik güncelleme
Uygulama `brand.updateUrl` (wp-update-server ≥ 1.4.0) üzerinden `fluxapp-v<sürüm>` release'lerini denetler: açılışta ve 6 saatte bir. İmzalı paket varsa (macOS `.app.tar.gz`, Windows `setup.exe`) arka planda indirir, sol altta "Yeniden başlat ve güncelle" gösterir; yoksa dmg'yi / setup.exe'yi açar. Depo private olduğu için dosyalar güncelleme sunucusundan geçer (token istemciye gitmez). Anahtar değişirse eski sürümler yeni paketi kabul etmez; anahtarı kaybetmeyin.
