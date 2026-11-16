# flx-analytics 0.5.0

- Faz: 1
- Durum: 👀
- PR: #319

`gql/flx-analytics-1`: servis katmanı, 14 sorgu + 7 mutation, 21 kayıtlı işlem, sözleşme testi 75/75. React ekranları (`flxan-stats`, `-journeys`, `-settings`, `-telemetry`) klasikle eşit, wp-admin varsayılanı. Ziyaret toplama, telemetri alımı, CSV indirme ve pano widget'ı istisna (aşağıda). Eşleme `ESLEME/flx-analytics.md`

## İstisnalar

| Uç / akış | Paket | Neden | GraphQL tarafı |
|---|---|---|---|
| Ziyaret toplama `POST /flxan/v1/c` ve telemetri alımı `POST /flxan/v1/telemetry` | flx-analytics | Herkese açık, her sayfada tarayıcıdan gelen yüksek hacimli yazma; ayrı hafif uç (`endpoint.php`/`collect.php`) | Raporlar ve ayarlar GraphQL'de |
| CSV indirme (admin-post `flxan_export`), WP Pano widget'ları ve `GET /flxan/v1/dashboard` | flx-analytics | Dosya akışı; Pano widget'ı sunucuda çizilir ve sekme açıkken yenilenir | Veri GraphQL raporlarıyla aynı servisten |
