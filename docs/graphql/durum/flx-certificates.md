# flx-certificates 2.1.2

- Faz: 1
- Durum: ✱
- PR: #338

Envanter düzeltildi (`02f12cb8` incelemesi). REST 27 GraphQL / 1 istisna. Ayar alanı 25 satır, 24’ü GraphQL; SMTP şifresi şemaya girmez. Kısa kod: yalnız `[flx_sertifika_sorgula]` GraphQL; üç etkinlik kısa kodu PHP kalır; `[flxs_sertifika]` yok (sayfa rewrite). Etkinlik ekranı CRM’de (`flxGatherings`, PR #333). CRM varken bu menü oraya gider; CRM yokken klasik sayfa kalır (#333 sonrası bileşen yeniden kullanılır). Bu pakette etkinlik CRUD yok; okunan alanlar hedef, ek fidan, fidan sayısı, sertifika sayısı ve site gösterimi. `flxCertificateVerify` saf okuma: sayaç, çizim ve public-files jetonu sorguda yok. Kayıtlar bölümü aynı PR’da. Eşleme `docs/graphql/ESLEME/flx-certificates.md`.

## İstisnalar

| Uç / akış | Paket | Neden | GraphQL tarafı |
|---|---|---|---|
| `POST /wp-json/flx-certificates/v1/public-files/{code}` | flx-certificates | Anonim tarayıcı çizicisi eksik PDF/JPEG dosyasını HMAC jetonuyla multipart yükler. Önbellekli GET jeton taşıyamaz ve sayaç artırmaz | `flxCertificateVerify` saf okuma, jeton yok. Sayaç ve `flxs_render_on_view` klasik sayfada; başsız ön yüz için ayrı önbelleksiz mutation |
| `/{slug}/{kod}/pdf` (`flxs_serve_pdf`) | flx-certificates | PDF ikili akış | `pdfUrl` |
| `admin-post` `flxs_cert_export` | flx-certificates | Excel akışı; filtreli liste GraphQL’de | `flxCertificates` (aynı filtre); indirme imzalı URL |
| `admin-post` `flxs_logs_csv` | flx-certificates | CSV akışı | `flxCertificateLogs` |
| `admin-post` `flxs_tpl_action` `op=export` | flx-certificates | Şablon JSON’u gömülü arka plan dosyası taşır | `flxCertificateTemplate`; indirme URL |
| `admin-post` `flxs_ecards_zip` | flx-certificates | Tasarım görsellerinin zip akışı | İçe aktarma `flxCertificateEcardsImport` |
| `/{konum}/{id}` konum QR (`flxs_location_redirect`) | flx-certificates | 302 ile harita adresine gider ve sayaç artırır | Yönetici `flxCertificateEvent.locationUrl` |
| `flxs_queue_tick`, `flxs_run_queue`, `flxs_render_files`, `flxs_daily` | flx-certificates | Hostinger’da WP cron tetiklenmez; nopriv AJAX yok, zamanlayıcı ve işçi kalp atışı GraphQL dışı | Adım `flxCertificateQueueStep` ve `flxCertificateRenderMissing`; durum `flxCertificateQueue` |
