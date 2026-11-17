# flx-app 0.6.0

- Faz: 1
- Durum: ✓
- PR: #303

`gql/flx-app-1`: servis katmanı, 8 sorgu + 4 mutation, 12 kayıtlı işlem, sözleşme testi 150/150. Belirteç + `flx` kapsamı + `X-Flux-Locale` GraphQL'de; OAuth/OTP/cihaz/ayar köprüsü REST'te, eski uygulama sürümleri REST'te. İstemci taşıyıcısı `@fluxwp/graphql/flx-app` (panel `mountApp`'te, REST yedekli). "Uygulama" ayar sayfası + "Uygulama erişimi" React ekranları 0.6.0'da (varsayılan, klasik bağlantıyla; 17 yeni işlem). Eşleme `ESLEME/flx-app.md`

## İstisnalar

| Uç / akış | Paket | Neden | GraphQL tarafı |
|---|---|---|---|
| FluxApp OAuth (`/oauth/*`), OTP (`/otp/*`), telefon (`/me/phone*`), cihazlar (`/devices*`), ayar köprüsü (`/settings*`), marka (`/app/brand`) | flx-app | OAuth/OTP protokol uçları; cihaz ve ayar köprüsü ekran akışı yok | Geçici; kalkar: Bootstrap, me, sync, config, manifest, dashboard, prefs, oturum GraphQL'de (`docs/flx-app/GRAPHQL.md`) |
| FluxApp bootstrap/me/sync/config/manifest/dashboard/prefs/session REST uçları | flx-app | Mağazadaki eski uygulama sürümleri | Geçici; kalkar: Faz 7 (satır 1 ile birlikte kalkar) |
