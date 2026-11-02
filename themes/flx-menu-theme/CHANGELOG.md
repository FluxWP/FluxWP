# Değişiklik günlüğü

## 1.2.1

Görünmez mod sertleştirme: giriş yapmamış ziyaretçi WordPress ve WooCommerce'in varsayılan uçlarından hiçbir veri alamaz (README › "Neler kapalı").

- `admin-ajax.php` her eylemde 404 (heartbeat `nopriv` dahil, yalnız `woocommerce_*` değil); `admin-post.php` de. Gerekirse "Açık kalacak yollar"a `admin-ajax:<eylem>` (ya da `admin-post:<eylem>`) yazılır; kayıtta `/wp-admin/admin-ajax.php?action=<eylem>` olur.
- `xmlrpc.php` (403 yerine 404), `wp-trackback.php`, `wp-links-opml.php` (site adını veriyordu), `wp-comments-post.php`, `wp-mail.php`, `wp-signup.php`, `wp-activate.php`, `wp-admin/maint/repair.php` 404; `/xmlrpc.php` izin listesiyle açılır.
- Beslemeler 404 kodla yine de RSS/Atom basıyordu (`set_404()` `is_feed`'i korur); artık sade 404. Aynı düzeltme görünmez mod kapalıyken "RSS beslemeleri kapalı" anahtarında da (tema 404 sayfası).
- Kapalı adreslerin hepsi (arama, yazar/tarih arşivi, `/embed/`, `/trackback/`, ek, önizleme, WooCommerce sayfaları, bulunamayan adres) tema şablonu ve WooCommerce betikleri olmadan sade 404. `/embed/` ve `?embed=true` ana sayfayı 200 ile veriyordu.
- REST: izinli ad alanının dizini (`/wp-json/flx-qr-menu/v1`, bütün uçları listeliyordu) 404; JSONP kapalı; `Link: <…/wp-json/>` ve `X-Powered-By` başlıkları kalktı.
- WooCommerce: `?add-to-cart=`, `?download_file=` 404 (init'te, WooCommerce'ten önce); ana sayfada WooCommerce betik/stilleri (`wc_add_to_cart_params` içindeki admin-ajax/wc-ajax adresleri, sipariş kaynağı izleme), `woocommerce-no-js` betiği, gövde sınıfları ve "Mağazanı başlat" yazı tipleri yok.
- Sürüm sızıntıları: `?ver=` değerleri (WordPress 6.x, WooCommerce 9.x) giriş yapmamış ziyaretçide siteye özel özete döner (wp-login dahil); emoji betiği, besleme ve oEmbed bağlantıları anahtarlardan bağımsız kalkar; `X-Pingback`, `X-Powered-By`, `X-Redirect-By` yok.
- wp-login: kayıt kapalı; "kullanıcı adı kayıtlı değil" / "… kullanıcısının parolası yanlış" yerine tek ileti; parolamı unuttum kullanıcı olsa da olmasa da "e-postanızı kontrol edin"e gider.
- `.htaccess` bloğu genişledi: `readme.*`, `changelog.*`, `license*`, `*.md`, `*.log`, `composer.json/lock` (eklenti/tema sürümleri), `wp-admin/install.php` ve `setup-config.php` 403, `upgrade.php` ve `maint/` giriş çerezi yoksa 403, `X-Powered-By` kaldırılır. Tema güncellenince blok bir kez kendiliğinden yenilenir.
- WP-CLI görünmez moddan etkilenmez (WooCommerce CLI komutları REST'e erişemeyip uyarı veriyordu).
- Tema Ayarları ve Sistem Kontrolü metinleri güncellendi.

## 1.2.0

- **Görünmez mod** (Görünüm › Tema Ayarları, varsayılan açık): site dışarıdan görünmez. Giriş yapmamış ziyaretçi ve botlar için robots.txt `User-agent: *` / `Disallow: /`; her ön yüz, QR menü ve REST yanıtında `X-Robots-Tag: noindex, nofollow, noarchive, nosnippet` ve `<meta name="robots" content="noindex, nofollow, noarchive">`; Ayarlar › Okuma "arama motorlarını engelle" işaretlenir (kapatınca önceki hâline döner).
- Bütün site haritaları 404: `wp-sitemap*.xml`, `sitemap.xml`, `sitemap_index.xml`, `*-sitemap.xml` (Yoast, Rank Math, WooCommerce tarzı), `?sitemap=`. 1.1.0'daki sayfa + menü haritası yalnız görünmez mod kapalıyken.
- REST API giriş yapmamış istekte yalnız **Açık REST ad alanları** listesinde (varsayılan `flx-qr-menu/v1`: menülerin canlı yenilenmesi ve menü sitelerinin beslemesi); `/wp-json/` dizini, diğer uçlar ve `?rest_route=` 404. REST keşif bağlantıları, kısa bağlantı, RSD ve WordPress/WooCommerce sürüm etiketleri kalkar. Uygulama parolası ve Flux MCP anahtarıyla gelen istekler giriş yapmış sayılır.
- **Yalnız menüler açık** (varsayılan açık): giriş yapmamış ziyaretçiye ana sayfa, QR menüler (yol ve alt alan adı, /tesekkur/), wp-login ve **Açık kalacak yollar** dışında her adres 404. `?author=N` ve yazar sayfaları her zaman 404.
- WooCommerce kuruluysa (giriş yapmamış ziyaretçi): mağaza, ürün, kategori/etiket, sepet, ödeme ve hesap sayfaları 404; Store API ve `wc/v1-3` kapalı; `?wc-ajax=`, `/wc-api/`, `?wc-api=`, admin-ajax `woocommerce_*` ve form işleyicileri (sepete ekle, giriş, kayıt…) kapalı. Ödeme geri dönüşleri "Açık kalacak yollar"a `/wc-api/<ad>` yazılarak açılır.
- `.htaccess`'e "Yesil Fun" bloğu: `Options -Indexes`, `readme.html`, `readme.txt`, `license.txt`, `wp-config-sample.php`, `debug.log` erişimi yok. Kapatınca blok silinir; tema değişince her şey geri alınır.
- Site Kurulumu'na "Görünmez modu aç" adımı; hazır güncelleme "flx-menu-theme 1.2.0: görünmez modu aç".
- Sistem kontrolü: görünmez mod durumu, `.htaccess` yazılamadı, sitede gerçek `robots.txt` / site haritası dosyası, Okuma ayarı, `flx-qr-menu/v1` listede değil, LiteSpeed/QUIC.cloud ve WooCommerce notları.

## 1.1.0

- **Görünüm › Tema Ayarları**: menü sitesinde gereksiz WordPress özelliklerini kapatan 9 anahtar, hepsi varsayılan kapalı ve geri alınabilir: yazılar (menü, "+ Yeni › Yazı", tek yazı/kategori/etiket/tarih/yazar arşivleri 404), yorumlar (her yerde kapalı, menü/pano kutusu gizli, gönderme 403), RSS beslemeleri, arama (`?s=` 404), ek sayfaları (dosyaya 301), XML-RPC (403), emoji betiği, oEmbed (`/embed/` 404), anonim `/wp/v2/users`.
- Site haritası: yalnız sayfalar ve Flux QR Menu'nün bu alan adındaki menü adresleri (`wp-sitemap-qrmenus-1.xml`).
- **Görünüm › Site Kurulumu**: seçilebilir adımlar (kalıcı bağlantılar `/%postname%/`, İstanbul saat dilimi, `d.m.Y` tarih, yüklüyse Türkçe dil, değiştirilmemiş varsayılan içeriğin silinmesi, yorumların kapatılması, boş ana sayfa denetimi), her adımın sonucu satır satır; Flux QR Menu ayarlarına bağlantı (eklenti yoksa Flux Updater yönlendirmesi).
- **Hazır güncellemeler**: tek tıkla, tekrar çalıştırılabilir, uygulananlar `yf_migrations` seçeneğinde tarihle; bekleyen varsa yöneticiye bildirim. İlki: "flx-menu-theme 1.1.0: yazı/yorum varsayılanlarını kapat".
- Sistem kontrolü (`flx_system_checks`): kapalı özellikler, yazılar kapalıyken yayındaki yazılar, yorum varsayılanları, düz kalıcı bağlantılar, bekleyen hazır güncellemeler, Flux QR Menu etkin değil.

## 1.0.0

- İlk sürüm: menu.example.org için en sade block theme.
- Ana sayfa tamamen boş ve beyaz: üst/alt bilgi yok; cihaz koyu temada olsa da beyaz kalır.
- Sayfa, yazı, liste ve 404 şablonları: ortalanmış içerik, sistem yazı tipi.
- QR menüler Flux QR Menu eklentisinden gelir (/menu, alt alan adları, isteğe bağlı ana sayfa); tema onlara karışmaz.
- Türkçe kaynak + İngilizce (en_US) çeviri.
