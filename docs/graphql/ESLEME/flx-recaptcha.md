# Flux reCAPTCHA (flx-recaptcha): klasik → GraphQL eşlemesi

Paket sürümü: 0.3.0 · Önek: `flxrc_` · Sahip thread: Faz 1 / flx-recaptcha · Durum: bitti (PR `gql/flx-recaptcha-1`)

## Özet

- REST uçları: 0 (korunan formların REST yolları doğrulama kancasıdır, ayrı uç değil) · AJAX: 1 · admin-post işleyici: 1 · Admin sayfası: 1 · MCP/AI aracı: 0
- İstisna: yok.
- Servisler: `includes/services/class-flxrc-settings-service.php` (`FLXRC_Settings_Service`), `class-flxrc-captcha-service.php` (`FLXRC_Captcha_Service`, başka paketlerin kullandığı doğrulama), DTO'lar `class-flxrc-dto.php`.
- Node tipleri: yok.
- Eski `app/manifest.json` köprüsü (`flxrc_settings`) kaldırıldı; FluxApp modülü `flxrc-recaptcha` GraphQL kullanır.

## AJAX / admin-post

| Action | cap / nonce | → GraphQL | Not |
|---|---|---|---|
| `flxrc_save` (admin-post) | `manage_options` / `flxrc_save` | `flxRecaptchaSettingsUpdate` | Doğrula, sonra yaz (yarım kayıt yok). Anahtar/sürüm değişince koruma testten sonra açılır. |
| `flxrc_test` (AJAX) | `manage_options` / `flxrc_test` | `flxRecaptchaKeysTest` | Google sahte (mock) ile sözleşme testinde doğrulanır. |

## Okuma

| Sorgu | cap | Not |
|---|---|---|
| `flxRecaptchaSettings` | `manage_options` | Gizli anahtar yok: `secretSaved`, `secretHint`, `secretLocked`. |
| `flxRecaptchaForms` | `manage_options` | Bağlam + son 30 gün sayıları. |
| `flxRecaptchaStatus` | `manage_options` | Durum, son test, son Google hatası. |
| `flxCaptcha(context)` | `public` | Ziyaretçi düzeyi, önbelleğe alınır (IP/oturum yok). |

## Admin sayfaları

| Sayfa | Ne gösterir | İstemci ekranı |
|---|---|---|
| `options-general.php?page=flx-recaptcha` | KPI'lar, anahtarlar ve Google testi, korunan formlar, ayarlar, IP listesi | `packages/modules/src/recaptcha/RecaptchaScreen.tsx` (`flxrc-recaptcha`), wp-admin varsayılanı; "Klasik görünüm" bağlantısı kullanıcı başına (`flxrc_ui` meta). |

## Tipler

| Tip | Nullable alanlar ve nedeni |
|---|---|
| `FlxRecaptchaSettings` | — |
| `FlxRecaptchaForm`, `FlxRecaptchaStatus` | `lastTest`, `lastError`: kayıt yoksa |
| `FlxRecaptchaTestResult` | `score`: yalnız v3 |
| `FlxCaptchaConfig` | `siteKey`, `action`, `scriptUrl`: form korumasızsa |

## Önbellek tag'leri

| Tag | Okuyan | Temizleyen |
|---|---|---|
| `flx-recaptcha:config` | `flxCaptcha` | `flxrc_settings` seçeneği değişince (`FLXRC_Settings_Service::changed()`) |

## Açık sorular

- Klasik ekran ne zaman kaldırılacak (pilotla aynı karar: bir süre erişilebilir).
