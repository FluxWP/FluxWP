# Flux Speed (flx-speed): REST → GraphQL eşlemesi

Paket sürümü: 0.9.0 · Önek: `flxp_` · Sahip thread: "Geçir Flux Speed'i GraphQL'e" · Durum: bitti (PR bekliyor)

## Özet

- REST uçları: 3 (FluxApp, değişmedi) · AJAX: 1 (önbellek sınama probe'u, istisna) · Admin sayfası: 1 (`page=flx-speed`, 10 sekme + kayıt ayrıntısı) · MCP/AI aracı: 12 (`flxp_*`, değişmedi)
- İstisna: giriş akışı (`/fluxadmin`, `FLXP_LOGIN_DISABLE`) ve önbellek davranışı bilerek dokunulmadı; yalnız ayar ve durum ekranları taşındı.
- Servis: `includes/services/class-flxp-speed-service.php` (`FLXP_Speed_Service`); klasik ekran, GraphQL ve MCP aynı servisi kullanır.
- Node tipleri: yok (kayıtlar `FlxSpeedRecord`, `id` = veritabanı kimliği).

## REST uçları

| Yöntem | Yol | cap | → GraphQL | Not |
|---|---|---|---|---|
| GET | `/flx-speed/v1/summary` | `flxp_view` | `flxSpeedSummary` | FluxApp REST'i durur, servise delege |
| GET | `/flx-speed/v1/requests` | `flxp_view` | `flxSpeedRecords` | |
| GET | `/flx-speed/v1/requests/<id>` | `flxp_view` | `flxSpeedRecord` | |

## AJAX

| Action | cap / nonce | → GraphQL | Not |
|---|---|---|---|
| `flxp_cache_probe` (+ `nopriv`) | probe belirteci | İSTİSNA | Nesne önbelleği kalıcılık testinin kendi isteği; kullanıcı arayüzü değil |

## Admin sayfaları

| Sekme | Sorgular / mutation'lar |
|---|---|
| Özet | `flxSpeedSummary`, `flxSpeedLive`, `flxSpeedHints` |
| Kayıtlar + ayrıntı | `flxSpeedRecords` (cursor), `flxSpeedRecord`, `flxSpeedRecordsClear` |
| Sunucu | `flxSpeedServer`, `flxSpeedProfile` |
| Veritabanı | `flxSpeedDatabase`, `flxSpeedCleanup`, `flxSpeedAutoload` |
| Cron | `flxSpeedCron` |
| Sistem Kontrolü | `flxSpeedSystem` |
| PageSpeed | `flxSpeedPageSpeed`, `flxSpeedPageSpeedSave`, `flxSpeedPageSpeedClear` |
| Önbellek | `flxSpeedCache`, `flxSpeedCacheRun` |
| Ayarlar | `flxSpeedSettings`, `flxSpeedSettingsSave` |

İstemci ekranı: `packages/modules/src/speed` (`flxp-speed`, mount `flxp-app`). Kayıt ayrıntısındaki istek paneli mevcut `assets/panel.js`'i gömer.

## MCP / AI araçları

`flxp_summary`, `flxp_requests`, `flxp_request`, `flxp_slow_queries`, `flxp_php_errors`, `flxp_http_calls`, `flxp_environment`, `flxp_server`, `flxp_database`, `flxp_autoload`, `flxp_cron`, `flxp_cache_status`: aynı servis metotları, değişmedi.

## Yetkiler

Okuma (özet, kayıtlar, sunucu, veritabanı, cron) `manage_options` ya da `flxp_view`. Sistem, Ayarlar, PageSpeed ve Önbellek `manage_options`; `INSTALL_*` önbellek işlemleri ayrıca `install_plugins`. Katalog: `flx_gql_capabilities` ile `flxp_view` bildirilir.
