# flx-social 0.3.0

- Faz: 2
- Durum: 👀
- PR: #323

`claude/project-thread-yhevwp`: servis katmanı + DTO, 13 sorgu + 22 mutation, 34 kayıtlı işlem, sözleşme testi 70/70, strict PHPStan (services + graphql). React ekranı (`flxsm-social`) klasikle eşit (pano, gönderiler, onaylar, takvim, hesaplar, ayarlar, oluşturucu, ayrıntı), wp-admin varsayılanı; klasik görünüm bağlantısıyla. OAuth, `/tick`, yükleme istisna. Eşleme `ESLEME/flx-social.md`

## İstisnalar

| Uç / akış | Paket | Neden | GraphQL tarafı |
|---|---|---|---|
| Flux Social OAuth (`/flx-social/v1/oauth/start`, `callback/{app}`, `pick/{key}`) | flx-social | Ağ tarayıcıyı bu adrese yönlendirir (standart OAuth); seçim adımı aynı kısa ömürlü kayda bağlı | `flxSocialConnectUrl` başlangıç adresini verir; dönüş `#/accounts?…` ekrana |
| Flux Social dış zamanlayıcı `/flx-social/v1/tick` | flx-social | Hostinger cron'u anahtarlı adresi çağırır, oturum yok | Açık panel `flxSocialTickRun` ile aynı servisi çağırır |
| Flux Social dosya yükleme | flx-social | Ortak `/flx/v1/upload` (bağlam `flx-social/media`); GraphQL'de multipart yok | `flxSocialMediaAdd(uploadId)` |
| Flux Social `/flx-social/v1/calendar` ve FluxApp modül kimliği `social` | flx-social | FluxApp eski sürümleri bu uca ve eski modül kimliğine bağlı (yeni kimlik `flxsm-social`) | En düşük desteklenen uygulama sürümü GraphQL istemcisine geçince |
