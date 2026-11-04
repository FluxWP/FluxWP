# Flux reCAPTCHA 0.1.0 · Kurulum

Giriş, kayıt, şifre sıfırlama, yorum, WooCommerce hesap ve ödeme, Flux Donate bağış formu ve Flux Forms gönderimlerini
Google reCAPTCHA ile botlara karşı korur. Hangi formun korunacağı panelden seçilir.

## Kurulum
1. FluxWP Panel › Paketler › **Flux reCAPTCHA › Kur** (ya da `flx-recaptcha.zip` yükle, etkinleştir).
2. https://www.google.com/recaptcha/admin/create → tür: **reCAPTCHA v3** (önerilen) ya da v2 "Ben robot değilim";
   alan adları: `yesilturkiyeist.org` (ve test alanı). Site anahtarı + gizli anahtarı kopyala.
3. Yönetim › **reCAPTCHA** (Sistem grubunda): türü seç, anahtarları yapıştır, **Test et ve kaydet**.
   Test geçince koruma açılır. Test geçmeden hiçbir forma doğrulama eklenmez (yanlış anahtarla kilitlenme yok).
4. Korunan formlar: varsayılan hepsi açık; istemediğini kapat, **Ayarları kaydet**.
5. LiteSpeed › Purge All.

## Notlar
- Flux Speed özel giriş adresi (/fluxadmin) ile çalışır.
- Flux Forms'ta formun kendi reCAPTCHA seçeneği açıksa kapat (çakışır).
- Google betiği yalnız korunan forma dokununca yüklenir (v2'de kutucuk ekrana gelince); diğer sayfalar etkilenmez.
- "Giriş yapmış kullanıcılara sorma" açık: yorum, ödeme, bağış, form üyelere sorulmaz; giriş/kayıt her zaman korunur.
- Google'a ulaşılamazsa varsayılan: gönderim geçer (ayar). Son hata panelde ve Sistem Kontrolü'nde görünür.
- Ofis/izleme IP'leri "Doğrulama yapılmayan IP adresleri"ne (CIDR olur).

## Kilitlenirsen
`wp-config.php`'ye: `define( 'FLXRC_DISABLE', true );` → koruma tamamen kapanır. İsteğe bağlı sabitler:
`FLXRC_SITE_KEY`, `FLXRC_SECRET_KEY` (paneli ezer).

## Test kapsamı
WP 6.8.3 + SQLite + WooCommerce 10.3, Flux Admin/Speed/Donate/Forms ile. Google bu ortamdan erişilemediği için
**sahte Google (siteverify + api.js) ile** uçtan uca test edildi: panel testi, /fluxadmin girişi (v3 ve v2), şifre sıfırlama,
yorum, WC hesap girişi, klasik ödeme, blok ödeme (Store API), bağış formu, Flux Forms REST; iyi ve kötü belirteç.
`bin/test-update.sh` temiz kurulum geçti. Canlıda gerçek anahtarla bir giriş ve bir yorum denemesi yapılmalı.
