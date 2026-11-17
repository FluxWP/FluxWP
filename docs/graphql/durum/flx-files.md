# flx-files 0.2.0

- Faz: 2
- Durum: ✓
- PR: #320

`claude/project-thread-lerwuf`: servis katmanı, 12 sorgu + 18 mutation, 27 kayıtlı işlem, sözleşme testi. React ekranı (`flxfm-files`) klasikle eşit, wp-admin varsayılanı; klasik görünüm bağlantısıyla. Yükleme ve indirme istisna. FluxApp yerel modülü ertelendi. Eşleme `ESLEME/flx-files.md`

## İstisnalar

| Uç / akış | Paket | Neden | GraphQL tarafı |
|---|---|---|---|
| Flux Files parçalı yükleme `POST /wp-json/flx-files/v1/upload` | flx-files | Parçalı, devam ettirilebilir, klasör yapılı yükleme; ortak `/flx/v1/upload` tek parça | Mutation'lar yol alır; yükleme sonrası `flxFilesFolder` |
| Flux Files indirme/önizleme `admin-post.php?action=flxfm_file` | flx-files | Dosya baytı akışı (GraphQL JSON döner); çerez + nonce | `flxFilesFile` içerik/metadata |
| Flux Files `flx-files/v1/op` | flx-files | Klasik ekran ve eski FluxApp sürümleri; servisi çağırır, çıktı aynı | Eski sürümler desteklenmeyince kalkar |
