# Flux SEO — uygulama

Ayarlar `flxseo` köprüsünden (seçilen alanlar).

`GET /wp-json/flx-seo/v1/audit` — kayıtlı denetim: `counts`, `scores`, `findings`, `ran_at`. Çalıştırmaz.

`POST /wp-json/flx-seo/v1/audit` — yalnız yerel denetim (`flxseo_audit_run_local`). PageSpeed çağrılmaz. Yetki `manage_options`.

Zamanlanmış denetimin adımı `admin-ajax.php` eylemi `flxseo_audit_tick` dir. İki yoldan biri gerekir: giriş yapmış `manage_options` kullanıcısı ve `flxseo_audit_tick` nonce'u, ya da iş açılırken üretilip `flxseo_audit_job` seçeneğinde duran belirteç (POST `token`, `hash_equals`). Belirteç iş bitince silinir. WP-Cron kancası `flxseo_audit_cron` ve istek sonundaki yedek kontrol adımı çalıştırmaz; zamanı geldiyse işi açar ve belirteçli loopback gönderir. Sekme kapanınca aynı zincir sürer. Aynı anda iki adım `flxseo_audit_lock` ile engellenir.

Web isteğinde PageSpeed beklemesi en çok 40 saniyedir. Zaman aşımı başarısız adım olarak kaydedilir (`flxseo_audit_last_errors`); denetim sonraki adıma geçer. CLI beklemesi 90 saniyedir.
