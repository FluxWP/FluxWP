# Flux reCAPTCHA

## 0.1.0

- İlk sürüm: Google reCAPTCHA v3 (görünmez, puanlı) ya da v2 ("Ben robot değilim" kutucuğu).
- Panelden açılıp kapanan korumalar: giriş (Flux Speed özel giriş adresi dahil), kayıt, şifre sıfırlama, yorumlar, WooCommerce hesap girişi ve kaydı, ödeme sayfası (klasik ve blok), Flux Donate bağış formu, Flux Forms gönderimleri.
- Anahtarlar panelde test edilir; test geçmeden hiçbir forma doğrulama eklenmez (yanlış anahtarla kilitlenme olmaz). Gizli anahtar şifreli saklanır.
- Google betiği yalnız korunan forma dokununca (v2'de kutucuk ekrana gelince) yüklenir.
- Son 30 günün geçen / engellenen sayıları, giriş yapanlara sorma, IP izin listesi, Google'a ulaşılamazsa geçir, v3 rozetini gizle, recaptcha.net seçeneği.
- Kurtarma: wp-config.php'ye define( 'FLXRC_DISABLE', true );
- Diğer eklentiler için ortak API: `flx_captcha_contexts`, `flx_captcha_field`, `flx_captcha_verify`, JS `flxCaptcha.token()`.
