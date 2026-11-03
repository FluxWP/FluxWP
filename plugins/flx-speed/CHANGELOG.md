# Değişiklikler

## 0.3.0
- Paylaşımlı hosting'de (Hostinger vb.) Özet, Sunucu ve Başlangıç widget'ı artık ana makinenin değerlerini (64 çekirdek, 503 GB RAM, TB'larca disk) sitenin değeri gibi göstermiyor.
- Hesap sınırı cgroup v1/v2'den okunur (konteyner, VPS dilimi, hesap): işlemci yüzdesi sınıra göre, bellek kullanım / sınır. Okunamazsa İşlemci (hesap) ve Bellek (hesap) kutuları "Hesap sınırı okunamıyor" der; makinenin ortak değerleri Sunucu sekmesinde ayrı, "Makine geneli (paylaşımlı, tüm hesaplar)" başlığıyla durur.
- Disk (hesap): hesap klasörü (ev klasörü, yoksa WordPress klasörü) dosyaları + sitenin veritabanı. Tarama parça parça (her istek en fazla 1-2 sn), 12 saatte bir yenilenir; dosya/inode sayısı Sunucu sekmesinde.
- Ayarlar › Disk kotası (GB): girilirse Disk kutusu ve Sistem Kontrolü yüzde gösterir.
- Sistem Kontrolü yük satırı paylaşımlı hosting'de makine yükünü raporlamaz. `flxp_server` aracında `live.scope`, `live.machine`, `live.disk_usage`.
- Yeni filtreler: `flxp_shared_hosting`, `flxp_cgroup`, `flxp_account_root`.

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
