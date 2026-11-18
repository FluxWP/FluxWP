# Mobile OTA updates (self-hosted, no EAS)

**English** · [Türkçe](#türkçe)

The mobile app (`apps/mobile`, `expo-updates`) downloads JS and asset updates from our own update server ([FluxWP/updater](https://github.com/FluxWP/updater), Expo Updates protocol v1). EAS Update is not used.

```
GitHub Actions "Mobile OTA update"
  expo export → manifest per platform → signed with FLUX_OTA_PRIVATE_KEY
  → POST <server>/v1/expo/<brand>/{assets,publish}   (FLUX_OTA_PUBLISH_KEY)
update server stores the signed bytes, never the private key
  ← GET <server>/v1/expo/<brand>/manifest   (expo-platform, expo-runtime-version, expo-channel-name)
app verifies the signature with brands/<brand>/ota-certificate.pem
```

- **Silent:** checked on launch, downloaded in the background, applied on the next cold start (`checkAutomatically: ON_LOAD`, `fallbackToCacheTimeout: 0`). A launch crash in a new update makes expo-updates fall back to the previous one.
- **Runtime:** `runtimeVersion: { policy: 'fingerprint' }`. An update reaches only store/TestFlight builds with the same native fingerprint. Release workflows print the runtime in their summary; the OTA workflow warns when no device has asked for that runtime yet. Config inputs that change the fingerprint (`APPLE_TEAM_ID` on iOS, `EXPO_PUBLIC_EAS_PROJECT_ID`, the channel) are the same in both workflows.
- **Channel:** `brand.ota.channel` (default `production`), or `FLUX_OTA_CHANNEL` at build time. Sent as the `expo-channel-name` header.
- **Code signing is required.** Without `brands/<brand>/ota-certificate.pem` the build has `updates.enabled: false` and always runs its embedded bundle.

## Beta: selected devices and users first

1. Publish with channel `beta` (Actions › Mobile OTA update › channel `beta`).
2. Update server › Mobile OTA updates › **Beta**: pick devices (the device code is under Settings in the app) and/or list users as `site#userID` (e.g. `fluxwp.com#1`). The same lists are in the admin API (`ota_beta_device`, `ota_beta_accounts`).
3. Those devices get the beta update on their next launch; everyone else stays on `production`. A beta device on a runtime without a beta update gets `production`.
4. **Promote to stable** serves the same signed beta update on `production`. No new build or signature.

The app sends `flux-device` (random code, new on reinstall), `flux-device-name` (model + OS) and `flux-accounts` (`site#userId` of signed-in accounts, at most 10; no names or e-mails) with each update check (`src/platform/ota.ts`). The server keeps device records 180 days.

## One-time setup

1. Key pair (on your machine, not in the repo):
   `cd apps/mobile && npx expo-updates codesigning:generate --key-output-directory ../../../ota-keys --certificate-output-directory ../../brands/flux --certificate-validity-duration-years 10 --certificate-common-name "FluxWP OTA"`
   then rename `brands/flux/certificate.pem` → `brands/flux/ota-certificate.pem` and commit it.
2. GitHub secret `FLUX_OTA_PRIVATE_KEY` = contents of `ota-keys/private-key.pem`. Keep a backup offline; losing it means a new store build for a new certificate.
3. Update server › **Mobile OTA updates** › *Generate publish key* → GitHub secret `FLUX_OTA_PUBLISH_KEY`. Optionally paste the certificate there so a wrong-key publish fails on the server.
4. Ship one store/TestFlight build (expo-updates is a native module; older builds never ask).
5. Optional repository variable `FLUX_OTA_URL` when the server is not `https://updater.fluxwp.com`.

## Publish / roll back

Actions › **Mobile OTA update** › brand, channel, `action`:

- `publish` — exports iOS + Android and makes it the served update.
- `rollback` — signed `rollBackToEmbedded`: devices return to the bundle inside their store build.

The update server panel lists brand › channel › platform › runtime with the served update, the last device check and the last 8 updates; *Apply* serves an earlier signed update again or pauses (no update offered).

---

## Türkçe

Mobil uygulama JS ve görsel güncellemelerini EAS yerine kendi güncelleme sunucumuzdan alır (Expo Updates protokolü v1). Güncelleme sessizdir: açılışta arka planda iner, sonraki açılışta devreye girer. İmza CI'da atılır, sunucuda özel anahtar durmaz; uygulama `brands/<marka>/ota-certificate.pem` ile doğrular, dosya yoksa OTA kapalıdır.

Kurulum: yukarıdaki 1–5 adımı. Yayın: Actions › **Mobile OTA update** (`publish` ya da `rollback`). Güncelleme yalnız aynı yerel parmak izine (runtime) sahip mağaza/TestFlight yapılarına gider; yerel modül değişince önce yeni yapı gönderilir. Sunucu panelinde geçmişteki imzalı bir güncellemeye dönülebilir ya da sunum durdurulabilir.

Beta: `beta` kanalına yayınla, panelde cihaz (uygulamada Ayarlar altında cihaz kodu) ya da kullanıcı (`site#kullanıcıNo`) seç; onlar önce alır, diğerleri kararlıda kalır. "Kararlıya al" aynı imzalı güncellemeyi herkese açar. Uygulama her kontrolde rastgele cihaz kodu, model ve hesapların `site#kullanıcıNo` bilgisini gönderir (ad/e-posta yok).
