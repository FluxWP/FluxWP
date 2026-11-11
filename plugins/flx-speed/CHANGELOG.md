# Değişiklikler

## 0.6.6
- Hız API'si ve özel giriş adresi ortak önbelleksiz başlıkları kullanır. Eski Expires başlığı durur.

## 0.6.5
- Çevirilerde yer tutucuların (%s, %d, {ad}) yanında silinmiş boşluklar geri geldi (TR, DE, ES, FR, PT, RU, AR); .po/.mo/.l10n.php/JSON paketleri güncellendi.

## 0.6.4
- Full profiling (SAVEQUERIES) no longer follows the logged-in cookie. It runs only for a signed 30-minute marker set when someone who can open the Flux Speed panel loads wp-admin, and the collected profile is dropped if that user is not allowed.
- Block the default login script when the request is wp-login.php with a path suffix, extra slashes or different case, including subdirectory installs.

## 0.6.3
- Remove legacy translation response framing from source IDs and all language packs; headings no longer display literal newline escapes.
- Refresh current English and Turkish product screenshots and browser walkthroughs, including mobile and dark views.


## 0.6.2
- Add native WordPress language packs for German, Spanish, French, Brazilian Portuguese, Japanese, Simplified Chinese, Arabic and Russian. English-keyed JSON catalogs preserve legacy gettext compatibility; no Flux Lang dependency.

## 0.6.1
- Eksik çeviriler eklendi. Arayüz WordPress dilini izler: Türkçe sitede Türkçe, İngilizce sitede (en_US ve en_GB gibi) İngilizce metin gelir.


## 0.6.0
- Uygulama: sunucu özeti ve yavaş istekler (`flx-speed/v1/summary`, `/requests`).

## 0.5.2
- Kullanılmayan CSS kırpma `swiper-*` sınıflarını her zaman korur: Swiper sınıf adlarını JavaScript'te birleştirerek ürettiği için slider noktaları (pagination) siliniyordu.
- FluxWP imzası (flx-brand 1.1.1) koyu zeminde soluk değil.

## 0.5.1
- Metinler: MCP sunucusu artık Flux AI & MCP eklentisinde; "Flux SEO & MCP" ve "Flux AI ›" adresleri "Flux AI & MCP" oldu.

## 0.5.0
- Yeni **Performans › PageSpeed** sekmesi: Lighthouse önerilerinin tema değiştirmeden yapılabilenleri, her biri aç/kapa.
  - Yakınlaştırma kilidini kaldır (viewport `user-scalable=no`, `maximum-scale`) ve emoji betiğini kaldır: güvenli, varsayılan açık.
  - Kullanılmayan CSS'i kırp + satır içine al (varsayılan kapalı): sayfanın sınıf/id envanteri (HTML + sayfadaki betiklerin kelimeleri) CSS dosyasının envanteriyle kesiştirilir, karşılığı olmayan kurallar atılır, kalan `<head>`'e yazılır. Aynı envanterli sayfalar aynı kopyayı kullanır; kaynak imzası (yol + mtime + boyut) değişince kopyalar yenilenir; dosya başına 40 kopyayı aşınca tam (sıkıştırılmış) dosyaya dönülür. `is-*`, `has-*`, `wp-*` ve panelden girilen sınıflar (joker `*`) hep korunur. Kapsam: yalnız tema ya da tüm wp-content.
  - CSS ve JS sıkıştırma (kapalı): kaynaktan yeni `.min` kardeşi varsa o, yoksa `uploads/flx-speed/` altında üretilmiş kopya. JS sıkıştırma tutucu (yorum + satır boşlukları, satır sonları korunur, ayraç dengesi doğrulanır); webpack/import() paketleri ve çevirisi olan betikler atlanır.
  - Head betiklerine defer (kapalı): WordPress strateji API'siyle; jQuery ve hariç tutulanlar engelleyen kalır.
  - Yazı tipleri: sayfada kullanılmayan theme.json @font-face aileleri çıkarılır; en fazla iki ailenin 400 woff2 dosyası preload edilir (kapalı).
  - Hariç dosyalar / sayfalar listesi; `?flxp_off=1` ile sayfa iyileştirmesiz açılır (karşılaştırma). Kaydedince önbellek ve LiteSpeed temizlenir.
- GPU'da çalışmayan animasyonlar (Lighthouse non-composited): ön yüzde yüklenen CSS dosyalarındaki `@keyframes` transform/opacity dışı özellikleriyle listelenir; Sistem Kontrolü'nde "Animasyonlar (GPU)" satırı.
- Flux SEO site denetimi bulgularına tek tık düzeltme (`flx_system_fixes`: `flxp_ps_css`, `flxp_ps_minify`, `flxp_ps_defer`, `flxp_ps_viewport`, `flxp_ps_fonts`, `flxp_ps_emoji`); MCP `system_fix` ile de açılır. Güvenlik başlıkları ve llms.txt Flux SEO'da kalır.
- Yeni filtre `flxp_pagespeed_enabled` (tema/ön ayar bir özelliği kapatabilir); Flux Setup ön ayar şemasına `flxp_settings` › `ps_*` alanları.

## 0.4.2
- Tarih ve "… önce" metinleri artık kullanıcının dilinde, tek ortak yardımcıdan (FluxWP `flx_date` / `flx_time_ago`, flx-brand 1.1.0): "31 minutes önce", "August 10" gibi Türkçe/İngilizce karışımı kalmadı; çekirdek dil paketi inmemiş olsa da TR ve EN ay/gün adları doğru.

## 0.4.1
- Giriş adresi kaydedilince ya da değişince uyarı çıkar: 5 saniye geri sayımdan sonra oturum kapanır ve yeni giriş sayfası açılır; giriş yapınca Ayarlar'a dönülür. "Şimdi git" ve "Oturumda kal" düğmeleri var.
- Yeni giriş sayfasında "Giriş adresi değişti, yer imlerine ekleyin" bilgisi.

## 0.4.0
- Ayarlar › Yönetim giriş adresi: giriş ekranı wp-login.php yerine özel adreste açılır (örn. `/fluxadmin`). wp-login.php ve giriş yapmamış ziyaretçinin wp-admin istekleri temanın 404 sayfasını alır ya da ana sayfaya yönlenir.
- WordPress'in ürettiği giriş, çıkış, şifre sıfırlama (e-posta dahil) ve kayıt bağlantıları yeni adrese çevrilir; `/login`, `/admin`, `/dashboard` kısayolları kapanır.
- Etkilenmeyenler: admin-ajax.php, admin-post.php, REST API, wp-cron, XML-RPC, WP-CLI, WooCommerce Hesabım girişi, Flux App bağlantısı (`wp-login.php?action=flxapp_authorize`), şifreli yazı formu.
- Kurtarma: wp-config.php'de `define( 'FLXP_LOGIN_DISABLE', true );` özelliği kapatır; `FLXP_LOGIN_SLUG` sabiti paneli ezer.
- Önbellek: giriş ve engel yanıtları `no-cache` + `X-LiteSpeed-Cache-Control: no-cache`; adres değişince LiteSpeed önbelleği temizlenir.
- Yeni filtreler: `flxp_login_passthrough_actions`, `flxp_login_blocked_redirect`.

## 0.3.4
- Panel yalnız Flux Admin / tema koyu modunu izler; admin açıkken cihaz ayarından koyulaşmıyor.

## 0.3.3
- Ayar önekleri listesinde `flxu_` artık "FluxWP Panel" olarak görünür (eski Flux Updater adı yerine).

## 0.3.1
- Koyu mod Flux Admin koyu temasında da çalışıyor (önceden yalnız tema sınıfında); ara bilgi metinleri ve rozetler okunur; panelde yeşilimsi koyu tonlar nötr.

## 0.3.0
- Paylaşımlı hosting'de (Hostinger vb.) Özet, Sunucu ve Başlangıç widget'ı artık ana makinenin değerlerini (64 çekirdek, 503 GB RAM, TB'larca disk) sitenin değeri gibi göstermiyor.
- Hesap sınırı cgroup v1/v2'den okunur (konteyner, VPS dilimi, hesap): işlemci yüzdesi sınıra göre, bellek kullanım / sınır. Okunamazsa İşlemci (hesap) ve Bellek (hesap) kutuları "Hesap sınırı okunamıyor" der; makinenin ortak değerleri Sunucu sekmesinde ayrı, "Makine geneli (paylaşımlı, tüm hesaplar)" başlığıyla durur.
- Disk (hesap): hesap klasörü (ev klasörü, yoksa WordPress klasörü) dosyaları + sitenin veritabanı. Tarama parça parça (her istek en fazla 1-2 sn), 12 saatte bir yenilenir; dosya/inode sayısı Sunucu sekmesinde.
- Ayarlar › Disk kotası (GB): girilirse Disk kutusu ve Sistem Kontrolü yüzde gösterir.
- Sistem Kontrolü yük satırı paylaşımlı hosting'de makine yükünü raporlamaz. `flxp_server` aracında `live.scope`, `live.machine`, `live.disk_usage`.
- Yeni filtreler: `flxp_shared_hosting`, `flxp_cgroup`, `flxp_account_root`.

## 0.2.3
- FluxWP imzası: Flux ekranlarının alt satırında küçük FluxWP logosu ve fluxwp.com bağlantısı. Ortak modül `includes/flx-brand` (kaynak FluxWP/fluxwp `tools/brand`); kurulu Flux paketlerinden en yeni sürümü bir kez yüklenir, `flx_brand_show` filtresiyle gizlenir.

## 0.2.2
- Yeni logo (FluxWP ikon seti, grup rengi). `assets/icon-pastel.svg` Flux Admin › Görünüm › Paket logoları Pastel iken kullanılır.

## 0.2.1
- Sistem kontrolü kartı nötr renkler (yeşilimsi tonlar kaldırıldı).

## 0.2.0

- Cron görev süreleri: wp-cron.php her çalıştığında kanca başına süre ölçülür (hangi eklenti, kaç kez, ortalama/en uzun/son, süre sınırında kesildi mi). Cron sekmesinde "Görev süreleri" tablosu, istek ayrıntısında "Cron görevleri" bölümü, `flxp_cron` aracında `timings`.
- admin-ajax kayıtları artık action ile görünür (`admin-ajax.php?action=flxy_tick`); Heartbeat'te hangi paketlerin veri istediği de yazılır (`data=flxa,flxp_live`). En yavaş adresler action'a göre ayrılır.
- Özet: "İstek türüne göre" tablosu (Ön yüz / Cron / Ajax / REST için ortalama, p50, p95). `flxp_summary` çıktısında `by_type`, `cron_hooks` ve yapay zekâ için `note`: yalnız yavaş/hatalı/örneklenen istekler kaydedildiği için "kayıtların çoğu yavaş" sitenin çoğu yavaş demek değildir.

## 0.1.1

- Düzeltme: WordPress 6.8+ sürümünde kendi transient dinleyicimiz "setted_site_transient eskimiş" uyarısı üretiyordu; yeni set_transient / set_site_transient kancaları kullanılıyor.
- Düzeltme: kurulum ve güncellemede WordPress'in tablo yoklaması (DESCRIBE … doesn't exist) hatalı sorgu sayılıyordu; artık sayılmıyor.

## 0.1.0

- İlk sürüm. Query Monitor ve WP Server Stats'tan esinlenilerek sıfırdan yazıldı (kod kopyalanmadı).
- Geliştirici paneli: yönetici çubuğunda süre/sorgu/hata özeti, alt panelde Genel bakış (zaman çizelgesi), Sorgular (tür/çağıran/bileşen süzgeci, yavaş ve hatalı sorgular, çağrı yığını), Tekrar eden sorgular, Bileşenlere göre, PHP hataları, Yanlış kullanım/eskimiş, Dış istekler, Loglar + zamanlayıcılar, Şablon, İstek + koşullar, Yetki kontrolleri, Script ve stiller (eksik bağımlılık), Diller, Transient güncellemeleri, Hook'lar.
- Kayıt: yavaş (eşik), hatalı, 5xx, başarısız dış istek, yavaş/hatalı sorgu ve örneklenen istekler tabloya yazılır; 7 gün / 5000 kayıt sınırı. Kişisel veri (e-posta, telefon, IBAN, anahtar) maskelenir.
- Yönetim: Özet (canlı CPU/RAM/disk, öneriler, p95, en yavaş adresler, bileşen başına sorgu süresi, gruplu yavaş sorgu/PHP hatası/dış istek), Kayıtlar (süzgeç, sıralama, "Sayfayı ölç"), kayıt ayrıntısı (aynı panel), Sunucu, Veritabanı (+ güvenli temizlik), Autoload, Cron, Sistem Kontrolü, Ayarlar.
- Sunucu: CPU yükü ve çekirdek, RAM, disk, uptime (/proc, shell_exec yok), sunucu yazılımı/IP/port/ad/belge kökü, PHP limitleri, OPcache, önbellekler. Başlangıç ekranında canlı widget (Heartbeat).
- Sistem Kontrolü ekranı artık burada (Performans › Sistem Kontrolü); Başlangıç uyarısı buraya bağlanır. Yeni satırlar: autoload, cron, disk, yük, OPcache, süresi dolmuş geçici veriler.
- Yapay zekâ: `flxp_summary`, `flxp_requests`, `flxp_request`, `flxp_slow_queries`, `flxp_php_errors`, `flxp_http_calls`, `flxp_environment`, `flxp_server`, `flxp_database`, `flxp_autoload`, `flxp_cron`, `flxp_profile_url` (salt okunur; Flux SEO & MCP + Flux AI); düzeltme `flxp_expired_transients`.
- Log kancası `flx_log` (+ Query Monitor uyumlu `qm/debug` … `qm/emergency`), `flx_timer_start` / `flx_timer_stop`.
- Sistem kontrolü satırları, Flux Roles yetkisi `flxp_view`, TR + EN.
