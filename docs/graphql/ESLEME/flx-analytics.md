# Flux Analytics (flx-analytics): klasik → GraphQL eşlemesi

Paket sürümü: 0.5.0 · Önek: `flxan_` · Sahip thread: Faz 1 / flx-analytics · Durum: bitti (PR `gql/flx-analytics-1`)

## Özet

- REST uçları: 10 (`flxan/v1`: c, report, list, realtime, funnel, journeys, refs, refs/save, page, fast-test) + `dashboard` + telemetri `flxan/v1/telemetry` · AJAX: 0 · admin-post işleyici: 6 · Admin sayfası: 4 · MCP aracı: 9 (`flxan_*`)
- İstisnalar (kalıcı, `ISTISNALAR.md`): ziyaret toplama (POST `/flxan/v1/c`), telemetri alımı (`/flxan/v1/telemetry`), CSV indirme (`flxan_export`), WP Pano widget'ları + `/flxan/v1/dashboard`.
- Servisler: `includes/services/class-flxan-service.php` (`FLXAN_Service`), `class-flxan-telemetry-service.php`. Okuma katmanı `FLXAN_Stats` / `FLXAN_Journeys` aynı kalır; servis tipli sarmalayıcıdır. REST ve MCP davranışı değişmedi.
- Rapor sorguları hafif: depolanmış günlük toplamlardan okur, ham tablo taramaz (Hostinger ~60 sn PHP sınırı, Redis yok).

## REST uçları

| Uç | cap | → GraphQL |
|---|---|---|
| `GET /report` | `flxan_view` | `flxAnalyticsReport(range, filters)` |
| `GET /list` | `flxan_view` | `flxAnalyticsPages`, `flxAnalyticsReferrers`, `flxAnalyticsBreakdown`, `flxAnalyticsEvents` |
| `GET /realtime` | `flxan_view` | `flxAnalyticsRealtime` |
| `GET /funnel` | `flxan_view` | `flxAnalyticsFunnel` |
| `GET /journeys` | `flxan_view` | `flxAnalyticsJourneys` |
| `GET /refs` | `edit_posts` | `flxAnalyticsRefs` |
| `POST /refs/save` | `edit_posts` | `flxAnalyticsRefSave` (REST 400 `flxan_bad_code` aynı) |
| `GET /page` | `flxan_view` | `flxAnalyticsPage` |
| `POST /fast-test` | `manage_options` | `flxAnalyticsFastTest` |
| `POST /c` | herkese açık | İSTİSNA: ziyaret toplama |
| `POST /telemetry` | anahtarlı | İSTİSNA: telemetri alımı |
| `GET /dashboard` | `flxan_view` | İSTİSNA: WP Pano widget'ı |

## admin-post işleyicileri

| Action | → GraphQL | Not |
|---|---|---|
| `flxan_save` | `flxAnalyticsSettingsSave` | Ayarlar ve huniler |
| `flxan_aggregate` | `flxAnalyticsAggregate` | |
| `flxan_reset` | `flxAnalyticsReset` | Geri alınamaz |
| `flxan_import_koko` | `flxAnalyticsKokoImport` | |
| `flxan_tm_forget` | `flxAnalyticsTelemetryForget` | Yalnız telemetri merkezinde |
| `flxan_export` | — | İSTİSNA: CSV indirme (akış) |

## Admin sayfaları

| Sayfa | İstemci ekranı |
|---|---|
| `flx-analytics` (İstatistikler) | `packages/modules/src/analytics/StatsScreen.tsx` (`flxan-stats`) |
| `flx-analytics-journeys` | `JourneysScreen.tsx` (`flxan-journeys`) |
| `flx-analytics-settings` | `SettingsScreen.tsx` (`flxan-settings`) |
| `flx-analytics-telemetry` | `TelemetryScreen.tsx` (`flxan-telemetry`) |

Hepsi wp-admin varsayılanı; klasik görünüme bağlantı kullanıcı başına (`flxan_ui` meta).

## Önbellek tag'leri

`flx-analytics:stats`: tüm rapor, liste ve ayar sorguları okur; ayar/huni/referans kaydı, toplama, sıfırlama ve içe aktarma mutation'ları temizler.

## Açık sorular

- Klasik ekran ne zaman kaldırılacak (pilotla aynı karar).
