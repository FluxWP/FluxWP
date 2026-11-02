<div align="center">

[English](README.md) · **Türkçe**

<img src="docs/logo.svg" alt="Flux Menu Theme logosu" width="120">

# Flux Menu Theme

**QR menü alan adı için olabilecek en sessiz blok tema: bomboş beyaz ana sayfa ve WordPress'in gerekmeyen hiçbir parçası.**<br>
Flux Menu Theme, etkinlik QR menülerimizin alan adı menu.example.org'un temasıdır. Ana sayfa tamamen boş ve beyazdır, diğer sayfalar sade ortalanmış metindir; menüleri ise Flux QR Menu `/menu/` ya da `menu1.menu.example.org` gibi adreslerde kendisi sunar. Tema Ayarları bir menü sitesinin hiç kullanmadığı şeyleri (yazılar, yorumlar, beslemeler, arama, XML-RPC…) kapatır, Site Kurulumu siteyi tek tıkla hazırlar.

[![WordPress 6.6+](https://img.shields.io/badge/WordPress-6.6%2B-21759b?logo=wordpress&logoColor=white)](https://wordpress.org)
[![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bb4?logo=php&logoColor=white)](https://www.php.net)
[![GPL v2+](https://img.shields.io/badge/lisans-GPL--2.0--or--later-blue)](https://www.gnu.org/licenses/gpl-2.0.html)
![Sade](https://img.shields.io/badge/ana%20sayfa-boş%20beyaz-lightgrey)
![Sıkılaştırma](https://img.shields.io/badge/anahtar-9%20özellik%20kapalı-2fbf8f)
![QR](https://img.shields.io/badge/birlikte-FluxWP%20QR%20Menü-0a7cff)
![Arayüz](https://img.shields.io/badge/arayüz-Türkçe%20%7C%20English-e30a17)

[Özellikler](#özellikler) · [Kurulum](#kurulum) · [Geliştiriciler için](#geliştiriciler-için) · [Yol haritası](#yol-haritası)

</div>

---

## Neden Flux Menu Theme?

Bir QR menü alan adı menüyü açmalı, başka hiçbir şeyi değil. Sıradan bir tema header, blog arşivleri, yorum formları ve beslemeler getirir; bunlar yalnız spam çeker ve arama motorlarının kafasını karıştırır:

- ⬜ **Kökte görülecek bir şey yok.** Ana sayfa açık ve koyu modda beyazdır; ziyaretçi QR koddan doğrudan menüye gelir.
- 🔒 **Daha az yüzey.** Yazılar, yorumlar, beslemeler, arama, ek sayfaları, XML-RPC, emoji, oEmbed ve herkese açık kullanıcı ucu tek tek kapatılır, geri açılır.
- 🗺️ **Temiz site haritası.** Yalnız sayfalar ve bu alan adındaki QR menü adresleri listelenir.
- 🧩 **Menünün yolundan çekilir.** Flux QR Menu sayfalarını kendisi üretir; tema onlara hiç dokunmaz.

## Özellikler

### Tema
- Header ve footer'sız bomboş beyaz ana sayfa; sistem yazı tipiyle sade, ortalanmış sayfa, yazı, liste ve 404 şablonları.
- Türkçe kaynak, İngilizce çeviri dahil.

### Görünmez mod
- Varsayılan açık: robots.txt `Disallow: /`, her yanıtta `noindex` (QR menüler dahil), bütün site haritaları 404, REST API yalnız izin verilen ad alanlarında (varsayılan `flx-qr-menu/v1`), `?author=` 404, sürüm etiketleri yok, `.htaccess` ile dizin listesi kapalı.
- "Yalnız menüler açık": giriş yapmamış ziyaretçiye ana sayfa, QR menüler, giriş sayfası ve izin verilen yollar dışında her adres 404. WooCommerce kuruluysa sayfaları, Store API, wc-ajax ve wc-api da kapalı; ödeme geri dönüşleri "Açık kalacak yollar"la açılır.
- Giriş yapmış kullanıcılar siteyi normal görür.

#### Neler kapalı (giriş yapmamış ziyaretçi, görünmez mod açık)

| Ne | Sonuç |
|---|---|
| Site haritaları (`wp-sitemap*.xml`, `sitemap.xml`, `*-sitemap.xml`, `?sitemap=`) | 404 (giriş yapmış olsa da) |
| REST API: `/wp-json/`, `?rest_route=`, `/index.php/wp-json/`, ad alanı dizinleri, `wp/v2`, `oembed`, `wc/store`, `wc/v1-3`, `wc-analytics`, `batch/v1`… | 404; yalnız **Açık REST ad alanları**ndaki uçlar (varsayılan `flx-qr-menu/v1`) çalışır, JSONP (`?_jsonp=`) kapalı |
| `admin-ajax.php` (heartbeat, WooCommerce ve bütün eklentilerin `nopriv` eylemleri), `admin-post.php` | 404 (`admin-ajax:<eylem>` ile tek tek açılır) |
| `xmlrpc.php`, `wp-trackback.php`, `wp-links-opml.php`, `wp-comments-post.php`, `wp-mail.php`, `wp-signup.php`, `wp-activate.php`, `wp-admin/maint/repair.php` | 404 |
| Beslemeler, `/embed/`, `/trackback/`, arama, yazar (`?author=`) ve tarih arşivleri, ek sayfaları, önizleme, bulunamayan her adres | Tema şablonu olmadan sade 404 |
| "Yalnız menüler açık": diğer sayfalar, `?p=`, `?page_id=`, `?name=`, `?cat=`… | 404; ana sayfa sorgu parametresiyle başka içerik göstermez (`?utm_*` gibi parametreler sorun değil) |
| WooCommerce: mağaza, ürün, kategori, sepet, ödeme, hesap (parolamı unuttum dahil), `?wc-ajax=`, `/wc-api/`, `?wc-api=`, `/wc-auth/`, `?add-to-cart=`, `?download_file=`, form işleyicileri | 404; ana sayfada WooCommerce betik, stil ve yazı tipi yüklenmez |
| wp-login | Açık (sahibi için). Kayıt kapalı; "kullanıcı adı kayıtlı değil" / "parola yanlış" yerine tek ileti; parolamı unuttum her zaman "e-postanızı kontrol edin" |
| Sürüm bilgisi | `generator` etiketi, RSD/WLW, kısa bağlantı, REST/oEmbed/besleme bağlantıları, emoji betiği, `X-Pingback`, `X-Powered-By`, `X-Redirect-By`, REST `Link` başlığı yok; `?ver=` değerleri siteye özel özetle değişir (wp-login dahil) |
| `.htaccess` (Apache/LiteSpeed) | Dizin listesi kapalı; `readme.*`, `changelog.*`, `license.*`, `*.md`, `*.log`, `wp-config-sample.php`, `composer.json` erişilemez (eklenti sürümleri dahil); `wp-admin/install.php`, `setup-config.php` her zaman, `upgrade.php` ve `maint/` giriş çerezi yoksa 403 |

Açık kalanlar: ana sayfa, QR menüler (yol, alt alan adı, `/tesekkur/`), `flx-qr-menu/v1` (canlı yenileme, anahtarlı besleme), wp-login, `robots.txt`, `wp-cron.php`, iki izin listesi. WP-CLI ve uygulama parolası / Flux MCP anahtarıyla gelen istekler giriş yapmış sayılır. nginx'te `.htaccess` satırı geçerli değildir; dizin listesini ve bu dosyaları sunucu ayarından kapatın.

### Tema Ayarları
- Hepsi varsayılan açık dokuz anahtar: yazılar, yorumlar, RSS beslemeleri, arama, ek sayfaları, XML-RPC, emoji betiği, oEmbed, anonim `/wp/v2/users`.
- Kapatılan adresler 404 ya da 403 döner, menü ve panel öğeleri kaybolur; Flux QR Menu sayfaları, REST uçları ve menü yöneticisi paneli etkilenmez.

### Site Kurulumu
- Seçilebilir adımlar: kalıcı bağlantılar, İstanbul saat dilimi, `d.m.Y` tarih, yüklüyse Türkçe dil, değiştirilmemiş varsayılan içeriğin temizlenmesi, yorumların kapatılması, boş ana sayfa denetimi.
- Tarihli hazır güncellemeler; bekleyen varsa bildirim.
- Sistem kontrolleri: kapalı özellikler, yazılar kapalıyken yayındaki yazılar, kalıcı bağlantılar, bekleyen güncellemeler, Flux QR Menu etkin değil.

## Kurulum

Gereksinimler: WordPress 6.6+, PHP 7.4+. Menüler için Flux QR Menu.

**Zip ile**

1. [Releases](https://github.com/FluxWP/fluxwp/releases) sayfasında en son `flx-menu-theme-v*` sürümünden `flx-menu-theme.zip`'i indirin.
2. **Görünüm › Temalar › Yeni ekle › Tema yükle**, zip'i seçin, sonra **Etkinleştir**.

**Flux Updater ile** (sitelerimizde önerilen)

**Başlangıç › Güncellemeler** tüm FluxWP paketlerini listeler; *Flux Menu Theme* satırındaki **Kur**'a basın. Güncellemeler de buradan gelir.

**İlk adımlar**

1. Flux Menu Theme'u etkinleştirip **Görünüm › Site Kurulumu**'nu seçili adımlarla çalıştırın.
2. Site yazı ya da yorum gerektirmiyorsa **Görünüm › Tema Ayarları**'ndaki anahtarları açık bırakın.
3. **QR Menüler**'de menüleri oluşturup her birine adres (`/menu/`) ya da alt alan adı verin; Flux QR Menu kurulum notlarına bakın.

> ℹ️ Tema güncellemesinden sonra **Site Kurulumu › Hazır güncellemeler**'de bekleyen maddeyi uygulayın.

Ayrıntılı kurulum notları: [KURULUM.md](../../docs/flx-menu-theme/KURULUM.md) · Sürüm notları: [CHANGELOG.md](CHANGELOG.md)

## Geliştiriciler için

- Menü adresleri, alt alan adları, menü yöneticisi rolü ve SSL: [Flux QR Menu kurulumu](../../docs/qr-menu/KURULUM.md).
- Hazır güncellemeler tarihleriyle `yf_migrations` seçeneğinde tutulur.

## Yol haritası

| | |
|---|---|
| ✅ | Boş beyaz ana sayfa ve sade şablonlar |
| ✅ | Dokuz özellik anahtarı |
| ✅ | Site Kurulumu ve hazır güncellemeler |
| ✅ | Sistem kontrolleri |
| ⬜ | Menü alt alan adına özel site simgesi |

## Lisans

Flux Menu Theme, [GNU Genel Kamu Lisansı v2 veya üzeri](https://www.gnu.org/licenses/gpl-2.0.html) ile yayınlanır.

FluxWP tarafından geliştirilmiştir.
