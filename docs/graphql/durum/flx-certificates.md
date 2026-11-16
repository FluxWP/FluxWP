# flx-certificates 2.1.2

- Faz: 1
- Durum: ✱
- PR: #338

Envanter (`gql/flx-certificates-1`). REST tablosunda 27 satır GraphQL, 1 istisna. AJAX kaydı yok. 12 admin slug’ından 11’i ekran, sağlayıcılar yönlendirme. MCP aracı yok. Servis, şema ve ekran bu PR’da yok. Eşleme `docs/graphql/ESLEME/flx-certificates.md`.

## İstisnalar

| Uç / akış | Paket | Neden | GraphQL tarafı |
|---|---|---|---|
| `POST /wp-json/flx-certificates/v1/public-files/{code}` | flx-certificates | Anonim tarayıcı çizicisi eksik PDF/JPEG dosyasını HMAC jetonuyla multipart yükler; GraphQL multipart almaz | `flxCertificateVerify` eksik türleri söyler; hazır dosya URL olarak kalır |
| `/{slug}/{kod}/pdf` (`flxs_serve_pdf`) | flx-certificates | PDF ikili akış | `pdfUrl` |
| `admin-post` `flxs_cert_export` | flx-certificates | Excel akışı; filtreli liste GraphQL’de | `flxCertificates` (aynı filtre); indirme imzalı URL |
| `admin-post` `flxs_logs_csv` | flx-certificates | CSV akışı | `flxCertificateLogs` |
| `admin-post` `flxs_tpl_action` `op=export` | flx-certificates | Şablon JSON’u gömülü arka plan dosyası taşır | `flxCertificateTemplate`; indirme URL |
| `admin-post` `flxs_ecards_zip` | flx-certificates | Tasarım görsellerinin zip akışı | İçe aktarma `flxCertificateEcardsImport` |
| `/{konum}/{id}` konum QR (`flxs_location_redirect`) | flx-certificates | 302 ile harita adresine gider ve sayaç artırır | Yönetici `flxCertificateEvent.locationUrl` |
| `flxs_queue_tick`, `flxs_run_queue`, `flxs_render_files`, `flxs_daily` | flx-certificates | Hostinger’da WP cron tetiklenmez; nopriv AJAX yok, zamanlayıcı ve işçi kalp atışı GraphQL dışı | Adım `flxCertificateQueueStep` ve `flxCertificateRenderMissing`; durum `flxCertificateQueue` |
