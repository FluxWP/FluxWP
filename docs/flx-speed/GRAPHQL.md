# Flux Speed GraphQL API

Uç: `/graphql` (Flux GraphQL). Okuma alanları `manage_options` ya da `flxp_view`; Sistem, Ayarlar, PageSpeed ve Önbellek `manage_options`; `INSTALL_*` önbellek işlemleri ayrıca `install_plugins`.
İşlemler `plugins/flx-speed/graphql/operations/*.graphql`, tipleri `@fluxwp/graphql` (`FlxSpeed*`).

## Okuma

| Sorgu | Girdi | Not |
|---|---|---|
| `flxSpeedConfig` | | sürüm, sekmeler, türler, bayraklar |
| `flxSpeedSummary` | `hours` (1–720) | özet kartları ve tablolar |
| `flxSpeedLive` / `flxSpeedHints` | | canlı durum ve ipuçları |
| `flxSpeedRecords` | `first` (≤50), `after`, `hours`, `flag`, `type`, `q`, `slow` | cursor sayfalama |
| `flxSpeedRecord` | `id` | ayrıntı + istek paneli verisi |
| `flxSpeedServer` | | bölümler |
| `flxSpeedDatabase`, `flxSpeedAutoload`, `flxSpeedCron` | | |
| `flxSpeedSystem` | | ortak Sistem Kontrolü + son AI düzeltmeleri |
| `flxSpeedSettings` | | ayarlar + `loginMovedUrl` |
| `flxSpeedPageSpeed`, `flxSpeedCache` | | |

Toplam 14 sorgu. Kayıt türü `graphql` (aşağıda) süzgeçte `type: "graphql"` ile seçilir.

## Yazma

| Mutation | Girdi | Not |
|---|---|---|
| `flxSpeedSettingsSave` | `FlxSpeedSettingsInput` | verilmeyen alan olduğu gibi kalır |
| `flxSpeedPageSpeedSave` | PageSpeed ayarları | |
| `flxSpeedPageSpeedClear` | | kırpılmış kopyaları temizler |
| `flxSpeedCacheRun` | `op`: `PAGE_ON`, `PAGE_OFF`, `OBJECT_OFF`, `PURGE`, `FLUSH`, `TEST`, `REPAIR`, `USE_*`, `ACTIVATE_*`, `INSTALL_*` | `INSTALL_*` ayrıca `install_plugins` |
| `flxSpeedRecordsClear` | | tüm kayıtları siler |
| `flxSpeedCleanup` | `job` | veritabanı temizliği |
| `flxSpeedProfile` | `url` | profil çıkarır |

Toplam 7 mutation. Hatalar payload `errors` içinde (`code`, `message`, `field`).

## Davranış notları

- **Önbellek ve güvenlik kuralları**: `/graphql` isteği Flux Speed'in giriş gizleme ve sayfa iyileştirme (PageSpeed) kurallarına takılmaz. `front_request()` GraphQL'i dışlar; istek kayıt türü `graphql` olur. Ekranın kendi sorguları (`flxSpeed*`) kayıt altına alınmaz, böylece Kayıtlar ekranı kendini doldurmaz. Flux GraphQL'in kendi `X-LiteSpeed-Tag` önbelleği Flux Speed'in Tümünü temizle işlemiyle birlikte temizlenir.
- **Özel giriş adresi**: `/fluxadmin` ve `FLXP_LOGIN_DISABLE` akışına dokunulmadı. Yalnız Ayarlar ekranındaki "taşınmış giriş" bağlantısı servisten `loginMovedUrl` olarak gelir.
- **Klasik ayar kaydı**: klasik form tüm alanları gönderir; GraphQL `flxSpeedSettingsSave` yalnız verilen alanları yazar (kısmi kayıt). Sınırlar iki yolda da aynıdır (servis kısıtlar).
- **FluxApp**: `app/manifest.json` kaldırıldı; modül `flxapp_modules` filtresiyle kaydolur, REST uçları aynı kalır.
- **wp-admin**: React ekranı varsayılandır; klasik ekran sayfadaki bağlantıyla (kullanıcı meta `flxp_ui`) açılır.

## PHP

Servis: `FLXP_Speed_Service` (`config`, `summary`, `records`, `record`, `server_sections`, `database`, `cleanup`, `autoload`, `cron`, `system`, `settings`, `settings_save`, `pagespeed`, `pagespeed_save`, `pagespeed_clear`, `cache`, `cache_run`). Kit yardımcıları servisten çağrılmaz; yalnız `graphql/schema.php` kullanır.
