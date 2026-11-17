# flx-admin 1.8.0

- Faz: 1
- Durum: ✓
- PR: #309

`gql/flx-admin-1`: servis katmanı (settings/menu/caps/user), 7 sorgu + 7 mutation, 13 kayıtlı işlem, sözleşme testi 106/106. React ekranı (`flxa-admin`) klasikle eşit (genel, görünüm, menü editörü, yetkiler, başlangıç, PWA), wp-admin varsayılanı; klasik bağlantıyla. 5 PWA ucu kalıcı, 2 pano görev ucu geçici istisna (ISTISNALAR.md). Eşleme `ESLEME/flx-admin.md`

## İstisnalar

| Uç / akış | Paket | Neden | GraphQL tarafı |
|---|---|---|---|
| PWA protokol uçları: `admin-ajax.php?action=flxa_manifest`, `flxa_sw`, `flxa_offline`, `flxa_splash`, `flxa_auth` | flx-admin | Tarayıcı ve service worker'ın beklediği dosya/yanıt biçimi (manifest, JS, HTML, görsel) ve oturum köprüsü; oturum kontrolünden önce çalışır | PWA ayarları (`pwa`, `pwaName`, `pwaShort`) `flxAdminSettings` içinde |
| Flux Admin: pano hızlı görev formu `flxa_task_form` + `flxa_task_create` | flx-admin | Pano JS'i (yan kabuk) bu iki AJAX'a bağlı; görev oluşturma zaten `flxTaskCreate` ile GraphQL'de. Çift yol yeni mantık içermez (flx-tasks fonksiyonlarını çağırır) | Geçici; kalkar: Pano JS'i GraphQL istemcisine taşınınca (kabuk/tema işi, Faz 2) |
