# flx-seo 1.11.0

- Faz: 1
- Durum: ✓
- PR: #307

`gql/flx-seo-1`: 3 servis, 5 sorgu + 5 mutation + ayar tipi (`flxSettings.flxseo`) + `flxSeo` alanı (her içerik/terim), 9 kayıtlı işlem, sözleşme testi 120/120. React ekranı `flxseo-seo` (5 sekme) klasikle eşit, wp-admin varsayılanı. Eşleme `ESLEME/flx-seo.md`

## İstisnalar

| Uç / akış | Paket | Neden | GraphQL tarafı |
|---|---|---|---|
| Flux SEO `flxseo_audit_tick` (arka plan denetim tetiği, nopriv) | flx-seo | Hostinger'da WP cron tetiklenmiyor; loopback isteği, jetonla doğrulanır | Denetim durumu `flxSeoAuditHistory.running`, çalıştırma `flxSeoAuditRun` |
| Flux SEO REST `flx-seo/v1/audit` (GET/POST) | flx-seo | Eski FluxApp sürümleri | Geçici; kalkar: Faz 7 (eski uygulama sürümleri) |
