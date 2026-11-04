# Flux reCAPTCHA

Giriş, kayıt, şifre sıfırlama, yorum, WooCommerce hesap ve ödeme, Flux Donate bağış formu ve Flux Forms gönderimlerini
Google reCAPTCHA v3 (görünmez) ya da v2 (kutucuk) ile botlara karşı korur. Hangi formun korunacağı panelden seçilir.

- Anahtarlar test edilmeden koruma açılmaz; yanlış anahtarla kilitlenme olmaz. Gizli anahtar şifreli saklanır.
- Flux Speed özel giriş adresiyle (/fluxadmin) çalışır.
- Google betiği yalnız ziyaretçi korunan forma dokununca yüklenir.
- Form başına son 30 gün geçen / engellenen, üyelere sorma, IP izin listesi, recaptcha.net seçeneği.
- Kurtarma: wp-config.php'ye `define( 'FLXRC_DISABLE', true );`
- Diğer eklentiler için ortak API: [docs/flx-recaptcha/API.md](../../docs/flx-recaptcha/API.md).

English: [README.md](README.md)
