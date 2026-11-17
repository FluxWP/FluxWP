# Flux reCAPTCHA

## 0.5.0
- Klasik (PHP) yönetim sayfası kaldırıldı; wp-admin'de yalnız React ekranı açılır. "Klasik görünüm" bağlantısı, kullanıcı başına görünüm tercihi, eski kaydetme/test uçları (`admin-post` `flxrc_save`, `wp_ajax_flxrc_test`) ve `assets/admin.{js,css}` silindi. Ekran ortak kabukla (`flx_admin_screen`) açılır; açılamazsa boş sayfa yerine "Bu ekran yüklenemedi" uyarısı ve yenile düğmesi görünür.

## 0.4.0
- FluxWP mobil uygulamasında reCAPTCHA (genel bakış, form başına aç/kapat, ayarlar) artık web görünümü yerine uygulamanın kendi ekranlarında açılır (manifest `app/manifest.json`, veri GraphQL'den). Masaüstü ve wp-admin aynı React ekranında kalır. Yeni metinlerin Türkçesi eklendi.

## 0.3.0
- Ayarlar, korunan formlar, durum ve anahtar testi GraphQL'de (Flux GraphQL gerekir). Gizli anahtar hiçbir yanıtta dönmez.
- Yeni ortak React ekranı wp-admin'de varsayılan; klasik görünüm bağlantıyla açılır (kullanıcı başına).
- Başka paketler için: `flx_captcha_gql_verify()` GraphQL mutation'ında token'ı doğrular, herkese açık `flxCaptcha(context)` sorgusu istemciye site anahtarı ve betik adresini verir.
- PHP 8.1 ve WordPress 6.5 gerekir.

## 0.2.5
- Tarayıcının onay/uyarı/giriş pencereleri (confirm, alert, prompt) yerine ortak FluxWP modalı (flx-brand flxDialog): yeni tarz, telefonda alttan açılır, koyu modla uyumlu. Silme gibi geri alınamaz işlemlerde kırmızı onay düğmesi.

## 0.2.4
- Gizli anahtar bir sonraki kayıtta ortak mühürle saklanır. Eski kayıt açılır. WordPress tuzları değişirse anahtarı yeniden girin.

## 0.2.3
- Çevirilerde yer tutucuların (%s, %d, {ad}) yanında silinmiş boşluklar geri geldi (TR, DE, ES, FR, PT, RU, AR); .po/.mo/.l10n.php/JSON paketleri güncellendi.

## 0.2.2
- Remove legacy translation response framing from source IDs and all language packs; headings no longer display literal newline escapes.
- Refresh current English and Turkish product screenshots and browser walkthroughs, including mobile and dark views.


## 0.2.1
- Add native WordPress language packs for German, Spanish, French, Brazilian Portuguese, Japanese, Simplified Chinese, Arabic and Russian. English-keyed JSON catalogs preserve legacy gettext compatibility; no Flux Lang dependency.

## 0.2.0
- Uygulama ayarları. Site anahtarı ve gizli anahtar write-only; gizli anahtar şifreli saklanır.

## 0.1.0

- İlk sürüm: Google reCAPTCHA v3 (görünmez, puanlı) ya da v2 ("Ben robot değilim" kutucuğu).
- Panelden açılıp kapanan korumalar: giriş (Flux Speed özel giriş adresi dahil), kayıt, şifre sıfırlama, yorumlar, WooCommerce hesap girişi ve kaydı, ödeme sayfası (klasik ve blok), Flux Donate bağış formu, Flux Forms gönderimleri.
- Anahtarlar panelde test edilir; test geçmeden hiçbir forma doğrulama eklenmez (yanlış anahtarla kilitlenme olmaz). Gizli anahtar şifreli saklanır.
- Google betiği yalnız korunan forma dokununca (v2'de kutucuk ekrana gelince) yüklenir.
- Son 30 günün geçen / engellenen sayıları, giriş yapanlara sorma, IP izin listesi, Google'a ulaşılamazsa geçir, v3 rozetini gizle, recaptcha.net seçeneği.
- Kurtarma: wp-config.php'ye define( 'FLXRC_DISABLE', true );
- Diğer eklentiler için ortak API: `flx_captcha_contexts`, `flx_captcha_field`, `flx_captcha_verify`, JS `flxCaptcha.token()`.
