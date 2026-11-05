# Flux SEO — uygulama

Ayarlar `flxseo` köprüsünden (seçilen alanlar).

`GET /wp-json/flx-seo/v1/audit` — kayıtlı denetim: `counts`, `scores`, `findings`, `ran_at`. Çalıştırmaz.

`POST /wp-json/flx-seo/v1/audit` — yalnız yerel denetim (`flxseo_audit_run_local`). PageSpeed çağrılmaz. Yetki `manage_options`.
