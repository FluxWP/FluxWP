# Flux Speed (flx-speed v0.1.0)

Sitenin performansı tek yerde: geliştirici paneli ve istek kaydı (Query Monitor benzeri; fark: yavaş ve hatalı istekleri **kaydeder**), sunucu durumu (Server Stats benzeri), veritabanı, autoload, cron ve **Sistem Kontrolü**. Yapay zekâ hepsini MCP / Flux AI ile okur.

## Kurulum
1. Güncellemeler'den (ya da zip ile) kurup etkinleştirin. Tablo `wp_flxp_requests` kendiliğinden kurulur.
2. Sol menü › **Performans** › Ayarlar: varsayılanlar Hostinger için uygun (örnekleme %2, yavaş istek 1500 ms, yavaş sorgu 100 ms, 7 gün).
3. Personelin görmesi için Flux Roles'den **flxp_view** ("Performansı görüntüle") yetkisi verin. Yöneticide kendiliğinden var.

## Kullanım
- **Panel**: yetkili kullanıcı giriş yapmışken yönetici çubuğunda `266 ms · 49q · ⚠ 1` görünür (hata varsa kırmızı, yavaşsa sarı). Tıklayınca alt panel açılır; üst kenarından boyutlandırılır, Esc kapatır.
- **Kayıtlar**: süzgeç (zaman, işaret, tür, adres), "En yavaş" sıralama. Satıra tıklayınca o isteğin tüm paneli.
- **Sayfayı ölç**: bir adresi ziyaretçi gibi (önbelleksiz) açar ve tüm ayrıntıyla kaydeder. Yalnız bu sitenin adresleri.
- **Özet** (ilk ekran): canlı işlemci yükü / RAM / disk / PHP belleği / uptime kutuları (15 sn'de bir, Heartbeat), **Öneriler** (autoload büyük, cron çalışmıyor, disk dolu, OPcache kapalı...), son 1 saat / 24 saat / 7 gün; p95, en yavaş adresler, hangi eklenti ne kadar sorgu süresi harcıyor, gruplu yavaş sorgular, PHP hataları, dış istekler.
- **Sunucu**: işletim sistemi, sunucu yazılımı, IP, port, sunucu adı, belge kökü, çekirdek sayısı, CloudLinux; PHP limitleri ve kapalı fonksiyonlar, uzantılar; OPcache (isabet, bellek); önbellekler; WordPress sabitleri; etkin eklentiler.
- **Veritabanı**: MySQL/MariaDB sürümü, max_connections, buffer pool, max_allowed_packet...; durum (uptime, saniyede sorgu, yavaş sorgu, buffer pool isabeti); tablo tablo veri/indeks/boş alan; **Temizlik**: süresi dolmuş transient, eski otomatik taslak, spam/çöp yorum, OPTIMIZE (yalnız yönetici, onay sorar; yazı ve revizyon silinmez).
- **Autoload**: her istekte yüklenen seçeneklerin toplamı ve en büyük 40'ı, sahibi (hangi eklenti).
- **Cron**: tüm zamanlanmış görevler, gecikenler, cron'un en son ne zaman çalıştığı.
- **Sistem Kontrolü** (yalnız yönetici): tüm FluxWP paketlerinin kontrolleri + yapay zekâ düzeltme kaydı. Tema / Flux Donate / Flux Forms'daki eski ekranlar kendi sahiplerince buraya yönlendirilecek; o zamana kadar ikisi de çalışır, Başlangıç uyarısı buraya bağlanır.
- **Başlangıç widget'ı**: canlı kutular, 24 saatte p95 / hatalı / yavaş istek, ilk 3 öneri.

## Yapay zekâ
Flux AI & MCP açıksa MCP'de, değilse yalnız Flux AI sohbetinde (salt okunur):
`flxp_summary`, `flxp_requests`, `flxp_request`, `flxp_slow_queries`, `flxp_php_errors`, `flxp_http_calls`, `flxp_environment`, `flxp_server`, `flxp_database`, `flxp_autoload`, `flxp_cron`, `flxp_profile_url`.
Düzeltme: Flux AI & MCP `system_fix` ile `flxp_expired_transients` (süresi dolmuş geçici verileri sil).
Örnek: "Son 24 saatte site neden yavaş?", "/bagis/ sayfasını ölç, en yavaş sorgu hangi eklentiden?", "Evolution isteği hata veriyor mu?", "Autoload'ı en çok hangi eklenti şişiriyor?", "Cron çalışıyor mu?"

## Performans (Hostinger)
- Ziyaretçide yalnız ucuz kancalar çalışır (PHP hatası, dış istek, süre). Sorgu listesi (`SAVEQUERIES`) yalnız örneklenen isteklerde, "Sayfayı ölç"te ve paneli gören kullanıcının wp-admin'de aldığı imzalı çerezle (30 dk, HMAC) açılır. Çerez giriş çerezi değildir; kullanıcı yetkili değilse toplanan profil atılır.
- Kayıt istek sonunda tek INSERT. Temizlik ara sıra, istek üzerine (cron gerekmez).
- `wp-config.php`'de `SAVEQUERIES` tanımlıysa Sistem Kontrolü uyarır; kaldırın.
- Tamamen kapatmak için `define( 'FLXP_DISABLED', true );`.
- LiteSpeed: panel yalnız giriş yapmış yetkiliye basılır (önbelleğe girmez). "Sayfayı ölç" önbelleği benzersiz parametreyle atlar.

## Gizlilik
SQL, URL, hata ve log metinlerinde e-posta, 10+ hane (telefon), IBAN, anahtar/token görünümlü değerler ve `key/token/pass/...` sorgu parametreleri kayda yazılmadan maskelenir. POST gövdesi kaydedilmez.

## Sunucu bilgisi nasıl okunur
- `shell_exec` ve dış servis (IP konumu vb.) kullanılmaz; CPU/RAM/uptime `/proc`'tan, disk `disk_free_space`'ten. Sunucu izin vermezse kutu "—" gösterir.
- Hostinger paylaşımlı sunucuda CPU yükü ve RAM **tüm makineye** aittir (sitenin sınırı 3 GB RAM, 60 işçi değil). Sitenin kendi payı hPanel › Kaynak kullanımı'nda.

## Bilinen sınırlar
- Eklenti yüklenmeden önceki birkaç sorgu (çekirdek ve eklentilerden önce) listede yoktur; toplam sayıda vardır.
- Blok editörü blokları, multisite paneli faz 2.
