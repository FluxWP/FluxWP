# flx-backup 1.5.0

- Faz: 1
- Durum: 👀
- PR: #322

`gql/flx-backup-1`: servis katmanı, 8 sorgu + 7 mutation, 12 kayıtlı işlem, sözleşme testi 82/82. React ekranı (`flxy-backup`: pano, liste, ayrıntı, ayarlar, yedekle/geri yükle/sil/kilit/yükle, iş ilerlemesi) klasikle eşit, wp-admin varsayılanı. İş adım adım (mutation başlatır, durum sorguyla). İndirme ve parçalı yükleme istisna. Eşleme `ESLEME/flx-backup.md`

## İstisnalar

| Uç / akış | Paket | Neden | GraphQL tarafı |
|---|---|---|---|
| Flux Backup: yedek indirme (`admin-post flxy_download`), parçalı yükleme (`admin-ajax flxy_upload_*`) | flx-backup | Dosya akışı; GraphQL yalnız imzalı `downloadUrl` verir, yükleme çok parçalı ikili veri | `FlxBackup.downloadUrl`; yükleme sonrası kayıt `flxBackup` ile okunur |
| Flux Backup: `admin-ajax flxy_step` belirteç yolu | flx-backup | Veritabanı geri yüklemesi oturumu silebilir; ekran iş belirteciyle adımlamayı sürdürmeli | Oturum açıkken `flxBackupStep` |
