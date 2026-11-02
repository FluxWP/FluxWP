<div align="center">

[English](README.md) · **Türkçe**

<img src="docs/logo.svg" alt="Flux Speed logosu" width="120">

# Flux Speed

**WordPress sitenizin neden yavaş olduğunu, paylaşımlı sunucuda bile bulun.**<br>
Her istek için geliştirici paneli (sorgular, PHP hataları, HTTP istekleri, kancalar), yavaş ve hatalı isteklerin kaydı, canlı sunucu durumu, veritabanı, autoload ve cron ekranları, tüm FluxWP paketlerinin ortak Sistem Kontrolü. Yapay zekâ asistanları hepsini MCP ile okuyabilir.

[![WordPress 6.4+](https://img.shields.io/badge/WordPress-6.4%2B-21759b?logo=wordpress&logoColor=white)](https://wordpress.org)
[![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bb4?logo=php&logoColor=white)](https://www.php.net)
[![GPL v2+](https://img.shields.io/badge/lisans-GPL--2.0--or--later-blue)](https://www.gnu.org/licenses/gpl-2.0.html)
![Query Monitor tarzı](https://img.shields.io/badge/geliştirici%20paneli-sorgu%20%C2%B7%20hata%20%C2%B7%20HTTP-2fbf8f)
![MCP](https://img.shields.io/badge/yapay%20zekâ-MCP%20araçları-7d64ff)
![Arayüz](https://img.shields.io/badge/arayüz-Türkçe%20%7C%20English-e30a17)

[Özellikler](#özellikler) · [Kurulum](#kurulum) · [Yapay zekâ asistanlarıyla kullanım (MCP)](#yapay-zekâ-asistanlarıyla-kullanım-mcp) · [Yol haritası](#yol-haritası)

</div>

---

## Neden Flux Speed?

Query Monitor baktığınız sayfada ne olduğunu söyler, gerisini unutur. Paylaşımlı sunucuda yavaş istek genellikle kimsenin bakmadığı istektir. Flux Speed iki dünyanın işe yarayan tarafını birleştirir:

- 📝 **Kaydeder.** Yavaş ve hatalı istekler sorguları, hataları ve HTTP çağrılarıyla saklanır; sonradan bakabilirsiniz.
- 🔍 **Tam geliştirici paneli:** giriş yapmış personel için yönetim çubuğunda `266 ms · 49q · ⚠ 1`, bir tık uzakta.
- 🩺 **Bir bakışta sunucu:** işlemci yükü, RAM, disk, PHP belleği ve çalışma süresi canlı; öneriler eşliğinde.
- 🧹 **Güvenli temizlik:** süresi dolmuş transient'ler, eski otomatik taslaklar, spam ve çöp yorumlar, tablo iyileştirme.
- ✅ **Tek Sistem Kontrolü:** tüm FluxWP paketleri buraya bildirir.
- 🤖 **Yapay zekâya hazır:** "site dün neden yavaştı?" sorusu Flux AI'de ve her MCP istemcisinde çalışır.

## Özellikler

### Geliştirici paneli
- Yönetim çubuğunda özet (süre, sorgu, uyarı) ve boyutlandırılabilir alt panel: sorgular (tekrarlar, bileşene göre, yavaş), PHP hataları, HTTP API çağrıları, loglar, şablon, istek, kancalar, zaman çizelgesi ve yetki kontrolleri.
- **Sayfayı ölç:** sitenin herhangi bir adresini ziyaretçi gibi (önbelleksiz) açar ve tüm ayrıntısıyla kaydeder.

### Kayıtlar ve özet
- Süzgeçli istek kaydı (zaman, işaret, tür, adres) ve "en yavaş" sıralaması; satıra tıklayınca o isteğin tüm paneli.
- Özet: son 1 saat, 24 saat ya da 7 gün için p95, ortalama ve en uzun süre, en yavaş adresler, eklentilere göre sorgu süresi, gruplu yavaş sorgular, PHP hataları ve başarısız dış istekler.
- "Autoload büyük", "cron çalışmıyor", "disk dolmak üzere", "OPcache kapalı" gibi öneriler.

### Sunucu ve site
- **Sunucu:** işletim sistemi, web sunucusu, PHP sınırları ve kapalı fonksiyonlar, uzantılar, OPcache, önbellekler, WordPress sabitleri ve etkin eklentiler. `/proc` ve PHP'den okunur; `shell_exec` ve dış servis yok.
- **Veritabanı:** sürüm, önemli ayarlar, durum, tablo boyutları ve temizlik bölümü (yalnız yönetici, onay sorar).
- **Autoload:** toplam boyut ve sahibiyle birlikte en büyük 40 seçenek. **Cron:** tüm zamanlanmış görevler, gecikenler ve cron'un son çalışması.
- **Sistem Kontrolü:** tüm FluxWP paketlerinin kontrolleri ve tek tık düzeltmeleri (`flx_system_checks`, `flx_system_fixes`).
- Canlı kutular, p95, hatalı ve yavaş istekler ve ilk önerilerle Başlangıç widget'ı.

### Paylaşımlı sunucuda hafif
- Ziyaretçide yalnız ucuz kancalar çalışır (hata, HTTP çağrısı, süre). Sorgu listesi yalnız örneklenen isteklerde, giriş yapmış personelde ve "Sayfayı ölç"te toplanır.
- İstek sonunda tek INSERT; eski kayıtlar ara sıra temizlenir. E-posta, telefon, IBAN, token ve gizli değerler kaydedilmeden önce maskelenir.

## Kurulum

Gereksinimler: WordPress 6.4+, PHP 7.4+.

**Zip ile**

1. [Releases](https://github.com/FluxWP/fluxwp/releases) sayfasında en son `flx-speed-v*` sürümünden `flx-speed.zip`'i indirin.
2. **Eklentiler › Yeni ekle › Eklenti yükle**, zip'i seçin, sonra **Etkinleştir**.

**Flux Updater ile** (sitelerimizde önerilen)

**Başlangıç › Güncellemeler** tüm FluxWP paketlerini listeler; *Flux Speed* satırındaki **Kur**'a basın. Güncellemeler de buradan gelir.

**İlk adımlar**

1. Etkinleştirin; `wp_flxp_requests` tablosu kendiliğinden kurulur.
2. **Performans › Ayarlar** Hostinger'a uygun değerlerle gelir (%2 örnekleme, 1500 ms yavaş istek, 100 ms yavaş sorgu, 7 gün).
3. Personelin görmesi için Flux Roles'den **flxp_view** yetkisini verin. Yöneticide zaten var.

> ℹ️ Paylaşımlı sunucuda işlemci yükü ve RAM yalnız sitenize değil tüm makineye aittir. Eklentiyi tamamen kapatmak için `wp-config.php`'ye `define( 'FLXP_DISABLED', true );` ekleyin.

Ayrıntılı kurulum notları: [KURULUM.md](../../docs/flx-speed/KURULUM.md) · API başvurusu: [API.md](../../docs/flx-speed/API.md) · Sürüm notları: [CHANGELOG.md](CHANGELOG.md)

## Yapay zekâ asistanlarıyla kullanım (MCP)

Flux Speed, sitenin MCP sunucusuna (**Flux SEO & MCP**) ve **Flux AI** sohbetine salt okunur araçlar ekler:

| Araç | Neyi yanıtlar |
|---|---|
| `flxp_summary`, `flxp_requests`, `flxp_request` | Özet, kayıtlı istekler ve tek bir isteğin tamamı |
| `flxp_slow_queries`, `flxp_php_errors`, `flxp_http_calls` | Gruplu yavaş sorgular, PHP hataları, dış HTTP istekleri |
| `flxp_environment`, `flxp_server`, `flxp_database`, `flxp_autoload`, `flxp_cron` | Sunucu, PHP, veritabanı, autoload boyutu ve zamanlanmış görevler |
| `flxp_profile_url` | Sitenin bir sayfasını tüm ayrıntısıyla ölçer |

Flux SEO & MCP ile `system_fix` aracı `flxp_expired_transients` düzeltmesini de çalıştırabilir (süresi dolmuş geçici verileri siler).

Örnek istekler:
- *"Son 24 saatte site neden yavaştı?"*
- *"/bagis/ sayfasını ölç, en yavaş sorgu hangi eklentiden?"*
- *"Autoload'ı en çok hangi eklenti şişiriyor? Cron çalışıyor mu?"*

## Yol haritası

| | |
|---|---|
| ✅ | Geliştirici paneli ve istek kaydı |
| ✅ | Önerilerle sunucu, veritabanı, autoload ve cron ekranları |
| ✅ | Tüm FluxWP paketleri için ortak Sistem Kontrolü |
| ✅ | MCP ve Flux AI araçları |
| ⬜ | Panelde blok editörü istekleri |
| ⬜ | Multisite ağ paneli |
| ⬜ | Hata ya da yavaş istek oranı sıçrayınca e-posta uyarısı |

## Lisans

Flux Speed, [GNU Genel Kamu Lisansı v2 veya üzeri](https://www.gnu.org/licenses/gpl-2.0.html) ile yayınlanır.

FluxWP tarafından geliştirilmiştir.
