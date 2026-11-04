# Flux Speed: diğer paketler için (v0.1.0)

## Log
```php
do_action( 'flx_log', 'warning', 'Sertifika kuyruğu {n} iş', array( 'n' => 3 ) ); // emergency|alert|critical|error|warning|notice|info|debug
do_action( 'qm/error', 'Query Monitor uyumlu kanca da çalışır' );
```
`error` ve üstü isteği **Hata logu** işaretiyle kaydettirir. Metin maskelenir (e-posta, telefon, IBAN, anahtar). Flux Speed yoksa kanca hiçbir şey yapmaz; koşul gerekmez.

## Zamanlayıcı
```php
do_action( 'flx_timer_start', 'sertifika-pdf' );
// ...
do_action( 'flx_timer_stop', 'sertifika-pdf' );
```
Panelde Loglar › Zamanlayıcılar'da süre ve bellek.

## Yetki
`flxp_view` (Flux Roles `flx_role_caps_supported` listesinde). Panel, Kayıtlar ve MCP araçları bu yetkiyle; Ayarlar ve "Tüm kayıtları sil" `manage_options`.

## Fonksiyonlar
- `flxp_profile_url( $url )` → `array( id, status, url )` | `WP_Error`. Kendi sitesine tek kullanımlık jetonla GET, istek ayrıntılı kaydedilir.
- `FLXP_Store::summary( $hours )`, `::slow_queries()`, `::php_errors()`, `::http_calls()`, `::query( $args )`, `::get( $id )`.
- `flxp_environment()`, `flxp_server_live()` (CPU yükü, RAM, disk, uptime; /proc okunur, shell_exec yok), `flxp_server_stats()`, `flxp_database( $with_tables )`, `flxp_autoload( $limit )`, `flxp_cron()`.
- `flxp_cleanup( $job )`: `expired_transients | auto_drafts | spam_comments | trashed_comments | optimize` → silinen sayı. İçerik (yazı, revizyon) silen iş yok.
- `flxp_server_checks()`: performans satırları (Özet "Öneriler" ve Başlangıç widget'ı).
- `flxp_system_checks_url()`: Sistem Kontrolü ekranının adresi. Varlığı = ekran Flux Speed'ta.

## MCP araçları
`flxp_summary {hours}`, `flxp_requests {hours, flag, type, url, min_ms, order, limit, page}`, `flxp_request {id, sections[]}`, `flxp_slow_queries {hours, limit}`, `flxp_php_errors {hours, min_level, limit}`, `flxp_http_calls {hours, failed_only, limit}`, `flxp_environment`, `flxp_server`, `flxp_database {with_tables}`, `flxp_autoload {limit}`, `flxp_cron {overdue_only}`, `flxp_profile_url {url}`. Hepsi `readonly`, `cap = flxp_view`.

İşaretler: `slow, error, fatal, 5xx, http_fail, slow_query, db_error, log_error, sample, profile`.

## Sistem Kontrolü
Ekran: **Performans › Sistem Kontrolü** (`admin.php?page=flx-speed&tab=system`). Ortak `flx-system-checks.php` kopyası pakette; `flx_system_checks_page_url` filtresine 30 öncelikle bu adresi verir (tema 20), Başlangıç uyarısı ve üst çubuk buraya bağlanır. Grup anahtarı `Performance` eklendi.

Diğer paketler kendi Sistem Kontrolü ekranlarını şöyle gizleyebilir / yönlendirebilir:
```php
if ( function_exists( 'flxp_system_checks_url' ) ) { /* menü öğesini ekleme, eski adrese gelirse wp_safe_redirect( flxp_system_checks_url() ) */ }
```

Satırlar: `flxp.table`, `flxp.record`, `flxp.errors_24h`, `flxp.slow_24h`, `flxp.http_24h`, `flxp.savequeries`, `flxp.mcp`, `flxp.autoload`, `flxp.cron`, `flxp.disk`, `flxp.load`, `flxp.opcache`, `flxp.transients`.
Düzeltme (`flx_system_fixes`): `flxp_expired_transients` (Flux AI & MCP `system_fix` ile yapay zekâ çalıştırabilir).

## Canlı kutular
Heartbeat: istekte `data.flxp_live = 1` → cevapta `flxp_live` (kutu başına `label, pct, text, sub`). Sunucu/Özet'te 15 sn, Başlangıç'ta 60 sn.

## Kapatma
`define( 'FLXP_DISABLED', true );` (wp-config). WP-CLI isteklerinde zaten çalışmaz.
