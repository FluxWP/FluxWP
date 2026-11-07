# White-label

One FluxWP codebase, many branded apps. The main app stays a single build: bundle id `com.fluxwp.app`, scheme `fluxwp`, OAuth client `flx-mobil`, `allowedSites: []` (any Flux App site). A brand build is a second app. It can only add the sites named in its preset.

The source of truth is `brands/<slug>/brand.json`. The same file feeds the Expo app and the Tauri desktop config.

## Brand file

| Field | Role |
|---|---|
| `name`, `slug`, `shortName` | Store name and folder. `slug` matches `brands/<slug>/`. |
| `bundleId`, `scheme` | Desktop bundle id and desktop URL scheme. |
| `ios.bundleIdentifier`, `android.package` | Mobile ids. A restricted brand cannot use `com.fluxwp.app`. |
| `mobile.scheme`, `mobile.oauthClientId` | Mobile URL scheme and OAuth client (`<scheme>://oauth/callback`). |
| `oauthClientId` | Desktop client (`flx-masaustu-<slug>`). |
| `icon`, `logo`, `logoDark` | SVG files in the brand folder. |
| `mobile.splash` | Splash background, light and dark. |
| `colors`, `theme` | Brand color and theme overrides (layer 2). |
| `shell` | Default tabs, drawer and top bar. |
| `locked` | Config paths the brand wins (`theme.light.primary`, `shell.tabs`, …). |
| `allowedSites` | Hosts, or `*.example.org` (the apex itself is not included). Empty = any site. |
| `defaultSite`, `lockSite` | Prefilled address. `lockSite: true` hides the site field and opens `defaultSite`. |
| `loginMethods` | `oauth`, `otp`, `apikey`, `apppass`. |
| `eas.projectId`, `eas.updatesChannel` | Expo project UUID (public) and OTA channel. `null` until the project is linked. |
| `store` | App Store id and Play URL. Optional. |

`allowedSites` with `lockSite: false` lets the user pick a site from the list (and type a matching subdomain when the entry is a wildcard). `lockSite: true` is a single-site app. The example brand (`brands/example/`) is fictional: `north.example.org` and `south.example.org`, client `flx-mobil-example`. It is not a customer.

No secrets in the repo. Do not put `EXPO_TOKEN`, Apple keys, Google service accounts or private keys in `brand.json`. `eas.projectId` and `ios.appleTeamId` are public identifiers; leave them `null` until you have them. `EXPO_TOKEN` lives in the GitHub secret and in your shell. Apple and Google credentials stay in EAS.

## Commands

```bash
bun run brand:new <slug>          # brands/<slug>/ + EAS profiles prod-<slug> and preview-<slug>
FLUX_BRAND=<slug> bun run mobile:icons
bun run brand:build <slug> --prepare
EXPO_TOKEN=… bun run brand:build <slug> --platform all --profile production
bun run brand:pull https://site.example --slug <slug>
```

`brand:pull` calls `GET /wp-json/flxapp/v1/app/brand` (requires `manage_options` on flx-app 0.4.0). Set `FLXAPP_TOKEN` or `FLXAPP_USER` and `FLXAPP_APP_PASSWORD`. The token is not written into `brand.json`. Review the draft, then commit it.

`brand:build` runs `tools/fluxapp/brand.mjs` (desktop) and `apps/mobile/scripts/brand.cjs` (Expo), then `eas build` with profile `production` / `preview` for `flux` and `prod-<slug>` / `preview-<slug>` otherwise. `--prepare` stops after writing configs.

EAS reads `FLUX_BRAND` from the profile. `eas-build-pre-install` applies the brand before install. GitHub Actions: `.github/workflows/mobile-eas-build.yml` and `mobile-eas-update.yml` (`workflow_dispatch`).

Check a config without uploading:

```bash
cd apps/mobile
bunx expo config --type public
FLUX_BRAND=example bunx expo config --type public
```

## What the app enforces

`apps/mobile` loads the generated brand and calls `brandGate` (`@fluxwp/runtime`) before any discovery request. A host outside `allowedSites` is rejected on the device (`site_not_allowed`) and the demo account is hidden. The server lock is the brand's own OAuth client: preset `flxapp_settings.app_clients` registers `flx-mobil-<slug>` and `flx-masaustu-<slug>` only on the sites that brand may use. The generic clients `flx-mobil` and `flx-masaustu` exist on every Flux App site and belong to the main app only.

Theme and `shell` are config layer 2. Paths in `locked` override the site and the user.

## Desktop

`bun run brand` (`FLUX_BRAND`, default `flux`) writes `apps/desktop/src-tauri/tauri.brand.conf.json`: product name, bundle id, window title and the updater URL. Desktop builds already pass that file (`--config src-tauri/tauri.brand.conf.json`). The same run writes `allowedSites` and `lockSite` into the shared brand object the panel reads.

The desktop panel already hides site adding when `lockSite` is true. It does not yet reject an allow-list host in the address field; that UI lands with the panel migration. Until then, the server still refuses the brand's OAuth client on any other site. Tauri has no deep-link plugin in this repo, so the desktop URL scheme is stored on the brand and documented here rather than injected into `tauri.conf.json`.

---

# Beyaz etiket

Tek FluxWP kodu, birden çok markalı uygulama. Ana uygulama tek derlemedir: bundle `com.fluxwp.app`, şema `fluxwp`, OAuth istemcisi `flx-mobil`, `allowedSites: []` (Flux App kurulu her site). Marka derlemesi ayrı bir uygulamadır ve yalnız preset'inde yazılı siteleri ekler.

Kaynak `brands/<slug>/brand.json`. Aynı dosya Expo uygulamasını ve Tauri masaüstü yapılandırmasını besler.

## Marka dosyası

| Alan | Ne işe yarar |
|---|---|
| `name`, `slug`, `shortName` | Mağaza adı ve klasör. `slug`, `brands/<slug>/` ile aynıdır. |
| `bundleId`, `scheme` | Masaüstü bundle kimliği ve masaüstü URL şeması. |
| `ios.bundleIdentifier`, `android.package` | Mobil kimlikler. Kısıtlı bir marka `com.fluxwp.app` kullanamaz. |
| `mobile.scheme`, `mobile.oauthClientId` | Mobil şema ve OAuth istemcisi (`<şema>://oauth/callback`). |
| `oauthClientId` | Masaüstü istemcisi (`flx-masaustu-<slug>`). |
| `icon`, `logo`, `logoDark` | Marka klasöründeki SVG dosyaları. |
| `mobile.splash` | Açılış zemini, açık ve koyu. |
| `colors`, `theme` | Marka rengi ve tema override'ı (2. katman). |
| `shell` | Varsayılan sekmeler, yan menü ve üst bar. |
| `locked` | Markanın kazandığı config yolları (`theme.light.primary`, `shell.tabs`, …). |
| `allowedSites` | Host ya da `*.example.org` (köke dahil değildir). Boş = her site. |
| `defaultSite`, `lockSite` | Önceden dolu adres. `lockSite: true` site alanını gizler ve `defaultSite` açar. |
| `loginMethods` | `oauth`, `otp`, `apikey`, `apppass`. |
| `eas.projectId`, `eas.updatesChannel` | Expo proje UUID'si (herkese açık) ve OTA kanalı. Proje bağlanana kadar `null`. |
| `store` | App Store kimliği ve Play adresi. İsteğe bağlı. |

`allowedSites` dolu ve `lockSite: false` ise kullanıcı listeden site seçer (joker varsa eşleşen alt alan adını da yazabilir). `lockSite: true` tek sitelik uygulamadır. Örnek marka `brands/example/` kurmacadır: `north.example.org` ve `south.example.org`, istemci `flx-mobil-example`. Müşteri verisi yoktur.

Repoda gizli bilgi yok. `EXPO_TOKEN`, Apple anahtarı, Google servis hesabı ya da özel anahtar `brand.json`'a yazılmaz. `eas.projectId` ve `ios.appleTeamId` herkese açık kimliklerdir; sizde yoksa `null` kalır. `EXPO_TOKEN` GitHub secret'ında ve kabuk ortamında durur. Apple ve Google kimlikleri EAS'ta kalır.

## Komutlar

```bash
bun run brand:new <slug>          # brands/<slug>/ + EAS profilleri prod-<slug> ve preview-<slug>
FLUX_BRAND=<slug> bun run mobile:icons
bun run brand:build <slug> --prepare
EXPO_TOKEN=… bun run brand:build <slug> --platform all --profile production
bun run brand:pull https://site.example --slug <slug>
```

`brand:pull`, `GET /wp-json/flxapp/v1/app/brand` çağırır (flx-app 0.4.0, `manage_options`). `FLXAPP_TOKEN` ya da `FLXAPP_USER` ve `FLXAPP_APP_PASSWORD` verin. Belirteç `brand.json`'a yazılmaz. Taslağı gözden geçirip öyle commit edin.

`brand:build` önce `tools/fluxapp/brand.mjs` (masaüstü) ve `apps/mobile/scripts/brand.cjs` (Expo) çalıştırır, sonra `eas build` başlatır. `flux` için profil `production` / `preview`, diğer markalar için `prod-<slug>` / `preview-<slug>`. `--prepare` yapılandırmayı yazar, yüklemez.

EAS, profildeki `FLUX_BRAND` değerini okur. `eas-build-pre-install` kurulmadan önce markayı uygular. GitHub Actions: `.github/workflows/mobile-eas-build.yml` ve `mobile-eas-update.yml` (`workflow_dispatch`).

Yüklemeden kontrol:

```bash
cd apps/mobile
bunx expo config --type public
FLUX_BRAND=example bunx expo config --type public
```

## Uygulama neyi zorlar

`apps/mobile` üretilen markayı yükler ve keşif isteğinden önce `brandGate` (`@fluxwp/runtime`) çağırır. `allowedSites` dışındaki host cihazda reddedilir (`site_not_allowed`); demo hesabı da gizlenir. Sunucu kilidi markanın kendi OAuth istemcisidir: preset `flxapp_settings.app_clients` içinde `flx-mobil-<slug>` ve `flx-masaustu-<slug>` yalnız o markanın sitelerinde kayıtlıdır. Genel `flx-mobil` ve `flx-masaustu` her Flux App sitesinde vardır ve yalnız ana uygulamaya aittir.

Tema ve `shell` 2. katmandır. `locked` yolları site ve kullanıcı ayarını ezer.

## Masaüstü

`bun run brand` (`FLUX_BRAND`, varsayılan `flux`) `apps/desktop/src-tauri/tauri.brand.conf.json` yazar: ürün adı, bundle kimliği, pencere başlığı ve güncelleme adresi. Masaüstü derlemesi bu dosyayı zaten verir (`--config src-tauri/tauri.brand.conf.json`). Aynı çalıştırma `allowedSites` ve `lockSite` değerlerini panelin okuduğu ortak marka nesnesine yazar.

Masaüstü paneli `lockSite: true` iken site eklemeyi gizler. Adres alanındaki allow-list kontrolü henüz yoktur; o arayüz panel göçüyle gelir. O zamana kadar başka bir sitede markanın OAuth istemcisini sunucu reddeder. Bu repoda Tauri deep-link eklentisi yok; masaüstü URL şeması marka dosyasında durur, `tauri.conf.json` içine enjekte edilmez.
