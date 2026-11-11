# Flux reCAPTCHA

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
